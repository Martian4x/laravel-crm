<?php

namespace Azimio\Messaging\Http\Controllers\Admin;

use Azimio\Messaging\Models\OptOut;
use Azimio\Messaging\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Opt-out management (8.11).
 *
 * Every number is stored NORMALISED, because that is the whole point: a
 * suppression written as "0712 345 678" would not match a send addressed
 * to "+255712345678", and the contact would be messaged again. The
 * normalizer runs on everything that enters this list, including imports.
 *
 * The list is per tenant by design — opting out of one company's messages
 * does not silence another company the person actually deals with.
 */
class OptOutController extends Controller
{
    public function __construct(private readonly PhoneNormalizer $phones)
    {
    }

    public function index(Request $request)
    {
        $search = $request->input('search');

        return view('messaging::admin.opt-outs', [
            'optOuts' => OptOut::query()
                ->when($search, fn ($q) => $q->where('dest_addr', 'like', '%'.$search.'%'))
                ->with('person')
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'search' => $search,
            'total'  => OptOut::query()->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dest_addr' => 'required|string|max:32',
            'reason'    => 'nullable|string|max:255',
        ]);

        $number = $this->phones->normalize($validated['dest_addr']);

        if (! $number->isValid()) {
            session()->flash('error', trans($number->rejection->translationKey()));

            return back()->withInput();
        }

        // firstOrCreate keeps the unique(tenant_id, dest_addr) index from
        // throwing when someone adds the same number twice.
        OptOut::firstOrCreate(
            ['dest_addr' => $number->e164],
            ['source' => 'manual', 'reason' => $validated['reason'] ?? null],
        );

        session()->flash('success', trans('messaging::app.opt-outs.added', ['number' => $number->e164]));

        return redirect()->route('admin.sms.opt-outs.index');
    }

    /**
     * Bulk import, reporting what happened to each line rather than
     * swallowing the failures.
     */
    public function import(Request $request)
    {
        $validated = $request->validate(['numbers' => 'required|string|max:200000']);

        $lines = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $validated['numbers']) ?: []));

        $added = 0;
        $skipped = 0;
        $invalid = [];

        foreach ($lines as $line) {
            $number = $this->phones->normalize($line);

            if (! $number->isValid()) {
                $invalid[] = $line;

                continue;
            }

            $existing = OptOut::query()->where('dest_addr', $number->e164)->exists();

            if ($existing) {
                $skipped++;

                continue;
            }

            OptOut::create([
                'dest_addr' => $number->e164,
                'source'    => 'import',
            ]);

            $added++;
        }

        session()->flash('success', trans('messaging::app.opt-outs.imported', [
            'added'   => $added,
            'skipped' => $skipped,
            'invalid' => count($invalid),
        ]));

        if ($invalid !== []) {
            session()->flash('invalid_numbers', array_slice($invalid, 0, 50));
        }

        return redirect()->route('admin.sms.opt-outs.index');
    }

    /**
     * Remove a suppression.
     *
     * Deliberately possible: a number added by mistake, or a customer who
     * asks to be put back on, must be fixable. The row is deleted rather
     * than flagged, and the SMS log still records every message ever sent.
     */
    public function destroy(int $id)
    {
        $optOut = OptOut::query()->findOrFail($id);

        $number = $optOut->dest_addr;

        $optOut->delete();

        session()->flash('success', trans('messaging::app.opt-outs.removed', ['number' => $number]));

        return redirect()->route('admin.sms.opt-outs.index');
    }

    public function export(): StreamedResponse
    {
        $filename = 'sms-opt-outs-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['dest_addr', 'source', 'reason', 'added_at']);

            OptOut::query()->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $optOut) {
                    fputcsv($handle, [
                        $optOut->dest_addr,
                        $optOut->source,
                        $optOut->reason,
                        $optOut->created_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
