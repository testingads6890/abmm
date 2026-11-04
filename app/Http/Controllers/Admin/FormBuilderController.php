<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, FormDefinition, FormSubmission, UserNotification};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Online forms: build once in the panel, members fill + pay in the app, admins approve. */
class FormBuilderController extends Controller
{
    public function index()
    {
        return view('admin.global.forms', ['forms' => FormDefinition::withCount('submissions')->orderByDesc('id')->get()]);
    }

    public function edit(?FormDefinition $form = null)
    {
        return view('admin.global.form-edit', ['f' => $form ?? new FormDefinition(['fields' => [], 'needs_approval' => true, 'currency' => 'INR', 'is_active' => true])]);
    }

    /** One field per line:  key | Label | type | required(1/0) | option1,option2   */
    public static function parseFields(string $text): array
    {
        $out = []; $seen = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 3 || $p[0] === '') continue;
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower($p[0]));
            if ($key === '' || isset($seen[$key]) || ! isset(FormDefinition::TYPES[$p[2]])) continue;
            $seen[$key] = 1;
            $out[] = ['key' => $key, 'label' => $p[1] ?: $key, 'type' => $p[2], 'required' => ($p[3] ?? '0') === '1',
                'options' => $p[2] === 'select' ? array_values(array_filter(array_map('trim', explode(',', $p[4] ?? '')))) : []];
        }
        return $out;
    }

    public static function fieldsText(array $fields): string
    {
        return implode("\n", array_map(fn ($f) => implode(' | ', [$f['key'], $f['label'], $f['type'], $f['required'] ? 1 : 0, implode(',', $f['options'] ?? [])]), $fields));
    }

    public function save(Request $r, ?FormDefinition $form = null)
    {
        $d = $r->validate([
            'slug' => ['required', 'alpha_dash', 'max:60', Rule::unique('form_definitions', 'slug')->ignore($form?->id)], 'title' => 'required|string|max:150', 'description' => 'nullable|string|max:3000',
            'fields_text' => 'required|string|max:10000', 'fee' => 'required|numeric|min:0|max:9999999', 'currency' => 'required|alpha|size:3', 'country' => 'nullable|string|max:100', 'city' => 'nullable|string|max:150',
        ]);
        $fields = self::parseFields($d['fields_text']);
        abort_if(! $fields, 422, 'Write at least one valid field (key | Label | type | required | options).');
        $data = ['slug' => $d['slug'], 'title' => $d['title'], 'description' => $d['description'] ?? null, 'fields' => $fields, 'fee' => $d['fee'], 'currency' => strtoupper($d['currency']),
            'needs_approval' => $r->boolean('needs_approval'), 'country' => $d['country'] ?? null, 'city' => $d['city'] ?? null, 'is_active' => $r->boolean('is_active')];
        $form ? $form->update($data) : FormDefinition::create($data + ['created_by' => $r->user()->id]);
        return redirect()->route('admin.forms.index')->with('ok', 'Form saved.');
    }

    public function destroy(FormDefinition $form)
    {
        abort_if($form->submissions()->exists(), 422, 'It has submissions - disable it instead of deleting.');
        $form->delete();
        return back()->with('ok', 'Form hata diya.');
    }

    public function submissions(Request $r)
    {
        $ids = $r->user()->areaFamilyIds();
        $rows = FormSubmission::with('form')->when($ids, fn ($q) => $q->whereIn('family_id', $ids))
            ->when($r->form_id, fn ($q, $v) => $q->where('form_id', $v))->when($r->status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')->paginate(30)->withQueryString();
        return view('admin.global.submissions', ['rows' => $rows, 'forms' => FormDefinition::orderBy('title')->get(['id', 'title'])]);
    }

    public function decide(Request $r, FormSubmission $submission)
    {
        $ids = $r->user()->areaFamilyIds();
        abort_unless(! $ids || ($submission->family_id && (clone $ids)->where('id', $submission->family_id)->exists()), 404);
        $d = $r->validate(['decision' => 'required|in:approved,rejected', 'remark' => 'nullable|string|max:250']);
        abort_if($d['decision'] === 'approved' && $submission->form->fee > 0 && $submission->payment_status !== 'paid', 422, 'The fee is not paid yet - cannot approve.');
        $submission->update(['status' => $d['decision'], 'remark' => $d['remark'] ?? null, 'decided_by' => $r->user()->id, 'decided_at' => now()]);
        if ($submission->user_id) UserNotification::create(['user_id' => $submission->user_id, 'title' => "{$submission->form->title} ({$submission->reference}): " . ucfirst($d['decision']) . ($d['remark'] ? " - {$d['remark']}" : '')]);
        ActivityLog::create(['user_id' => $r->user()->id, 'action' => 'FORM_DECIDE', 'detail' => "{$submission->reference} {$d['decision']}", 'ip' => $r->ip()]);
        return back()->with('ok', 'Decision saved, the applicant has been notified.');
    }
}
