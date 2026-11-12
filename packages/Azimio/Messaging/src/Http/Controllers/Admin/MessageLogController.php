<?php

namespace Azimio\Messaging\Http\Controllers\Admin;

use Azimio\Messaging\Enums\SmsMode;
use Azimio\Messaging\Enums\SmsStatus;
use Azimio\Messaging\Jobs\SendSmsJob;
use Azimio\Messaging\Models\Batch;
use Azimio\Messaging\Models\Message;
use Azimio\Messaging\Models\SenderProfile;
use Azimio\Messaging\Services\CreditLedger;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Message log and batch history (8.10).
 *
 * Built on Eloquent rather than a raw DataGrid, deliberately. Every other
 * grid in this codebase uses DB::table() and therefore needs the
 * ScopeDataGridToTenant listener from Phase 3.11 to stay tenant-safe. These
 * queries go through the Message/Batch models, so the BelongsToTenant
 * global scope applies and the listing cannot leak across tenants by
 * construction rather than by remembering to register a listener.
 */
class MessageLogController extends Controller
{
    public function __construct(private readonly CreditLedger $ledger)
    {
    }

    public function index(Request $request)
    {
        $messages = $this->filtered($request)
            ->with('batch')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('messaging::admin.log', [
            'messages'  => $messages,
            'statuses'  => SmsStatus::cases(),
            'filters'   => $this->filterValues($request),
            'batches'   => Batch::query()->latest('id')->limit(50)->get(),
            'totals'    => $this->totals($request),
        ]);
    }

    /**
     * Batch history.
     */
    public function batches()
    {
        return view('messaging::admin.batches', [
            'batches' => Batch::query()->latest('id')->paginate(25),
        ]);
    }

    /**
     * One batch, with its per-recipient breakdown.
     */
    public function show(int $id)
    {
        $batch = Batch::query()->findOrFail($id);

        $tenant = $this->tenant();

        return view('messaging::admin.batch', [
            'batch'           => $batch,
            'messages'        => $batch->messages()->latest('id')->paginate(100),
            'failedCount'     => $batch->messages()->whereIn('status', $this->failedStatuses())->count(),
            'retryableCredits' => $this->retryCost($batch),
            'platformCredits' => $this->ledger->balance($tenant),
            'mode'            => SmsMode::tryFrom((string) $tenant->sms_mode) ?? SmsMode::Platform,
        ]);
    }

    /**
     * Re-queue the failed messages in a batch.
     *
     * Two routes in, per decision 8.0.6:
     *
     *  - retry through the SAME sender profile, for a transient failure;
     *  - retry through the PLATFORM profile, which is the explicit
     *    "Send via AZIMIOCRM" action offered only when the tenant actually
     *    has the credits. Nothing falls back automatically.
     *
     * Credits were released when the messages failed (8.0.2), so a retry
     * reserves afresh — it never reuses a spent hold.
     */
    public function retry(int $id, Request $request)
    {
        $batch = Batch::query()->findOrFail($id);
        $tenant = $this->tenant();

        $viaPlatform = $request->boolean('via_platform');

        $failed = $batch->messages()->whereIn('status', $this->failedStatuses())->get();

        if ($failed->isEmpty()) {
            session()->flash('error', trans('messaging::app.sms.log.nothing-to-retry'));

            return redirect()->route('admin.sms.batches.show', $id);
        }

        $profile = $viaPlatform
            ? SenderProfile::platform()
            : $batch->senderProfile;

        if (! $profile || ! $profile->canSend()) {
            session()->flash('error', trans('messaging::app.sms.settings.no-profile'));

            return redirect()->route('admin.sms.batches.show', $id);
        }

        $credits = $profile->isPlatform() ? (int) $failed->sum('segments') : 0;

        $reservation = $this->ledger->reserve($tenant, $credits, Batch::class, $batch->id);

        if ($reservation === null) {
            session()->flash('error', trans('messaging::app.sms.errors.no-platform-credits', [
                'needed'    => $credits,
                'available' => $this->ledger->balance($tenant),
            ]));

            return redirect()->route('admin.sms.batches.show', $id);
        }

        Message::withoutTenancy()->whereIn('id', $failed->pluck('id'))->update([
            'status'            => SmsStatus::Queued->value,
            'sender_profile_id' => $profile->id,
            'cost_source'       => ($profile->isPlatform() ? SmsMode::Platform : SmsMode::Own)->value,
            'error_code'        => null,
            'error_message'     => null,
            'credit_shortfall'  => null,
        ]);

        $batch->forceFill([
            'sender_profile_id' => $profile->id,
            'status'            => 'sending',
            'queued_count'      => $failed->count(),
            'completed_at'      => null,
        ])->save();

        SendSmsJob::dispatch($batch->id, $reservation->credits)
            ->onQueue((string) config('messaging.batch.queue', 'default'));

        session()->flash('success', trans('messaging::app.sms.log.retry-queued', [
            'count' => $failed->count(),
        ]));

        return redirect()->route('admin.sms.batches.show', $id);
    }

    /**
     * CSV of the filtered log, streamed so a large export does not have to
     * fit in memory.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->latest('id');

        $filename = 'sms-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'id', 'sent_at', 'number', 'status', 'segments', 'credits',
                'cost_source', 'error_code', 'error_message', 'batch', 'body',
            ]);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $message) {
                    fputcsv($handle, [
                        $message->id,
                        $message->submitted_at?->toDateTimeString(),
                        $message->dest_addr,
                        $message->status->value,
                        $message->segments,
                        $message->credits_charged,
                        $message->cost_source->value,
                        $message->error_code,
                        $message->error_message,
                        $message->batch_id,
                        $message->body,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Shared filter construction, so the export returns exactly the rows
     * the screen was showing rather than a different set.
     */
    private function filtered(Request $request)
    {
        return Message::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->integer('batch_id')))
            ->when($request->filled('number'), fn ($q) => $q->where('dest_addr', 'like', '%'.$request->input('number').'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));
    }

    /**
     * @return array<string, mixed>
     */
    private function filterValues(Request $request): array
    {
        return [
            'status'   => $request->input('status'),
            'batch_id' => $request->input('batch_id'),
            'number'   => $request->input('number'),
            'from'     => $request->input('from'),
            'to'       => $request->input('to'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function totals(Request $request): array
    {
        $base = $this->filtered($request);

        return [
            'messages' => (clone $base)->count(),
            'segments' => (int) (clone $base)->sum('segments'),
            'credits'  => (int) (clone $base)->sum('credits_charged'),
        ];
    }

    /**
     * What a platform retry of this batch would cost.
     */
    private function retryCost(Batch $batch): int
    {
        return (int) $batch->messages()
            ->whereIn('status', $this->failedStatuses())
            ->sum('segments');
    }

    /**
     * @return string[]
     */
    private function failedStatuses(): array
    {
        return [
            SmsStatus::Failed->value,
            SmsStatus::FailedOwnProvider->value,
            SmsStatus::Rejected->value,
        ];
    }

    private function tenant(): Tenant
    {
        return Tenant::withoutGlobalScopes()->findOrFail(tenant_id());
    }
}
