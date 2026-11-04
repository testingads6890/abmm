<?php

namespace App\Support;

use App\Models\{Family, Lookup, Member};
use Illuminate\Support\Facades\Storage;

/** Shared helpers of the member (Flutter) API: validation rules, location resolving, JSON shapes. */
class MemberApp
{
    /** Fields common to a head-of-family and a member (4-step Add Member form). */
    public static function rules(bool $creating = true): array
    {
        $req = $creating ? 'required' : 'sometimes|required';
        return [
            'name' => "$req|string|max:100", 'surname' => "$req|string|max:80", 'father_husband_name' => "$req|string|max:120", 'mother_name' => 'nullable|string|max:120',
            'gender' => "$req|in:MALE,FEMALE,OTHER", 'relation' => 'nullable|string|max:40', 'dob' => "$req|date|before_or_equal:today|after:1900-01-01",
            'aadhar' => ['nullable', 'regex:/^\d{12}$/'], 'blood_group' => 'nullable|string|max:5',
            'marital_status' => "$req|string|max:20", 'anniversary' => 'nullable|date', 'income_range' => 'nullable|string|max:80',
            'email' => 'nullable|email|max:120', 'mobile' => ['nullable', self::mobileRule()], 'country_code' => ['nullable', 'regex:/^\+\d{1,4}$/'],
            'caste' => ['nullable', 'string', 'max:60', self::inMaster('caste')], 'gotra' => ['nullable', 'string', 'max:80', self::inMaster('gotra')],
            'city' => 'nullable|string|max:100', 'address' => 'nullable|string|max:400', 'pincode' => ['nullable', 'regex:/^\d{6}$/'],
            'resident_type' => 'nullable|string|max:20', 'country' => 'nullable|string|max:60', 'zone' => 'nullable|string|max:100', 'state' => 'nullable|string|max:80', 'district' => 'nullable|string|max:80',
            'education' => 'nullable|string|max:120', 'education_detail' => 'nullable|string|max:200', 'occupation' => 'nullable|string|max:120', 'occupation_detail' => 'nullable|string|max:200',
            'profession' => 'nullable|string|max:120', 'profession_detail' => 'nullable|string|max:200', 'business_type' => 'nullable|string|max:120', 'business_detail' => 'nullable|string|max:200',
            'photo' => 'nullable|image|max:3072',
        ];
    }

    /** Mobile: 10-digit Indian number for +91, 6-14 digits for any other country code (sent as country_code). */
    public static function mobileRule(): \Closure
    {
        return function ($attr, $v, $fail) {
            $cc = request()->input('country_code') ?: '+91';
            $ok = $cc === '+91' ? preg_match('/^[6-9]\d{9}$/', (string) $v) : preg_match('/^\d{6,14}$/', (string) $v);
            if (! $ok) $fail($cc === '+91' ? 'Enter a valid 10-digit mobile number.' : 'Enter a valid mobile number (6-14 digits).');
        };
    }

    /** Value must exist (active) in the given Master Data list, e.g. caste / gotra. */
    public static function inMaster(string $type): \Closure
    {
        return function ($attr, $v, $fail) use ($type) {
            if ($v !== null && $v !== '' && ! Lookup::ofType($type)->active()->where('name', $v)->exists()) $fail("This is not in the $type list.");
        };
    }

    /** Fills tehsil / district / state / pradesh / pincode from the Tehsil-Town master when the typed city matches it. */
    public static function location(array $d): array
    {
        $city = trim((string) ($d['city'] ?? ''));
        if ($city !== '') {
            $meta = Lookup::tehsilMeta();
            $hit = $meta[$city] ?? $meta[strtoupper($city)] ?? null;
            if (! $hit) foreach ($meta as $name => $m) if (strcasecmp($name, $city) === 0) { $hit = $m; $city = $name; break; }
            if ($hit) {
                $d['city'] = $city; $d['tehsil'] = $city;
                $d['district'] = $hit['district'] ?? ($d['district'] ?? null); $d['state'] = $hit['state'] ?? ($d['state'] ?? null);
                $d['pradesh'] = $hit['pradesh'] ?? null; $d['pincode'] = $d['pincode'] ?? $hit['pincode'];
            }
        }
        if (empty($d['pradesh']) && ! empty($d['state'])) {
            $d['pradesh'] = Lookup::where('type', 'state')->where('name', $d['state'])->first()?->parent?->name;
        }
        return $d;
    }

