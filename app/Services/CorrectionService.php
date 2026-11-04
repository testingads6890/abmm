<?php

namespace App\Services;

use App\Models\{Family, Member, UserNotification};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectionService
{
    /** field => validation rule. Mobile / aadhaar are deliberately NOT here (login identity / sensitive). */
    public const FIELDS = [
        'name' => 'string|max:150', 'surname' => 'string|max:100', 'father_husband_name' => 'string|max:150', 'mother_name' => 'string|max:150',
        'gender' => 'in:Male,Female,Other', 'dob' => 'date|before:today', 'anniversary' => 'date', 'blood_group' => 'string|max:5', 'marital_status' => 'string|max:30',
        'education' => 'string|max:150', 'occupation' => 'string|max:150', 'profession' => 'string|max:150', 'business_type' => 'string|max:150',
        'email' => 'email|max:150', 'address' => 'string|max:400', 'city' => 'string|max:100', 'pincode' => 'regex:/^\d{4,10}$/',
    ];

    public function validate(string $field, mixed $value): string
    {
        abort_unless(isset(self::FIELDS[$field]), 422, 'A correction request is not possible for this field.');
        $v = validator(['v' => $value], ['v' => 'required|' . self::FIELDS[$field]]);
        if ($v->fails()) throw ValidationException::withMessages(['new_value' => $v->errors()->first('v')]);
        return trim((string) $value);
    }

    public function current(string $target, int $id, string $field): ?string
    {
        $row = $target === 'family' ? Family::withoutGlobalScopes()->find($id) : Member::withoutGlobalScopes()->find($id);
        $col = ($target === 'family' && $field === 'name') ? 'head_name' : $field;
        $v = $row?->{$col};
        return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v === null ? null : (string) $v);
    }

    public function decide(object $c, string $decision, ?string $remark, int $by): void
    {
        DB::transaction(function () use ($c, $decision, $remark, $by) {
            if ($decision === 'approved') {
                if ($c->target === 'family') {
                    $f = Family::withoutGlobalScopes()->findOrFail($c->family_id);
                    $f->update([($c->field === 'name' ? 'head_name' : $c->field) => $c->new_value]);
                    $f->syncHead();
                } else {
                    $m = Member::withoutGlobalScopes()->where('family_id', $c->family_id)->findOrFail($c->member_id);
                    $m->update([$c->field => $c->new_value]);
                    if ($m->is_head) { // keep the head's row and the family record in step
                        $fam = Family::withoutGlobalScopes()->find($c->family_id);
                        $fcol = $c->field === 'name' ? 'head_name' : $c->field;
                        if ($fam && \Illuminate\Support\Facades\Schema::hasColumn('families', $fcol)) $fam->update([$fcol => $c->new_value]);
                    }
                }
            }
            DB::table('data_corrections')->where('id', $c->id)->update(['status' => $decision, 'remark' => $remark, 'decided_by' => $by, 'decided_at' => now(), 'updated_at' => now()]);
            UserNotification::create(['user_id' => $c->user_id, 'title' => "Correction request ({$c->field}): " . ucfirst($decision) . ($remark ? " - $remark" : '')]);
        });
    }
}
