<?php

namespace Azimio\Messaging\Http\Controllers\Admin;

use Azimio\Messaging\Enums\TemplateCategory;
use Azimio\Messaging\Models\Template;
use Azimio\Messaging\Services\MergeFields;
use Azimio\Messaging\Support\SegmentCounter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * SMS template CRUD (8.9).
 *
 * Templates use SHARED tenancy: a NULL-tenant row is a platform template
 * every tenant can use but none may change. BelongsToTenant already blocks
 * the write (Phase 6.1), but relying on a silent `return false` would leave
 * a user clicking Save and seeing nothing happen — so the guard is checked
 * explicitly here and refused with a reason.
 */
class TemplateController extends Controller
{
    public function __construct(private readonly SegmentCounter $segments)
    {
    }

    public function index()
    {
        $templates = Template::query()->orderBy('name')->paginate(25);

        // Cost per recipient, shown in the list: a template is reused many
        // times, so a careless extra segment is a recurring charge.
        $templates->getCollection()->transform(function (Template $template) {
            $count = $this->segments->count($template->body);

            $template->segment_count = $count->segments;
            $template->is_unicode = $count->isUnicode();

            return $template;
        });

        return view('messaging::admin.templates.index', compact('templates'));
    }

    public function create()
    {
        return view('messaging::admin.templates.form', [
            'template'    => new Template(['category' => TemplateCategory::Transactional->value]),
            'mergeFields' => MergeFields::available(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);

        Template::create($validated);

        session()->flash('success', trans('messaging::app.templates.created'));

        return redirect()->route('admin.sms.templates.index');
    }

    public function edit(int $id)
    {
        $template = Template::query()->findOrFail($id);

        if ($template->isPlatformDefault()) {
            session()->flash('error', trans('messaging::app.templates.platform-read-only'));

            return redirect()->route('admin.sms.templates.index');
        }

        return view('messaging::admin.templates.form', [
            'template'    => $template,
            'mergeFields' => MergeFields::available(),
        ]);
    }

    public function update(int $id, Request $request)
    {
        $template = Template::query()->findOrFail($id);

        // Checked explicitly rather than left to the model guard, which
        // would silently refuse and look like a bug to the user.
        if ($template->isPlatformDefault()) {
            session()->flash('error', trans('messaging::app.templates.platform-read-only'));

            return redirect()->route('admin.sms.templates.index');
        }

        $template->update($this->validateTemplate($request));

        session()->flash('success', trans('messaging::app.templates.updated'));

        return redirect()->route('admin.sms.templates.index');
    }

    public function destroy(int $id)
    {
        $template = Template::query()->findOrFail($id);

        if ($template->isPlatformDefault()) {
            session()->flash('error', trans('messaging::app.templates.platform-read-only'));

            return redirect()->route('admin.sms.templates.index');
        }

        $template->delete();

        session()->flash('success', trans('messaging::app.templates.deleted'));

        return redirect()->route('admin.sms.templates.index');
    }

    /**
     * Live segment and credit count for the template being edited, so the
     * figure comes from the same counter the pipeline bills with.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate(['body' => 'nullable|string|max:2000']);

        $count = $this->segments->count($validated['body'] ?? '');

        return response()->json([
            'length'     => $count->length,
            'segments'   => $count->segments,
            'encoding'   => $count->encoding->value,
            'is_unicode' => $count->isUnicode(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name'     => 'required|string|max:255',
            'body'     => 'required|string|max:2000',
            'category' => 'required|in:transactional,marketing',
            'locale'   => 'nullable|in:'.implode(',', array_keys(config('app.available_locales'))),
        ]);
    }
}