    public static function storePhoto($file, string $dir): string
    {
        return $file->store($dir, 'public');
    }

    public static function photoUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function age(?\DateTimeInterface $dob): ?string
    {
        if (! $dob) return null;
        $d = \Carbon\Carbon::instance($dob)->diff(now());
        return "{$d->y} Years {$d->m} Months";
    }

    public static function member(Member $m): array
    {
        return [
            'id' => $m->id, 'member_code' => $m->member_code, 'name' => $m->name, 'surname' => $m->surname, 'full_name' => trim("$m->surname $m->name"),
            'relation' => $m->is_head ? 'Head of Family' : $m->relation, 'is_head' => (bool) $m->is_head, 'gender' => $m->gender, 'mobile' => $m->mobile, 'email' => $m->email,
            'dob' => $m->dob?->toDateString(), 'age_text' => self::age($m->dob), 'age' => $m->dob?->age, 'photo_url' => self::photoUrl($m->photo),
            'father_husband_name' => $m->father_husband_name, 'mother_name' => $m->mother_name, 'blood_group' => $m->blood_group, 'marital_status' => $m->marital_status,
            'anniversary' => $m->anniversary?->toDateString(), 'income_range' => $m->income_range, 'city' => $m->city, 'address' => $m->address, 'pincode' => $m->pincode,
            'resident_type' => $m->resident_type, 'education' => $m->education, 'education_detail' => $m->education_detail, 'occupation' => $m->occupation,
            'occupation_detail' => $m->occupation_detail, 'profession' => $m->profession, 'profession_detail' => $m->profession_detail, 'business_type' => $m->business_type,
            'business_detail' => $m->business_detail, 'status' => $m->status, 'approval' => $m->approval, 'archive_type' => $m->archive_type,
            'aadhar_masked' => self::mask($m->aadhar_enc), 'caste' => $m->caste, 'gotra' => $m->gotra, 'country_code' => $m->country_code ?: '+91',
        ];
    }

    public static function family(Family $f): array
    {
        return [
            'id' => $f->id, 'family_code' => $f->family_code, 'head_name' => $f->head_name, 'surname' => $f->surname, 'mobile' => $f->mobile, 'photo_url' => self::photoUrl($f->photo),
            'member_count' => $f->member_count, 'address' => $f->address, 'city' => $f->city, 'tehsil' => $f->tehsil, 'district' => $f->district, 'state' => $f->state,
            'pradesh' => $f->pradesh, 'country' => $f->country, 'pincode' => $f->pincode, 'resident_type' => $f->resident_type, 'income_range' => $f->income_range,
            'caste' => $f->caste, 'gotra' => $f->gotra, 'country_code' => $f->country_code ?: '+91', 'has_mediclaim' => (bool) $f->has_mediclaim, 'pakshik_member' => (bool) $f->pakshik_member, 'updated_at' => $f->updated_at?->toDateString(),
        ];
    }

    private static function mask(?string $enc): ?string
    {
        if (! $enc) return null;
        try { return 'XXXX-XXXX-' . substr(\Illuminate\Support\Facades\Crypt::decryptString($enc), -4); } catch (\Throwable) { return null; }
    }

    /** Column map: member-form field => families column (head of family is stored on the family row). */
    public const FAMILY_MAP = ['name' => 'head_name'];
    public const FAMILY_COLS = ['surname', 'father_husband_name', 'mother_name', 'gender', 'dob', 'anniversary', 'blood_group', 'education', 'education_detail', 'occupation', 'occupation_detail',
        'profession', 'profession_detail', 'business_type', 'business_detail', 'marital_status', 'income_range', 'mobile', 'email', 'address', 'city', 'tehsil', 'state', 'district',
        'pradesh', 'pincode', 'resident_type', 'country', 'aadhar', 'caste', 'gotra', 'country_code', 'zone'];
    public const MEMBER_COLS = ['name', 'surname', 'father_husband_name', 'mother_name', 'gender', 'relation', 'dob', 'anniversary', 'blood_group', 'education', 'education_detail',
        'occupation', 'occupation_detail', 'profession', 'profession_detail', 'business_type', 'business_detail', 'marital_status', 'income_range', 'mobile', 'email', 'address',
        'city', 'pincode', 'resident_type', 'aadhar', 'caste', 'gotra', 'country_code'];
}
