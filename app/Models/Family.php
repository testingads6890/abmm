<?php

namespace App\Models;

use App\Models\Concerns\HasArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Family extends Model
{
    use HasArea;

    protected static array $areaColumns = ['pradesh', 'district', 'tehsil', 'country', 'city', 'zone'];

    protected $guarded = [];
    protected $casts = ['extra' => 'array', 'dob' => 'date', 'anniversary' => 'date', 'profile_updated_on' => 'date', 'has_mediclaim' => 'boolean', 'pakshik_member' => 'boolean'];

    public function members() { return $this->hasMany(Member::class); }
    public function activeMembers() { return $this->hasMany(Member::class)->where('status', 'active'); }
    public function head() { return $this->hasOne(Member::class)->where('is_head', true); }

    /** Keep the head-of-family row in `members` in sync (original system lists HOF as a member too). */
    public function syncHead(): void
    {
        $this->members()->withoutGlobalScopes()->updateOrCreate(['is_head' => true], [
            'name' => $this->head_name, 'surname' => $this->surname, 'relation' => 'Self', 'gender' => $this->gender, 'dob' => $this->dob,
            'blood_group' => $this->blood_group, 'education' => $this->education, 'occupation' => $this->occupation, 'profession' => $this->profession,
            'business_type' => $this->business_type, 'marital_status' => $this->marital_status, 'mobile' => $this->mobile, 'email' => $this->email,
            'father_husband_name' => $this->father_husband_name, 'mother_name' => $this->mother_name, 'anniversary' => $this->anniversary, 'photo' => $this->photo,
            'education_detail' => $this->education_detail, 'occupation_detail' => $this->occupation_detail, 'profession_detail' => $this->profession_detail,
            'business_detail' => $this->business_detail, 'income_range' => $this->income_range, 'city' => $this->city, 'address' => $this->address,
            'pincode' => $this->pincode, 'resident_type' => $this->resident_type, 'aadhar_enc' => $this->aadhar_enc,
            'caste' => $this->caste, 'gotra' => $this->gotra, 'country_code' => $this->country_code ?: '+91',
        ]);
    }

    public function refreshCount(): void
    {
        $this->update(['member_count' => max(1, $this->members()->withoutGlobalScopes()->where('status', 'active')->count())]);
    }

    /** Aadhar is stored encrypted; only last 4 digits are shown in the UI. */
    public function setAadharAttribute(?string $v): void
    {
        $this->attributes['aadhar_enc'] = $v ? Crypt::encryptString(preg_replace('/\D/', '', $v)) : null;
    }

    public function getAadharMaskedAttribute(): ?string
    {
        if (! $this->aadhar_enc) return null;
        try { return 'XXXX-XXXX-' . substr(Crypt::decryptString($this->aadhar_enc), -4); } catch (\Throwable) { return null; }
    }

    public function scopeSearch($q, ?string $term)
    {
        if (! $term) return $q;
        return $q->where(fn ($w) => $w->where('head_name', 'like', "%$term%")
            ->orWhere('surname', 'like', "%$term%")->orWhere('mobile', 'like', "%$term%")->orWhere('family_code', 'like', "%$term%"));
    }

    public static function nextCode(): string
    {
        do { $c = (string) random_int(100000, 999999); } while (static::where('family_code', $c)->exists());
        return $c;
    }
}
