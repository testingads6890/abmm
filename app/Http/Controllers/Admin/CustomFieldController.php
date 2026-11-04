<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Support\CustomForm;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Super Admin: add / edit / hide extra fields on any supported form without a code change. */
class CustomFieldController extends Controller
{
    public function index(Request $r)
    {
        $form = array_key_exists($r->form, CustomForm::FORMS) ? $r->form : array_key_first(CustomForm::FORMS);
        return view('admin.custom.index', ['form' => $form, 'cats' => \App\Models\Lookup::ofType('trust_category')->active()->orderBy('name')->pluck('name'), 'rows' => CustomField::where('form', $form)->orderBy('sort')->orderBy('id')->get()]);
    }

    private function rules(): array
    {
        return ['label' => 'required|max:120', 'type' => ['required', Rule::in(array_keys(CustomForm::TYPES))], 'options' => 'nullable|string|max:3000', 'sort' => 'nullable|integer|min:0|max:9999',
            'category' => 'nullable|max:120', 'required' => 'boolean', 'show_in_list' => 'boolean', 'is_active' => 'boolean'];
    }

    private function check(array $d): void
    {
        if (($d['type'] ?? '') === 'select' && count(array_filter(array_map('trim', preg_split('/\R/', (string) ($d['options'] ?? ''))))) < 1) {
            back()->withErrors(['options' => 'Write at least one option for the dropdown (one per line).'])->throwResponse();
        }
    }

    public function store(Request $r)
    {
        $d = $r->validate($this->rules() + ['form' => ['required', Rule::in(array_keys(CustomForm::FORMS))]]);
        $this->check($d);
        $base = Str::slug($d['label'], '_') ?: 'field';
        $key = $base; $i = 2;
        while (CustomField::where('form', $d['form'])->where('key', $key)->exists()) $key = $base . '_' . $i++;
        CustomField::create(['category' => $d['form'] === 'trust' ? ($d['category'] ?? null) ?: null : null] + $d + ['key' => $key, 'sort' => $d['sort'] ?? (CustomField::where('form', $d['form'])->max('sort') + 10)]);
        CustomForm::forget($d['form']);
        return redirect()->route('admin.customfields', ['form' => $d['form']])->with('ok', "Field '{$d['label']}' added.");
    }

    public function update(Request $r, CustomField $field)
    {
        $d = $r->validate($this->rules());
        $this->check($d);
        $field->update(['category' => $field->form === 'trust' ? ($d['category'] ?? null) ?: null : null] + $d + ['required' => $r->boolean('required'), 'show_in_list' => $r->boolean('show_in_list'), 'is_active' => $r->boolean('is_active')]); // key never changes
        CustomForm::forget($field->form);
        return back()->with('ok', 'Field updated.');
    }

    /** Deleting only removes the field definition; values already saved stay in the records (hide with Active=off if unsure). */
    public function destroy(CustomField $field)
    {
        $field->delete();
        CustomForm::forget($field->form);
        return back()->with('ok', 'Field removed (old saved data in records is safe).');
    }
}
