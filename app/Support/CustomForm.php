<?php

namespace App\Support;

use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/** Super-Admin-defined extra fields per form. Values live in the model's `extra` JSON column, keyed by field key. */
class CustomForm
{
    public const FORMS = [
        'trust'     => 'Trust form',
        'family'    => 'Family (Add / Edit Family)',
        'member'    => 'Member (Add New Member)',
        'committee' => 'Committee member',
        'matrimony' => 'Mangal Parinay profile',
    ];
    public const TYPES = ['text' => 'Text', 'textarea' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'email' => 'Email', 'phone' => 'Phone (10 digit)', 'select' => 'Dropdown', 'checkbox' => 'Yes / No tick'];

    /** Active fields of one form as plain arrays (cached). */
    public static function fields(string $form): array
    {
        return Cache::remember("customfields.$form", 300, fn () => CustomField::where('form', $form)->where('is_active', true)->orderBy('sort')->orderBy('id')
            ->get()->map(fn ($f) => ['key' => $f->key, 'label' => $f->label, 'type' => $f->type, 'category' => $f->category, 'options' => $f->optionList(), 'required' => $f->required, 'show_in_list' => $f->show_in_list])->all());
    }

    /** Fields that apply to a category (trust form: category-specific fields + the ones for all). */
    public static function forCategory(string $form, ?string $category): array
    {
        return array_values(array_filter(self::fields($form), fn ($f) => ! $f['category'] || $f['category'] === $category));
    }

    public static function forget(string $form): void { Cache::forget("customfields.$form"); Cache::forget('api.masters'); }

    public static function rules(string $form, ?string $category = null): array
    {
        $rules = [];
        foreach (self::forCategory($form, $category) as $f) {
            $r = [$f['required'] && $f['type'] !== 'checkbox' ? 'required' : 'nullable'];
            $r[] = match ($f['type']) {
                'number' => 'numeric', 'date' => 'date', 'email' => 'email|max:160', 'phone' => 'digits:10', 'checkbox' => 'boolean',
                'select' => Rule::in($f['options']), 'textarea' => 'string|max:2000', default => 'string|max:255',
            };
            $rules['extra.' . $f['key']] = $r;
        }
        return $rules;
    }

    /** Validates the submitted extra[...] inputs and returns them merged over $existing (values of switched-off fields are kept). */
    public static function collect(Request $r, string $form, ?array $existing = []): array
    {
        $cat = $form === 'trust' ? (string) $r->input('trust_category') : null;
        $rules = self::rules($form, $cat);
        $out = (array) $existing;
        if (! $rules) return $out;
        $v = $r->validate($rules)['extra'] ?? [];
        foreach (self::forCategory($form, $cat) as $f) {
            if (array_key_exists($f['key'], $v) || $f['type'] === 'checkbox') $out[$f['key']] = $v[$f['key']] ?? null;
        }
        return $out;
    }

    /** label => printable value for the active fields (used on list columns, PDF and e-mail). */
    public static function display(string $form, ?array $extra, bool $listOnly = false, ?string $category = null): array
    {
        $o = [];
        foreach ($category !== null ? self::forCategory($form, $category) : self::fields($form) as $f) {
            if ($listOnly && ! $f['show_in_list']) continue;
            $val = $extra[$f['key']] ?? null;
            $o[$f['label']] = $f['type'] === 'checkbox' ? ($val ? 'Yes' : 'No') : (string) ($val ?? '');
        }
        return $o;
    }
}
