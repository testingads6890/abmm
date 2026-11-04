<?php

namespace App\Services;

use App\Models\{Family, GeoNode, ImportBatch, Member, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Imports a "member sheet": ONE ROW PER PERSON, grouped by Family ID (the format of the Bharatpur CSV
 * and of our own export, so a file can be exported, corrected and imported back).
 *
 *  - The row whose Relationship is HOF (Head of Family) becomes the family; every other row becomes a member of it.
 *  - Family ID is kept as the family code, so re-importing updates instead of duplicating.
 *  - Empty cells never overwrite existing data. "NA", "N/A", "-", "Not Available" are treated as empty.
 *  - Sub-admins can only import into their own area; rows of other areas are skipped.
 */
class MemberSheetImporter
{
    /** header (normalised) => field */
    private const ALIASES = [
        'family_code' => ['family id', 'family code', 'familyid', 'family no', 'fid'],
        'surname' => ['last name', 'surname', 'sir name', 'lastname'],
        'first_name' => ['first name', 'firstname', 'name', 'member name', 'full name'],
        'father' => ['father husband', 'father husband name', 'fathers husband name', 'father name', 'husband name', 'father s husband name'],
        'relation' => ['relationship', 'relation', 'rishta'],
        'mobile' => ['mobile', 'mobile no', 'mobile number', 'contact', 'phone'],
        'whatsapp' => ['whatsapp', 'whatsapp no', 'whatsapp number', 'wa number', 'whatsapp mobile'],
        'email' => ['email', 'e mail', 'email id'],
        'address' => ['address', 'res address', 'residence address'],
        'city' => ['city village', 'city', 'village', 'town', 'village town', 'city village town'],
        'tehsil' => ['tehsil', 'tehsil town', 'tahsil'],
        'district' => ['district'],
        'state' => ['state', 'govt state'],
        'pradesh' => ['pradesh', 'abmm pradesh'],
        'country' => ['country'],
        'zone' => ['zone'],
        'pincode' => ['pincode', 'pin code', 'pin'],
        'gender' => ['gender', 'sex'],
        'blood_group' => ['blood group', 'bloodgroup'],
        'business_type' => ['business type'],
        'business_detail' => ['business type detail', 'business detail'],
        'education' => ['education'],
        'education_detail' => ['education detail'],
        'occupation' => ['occupation'],
        'occupation_detail' => ['occupation detail'],
        'profession' => ['profession'],
        'profession_detail' => ['profession detail'],
        'marital_status' => ['marital status'],
        'anniversary' => ['marriage anniversary', 'anniversary'],
        'dob' => ['date of birth', 'dob', 'birth date'],
        'income' => ['income', 'income range', 'annual income'],
        'caste' => ['caste'], 'gotra' => ['gotra'],
        'aadhar' => ['aadhar', 'aadhaar', 'aadhar number'],
        'profile_updated' => ['profile last updated', 'last updated', 'profile updated'],
    ];

    private const RELATIONS = ['AUNTY', 'BROTHER', 'BROTHER-IN-LAW', 'CHACHA (KAKAJI)', 'CHACHI (KAKIJI)', 'DAUGHTER', 'DAUGHTER OF GRANDSON', 'DAUGHTER-IN-LAW (BAHU)', 'FATHER', 'FATHER-IN-LAW (SASUR)',
        'GRAND DAUGHTER (POTI)', 'GRAND DAUGHTER-IN-LAW', 'GRAND MOTHER', 'GRAND SON (POTA)', 'GRANDFATHER', 'HUSBAND', 'MOTHER', 'MOTHER-IN-LAW', 'NEPHEW (BHATIJA)', 'NEPHEWS WIFE', 'NIECE (BHATIJI)',
        'OTHER', 'SISTER', 'SISTER-IN-LAW', 'SON', 'SON OF GRANDSON', 'SPOUSE', 'STEP MOTHER', 'UNCLE', 'WIFE'];

    private const FEMALE_REL = ['DAUGHTER', 'DAUGHTER OF GRANDSON', 'DAUGHTER-IN-LAW (BAHU)', 'MOTHER', 'MOTHER-IN-LAW', 'WIFE', 'SISTER', 'GRAND MOTHER', 'GRAND DAUGHTER (POTI)', 'AUNTY', 'CHACHI (KAKIJI)', 'STEP MOTHER', 'NIECE (BHATIJI)', 'SISTER-IN-LAW', 'NEPHEWS WIFE', 'GRAND DAUGHTER-IN-LAW'];
    private const MALE_REL = ['SON', 'SON OF GRANDSON', 'FATHER', 'FATHER-IN-LAW (SASUR)', 'HUSBAND', 'BROTHER', 'BROTHER-IN-LAW', 'GRANDFATHER', 'GRAND SON (POTA)', 'UNCLE', 'CHACHA (KAKAJI)', 'NEPHEW (BHATIJA)'];

    public array $stats = ['rows' => 0, 'families_created' => 0, 'families_updated' => 0, 'members_created' => 0, 'members_updated' => 0, 'skipped' => 0];
    public array $notes = [];      // warnings that did not stop a row
    public array $errors = [];     // skipped rows
    public array $counters = ['no_mobile' => 0, 'no_head' => 0, 'bad_date' => 0, 'bad_pincode' => 0, 'bad_mobile' => 0, 'bad_blood' => 0, 'no_relation' => 0, 'duplicate_row' => 0];
    public array $sample = [];
    public ?int $batchId = null;

    private ?User $actor;
    private array $def;
    private array $createdFamilies = [];

    /** $defaults: country, state, pradesh, city, zone — used only where the sheet itself has no value. */
    public function __construct(?User $actor = null, array $defaults = [])
    {
        $this->actor = $actor;
        $this->def = array_map(fn ($v) => $this->blank($v), $defaults);
    }

    public function import(string $path, string $fileName, bool $dryRun): array
    {
        $rows = $this->read($path);
        $this->stats['rows'] = count($rows);

        // group by family code
        $groups = [];
        foreach ($rows as $r) {
            $code = preg_replace('/\D/', '', (string) ($r['data']['family_code'] ?? ''));
            if ($code === '' || strlen($code) > 12) { $this->skip($r['line'], 'Family ID is missing or invalid'); continue; }
            $groups[$code][] = $r;
        }

        DB::beginTransaction();
        try {
            $batch = null;
            if (! $dryRun) {
                $batch = ImportBatch::create(['user_id' => $this->actor?->id, 'file_name' => $fileName, 'rows' => $this->stats['rows']]);
                $this->batchId = $batch->id;
            }
            foreach ($groups as $code => $people) $this->saveFamily((string) $code, $people);

            if ($batch) {
                $batch->update([
                    'families_created' => $this->stats['families_created'], 'families_updated' => $this->stats['families_updated'],
                    'members_created' => $this->stats['members_created'], 'members_updated' => $this->stats['members_updated'], 'skipped' => $this->stats['skipped'],
                    'warnings' => ['counters' => $this->counters, 'errors' => array_slice($this->errors, 0, 100), 'notes' => array_slice($this->notes, 0, 100)],
                ]);
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $this->stats;
    }

    /* ------------------------------------------------------------------ families */

    private function saveFamily(string $code, array $people): void
    {
        // pick the head
        $headIdx = null;
        foreach ($people as $i => $p) {
            if ($this->isHead($p['data']['relation'] ?? null)) { $headIdx = $i; break; }
        }
        $noHead = $headIdx === null;
        if ($noHead) {
            $headIdx = 0;
            foreach ($people as $i => $p) if ($this->mobile($p['data']['mobile'] ?? null)) { $headIdx = $i; break; }
            $this->counters['no_head']++;
            $this->note($people[$headIdx]['line'], "Family $code has no HOF row - " . $this->nice($people[$headIdx]['data']['first_name'] ?? '?') . ' was used as head');
        }
        $head = $people[$headIdx]['data'];
        $hl = $people[$headIdx]['line'];

        if (! $this->blank($head['first_name'] ?? null)) { $this->skip($hl, "Family $code: head has no name"); return; }

        // area guard
        if ($why = $this->outsideArea($head)) { $this->skip($hl, "Family $code: $why"); return; }

        $visible = Family::where('family_code', $code)->first();
        if (! $visible && Family::withoutGlobalScopes()->where('family_code', $code)->exists()) {
            $this->skip($hl, "Family $code already exists in another area - not touched");
            return;
        }

        $fd = $this->familyData($head, $hl, $noHead ? ($head['relation'] ?? null) : 'HOF');
        if ($visible) {
            $visible->fill(array_filter($fd, fn ($v) => $v !== null))->save();
            $family = $visible;
            $this->stats['families_updated']++;
        } else {
            $family = new Family(array_merge(
                ['has_mediclaim' => false, 'pakshik_member' => false, 'father_husband_name' => '-', 'address' => '-', 'city' => '-', 'country' => 'INDIA', 'gender' => 'Male'],
                array_filter($fd, fn ($v) => $v !== null),
                ['family_code' => $code, 'status' => 'active', 'member_count' => 1]
            ));
            $family->created_by = $this->actor?->id;
            $family->import_batch_id = $this->batchId;
            $family->save();
            $this->createdFamilies[$code] = true;
            $this->stats['families_created']++;
        }
        $family->syncHead();
        if (empty($family->mobile)) { $this->counters['no_mobile']++; }

        if (count($this->sample) < 6) {
            $this->sample[] = ['code' => $code, 'head' => trim(($fd['surname'] ?? '') . ' ' . ($fd['head_name'] ?? '')), 'members' => count($people) - 1, 'place' => trim(($fd['city'] ?? '') . ', ' . ($fd['district'] ?? ''), ', '), 'new' => ! $visible];
        }

        // everyone else
        $seen = [$this->key($fd['head_name'] ?? '')];
        foreach ($people as $i => $p) {
            if ($i === $headIdx) continue;
            $d = $p['data'];
            if (! $this->blank($d['first_name'] ?? null)) { $this->skip($p['line'], "Family $code: member row has no name"); continue; }
            $name = $this->nice($d['first_name']);
            if (in_array($this->key($name), $seen, true)) { $this->counters['duplicate_row']++; $this->note($p['line'], "Family $code: '$name' appears more than once in this family - please check it is not a double entry"); }
            $seen[] = $this->key($name);
            $this->saveMember($family, $name, $d, $p['line']);
        }
        $family->refreshCount();
    }

    private function familyData(array $d, int $line, ?string $rel): array
    {
        $district = $this->up($d['district'] ?? null);
        $state = $this->up($d['state'] ?? null) ?: $this->up($this->def['state'] ?? null) ?: $this->stateOf($district);
        $mobile = $this->mobileChecked($d, $line);
        $gender = $this->gender($d['gender'] ?? null, $rel);
        $out = [
            'surname' => $this->up($d['surname'] ?? null) ?: $this->surnameFrom($d['first_name'] ?? ''),
            'head_name' => $this->nice($d['first_name']),
            'father_husband_name' => $this->nice($d['father'] ?? null),
            'mobile' => $mobile,
            'whatsapp' => $this->whatsapp($d['whatsapp'] ?? null),
            'email' => $this->email($d['email'] ?? null),
            'gender' => $gender,
            'address' => $this->blank($d['address'] ?? null),
            'city' => $this->nice($d['city'] ?? null) ?: $this->nice($district) ?: $this->nice($this->def['city'] ?? null),
            'country' => $this->up($d['country'] ?? null) ?: $this->up($this->def['country'] ?? null) ?: 'INDIA',
            'state' => $state,
            'district' => $district,
            'tehsil' => $this->up($d['tehsil'] ?? null),
            'pradesh' => $this->up($d['pradesh'] ?? null) ?: $this->up($this->def['pradesh'] ?? null) ?: $state,
            'zone' => $this->blank($d['zone'] ?? null) ?: ($this->def['zone'] ?? null),
            'pincode' => $this->pincode($d['pincode'] ?? null, $line),
            'dob' => $this->date($d['dob'] ?? null, $line, true),
            'anniversary' => $this->date($d['anniversary'] ?? null, $line),
            'marital_status' => $this->marital($d['marital_status'] ?? null),
            'blood_group' => $this->blood($d['blood_group'] ?? null, $line),
            'education' => $this->blank($d['education'] ?? null), 'education_detail' => $this->blank($d['education_detail'] ?? null),
            'occupation' => $this->blank($d['occupation'] ?? null), 'occupation_detail' => $this->blank($d['occupation_detail'] ?? null),
            'profession' => $this->blank($d['profession'] ?? null), 'profession_detail' => $this->blank($d['profession_detail'] ?? null),
            'business_type' => $this->blank($d['business_type'] ?? null), 'business_detail' => $this->blank($d['business_detail'] ?? null),
            'income_range' => $this->blank($d['income'] ?? null),
            'has_mediclaim' => $this->yes($d['has_mediclaim'] ?? null), 'pakshik_member' => $this->yes($d['pakshik_member'] ?? null),
            'profile_updated_on' => $this->date($d['profile_updated'] ?? null, $line, true),
            'caste' => $this->blank($d['caste'] ?? null), 'gotra' => $this->blank($d['gotra'] ?? null),
        ];
        if ($a = preg_replace('/\D/', '', (string) ($d['aadhar'] ?? '')) and strlen($a) === 12) $out['aadhar'] = $a;
        return $out;
    }

    /* ------------------------------------------------------------------ members */

    private function saveMember(Family $family, string $name, array $d, int $line): void
    {
        $rel = $this->relation($d['relation'] ?? null, $line);
        $want = $this->key($name);   // ignore case and extra spaces, so "Dipiti  Maheshwari" and "dipiti maheshwari" are the same person
        $m = Member::withoutGlobalScopes()->where('family_id', $family->id)->where('is_head', false)->get()->first(fn ($x) => $this->key((string) $x->name) === $want);
        $new = ! $m;
        $m ??= new Member(['family_id' => $family->id, 'name' => $name, 'is_head' => false, 'status' => 'active']);

        $data = [
            'surname' => $this->up($d['surname'] ?? null) ?: $family->surname,
            'relation' => $rel,
            'gender' => $this->gender($d['gender'] ?? null, $rel),
            'dob' => $this->date($d['dob'] ?? null, $line, true),
            'anniversary' => $this->date($d['anniversary'] ?? null, $line),
            'blood_group' => $this->blood($d['blood_group'] ?? null, $line),
            'mobile' => $this->mobileChecked($d, $line),
            'email' => $this->email($d['email'] ?? null),
            'father_husband_name' => $this->nice($d['father'] ?? null),
            'marital_status' => $this->marital($d['marital_status'] ?? null),
            'education' => $this->blank($d['education'] ?? null), 'education_detail' => $this->blank($d['education_detail'] ?? null),
            'occupation' => $this->blank($d['occupation'] ?? null), 'occupation_detail' => $this->blank($d['occupation_detail'] ?? null),
            'profession' => $this->blank($d['profession'] ?? null), 'profession_detail' => $this->blank($d['profession_detail'] ?? null),
            'business_type' => $this->blank($d['business_type'] ?? null), 'business_detail' => $this->blank($d['business_detail'] ?? null),
            'income_range' => $this->blank($d['income'] ?? null),
            'address' => $this->blank($d['address'] ?? null), 'city' => $this->nice($d['city'] ?? null),
            'pincode' => $this->pincode($d['pincode'] ?? null, $line), 'tehsil' => $this->up($d['tehsil'] ?? null),
            'has_mediclaim' => $this->yes($d['has_mediclaim'] ?? null), 'pakshik_member' => $this->yes($d['pakshik_member'] ?? null),
            'profile_updated_on' => $this->date($d['profile_updated'] ?? null, $line, true),
            'caste' => $this->blank($d['caste'] ?? null), 'gotra' => $this->blank($d['gotra'] ?? null),
        ];
        // never blank out what we already have
        $m->fill(array_filter($data, fn ($v) => $v !== null));
        if ($a = preg_replace('/\D/', '', (string) ($d['aadhar'] ?? '')) and strlen($a) === 12) $m->aadhar = $a;
        if ($new) {
            $m->import_batch_id = $this->batchId;
            $m->relation ??= 'OTHER';
            $this->stats['members_created']++;
        } else {
            $this->stats['members_updated']++;
        }
        $m->save();
        if (! $m->mobile) $this->counters['no_mobile']++;
    }

    /* ------------------------------------------------------------------ reading */

    /** @return array<int, array{line:int, data:array}> */
    private function read(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') throw new \RuntimeException('The file is empty.');
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        $first = strtok($raw, "\n");
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');

        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        $head = fgetcsv($h, 0, $delim);
        $map = [];
        foreach ($head as $i => $col) {
            $n = $this->norm((string) $col);
            if (str_contains($n, 'mediclaim') || str_contains($n, 'health insurance')) { $map[$i] = 'has_mediclaim'; continue; }
            if (str_contains($n, 'pakshik')) { $map[$i] = 'pakshik_member'; continue; }
            foreach (self::ALIASES as $field => $names) {
                if (in_array($n, $names, true) && ! in_array($field, $map, true)) { $map[$i] = $field; break; }
            }
        }
        $fields = array_values($map);
        foreach (['family_code', 'first_name'] as $need) {
            if (! in_array($need, $fields, true)) throw new \RuntimeException('Required column missing: ' . ($need === 'family_code' ? '"Family ID"' : '"First Name"') . '. Download the sample file to see the format.');
        }

        $out = [];
        $line = 1;
        while (($row = fgetcsv($h, 0, $delim)) !== false) {
            $line++;
            if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue;
            $d = [];
            foreach ($map as $i => $f) $d[$f] = isset($row[$i]) ? trim((string) $row[$i]) : null;
            $out[] = ['line' => $line, 'data' => $d];
        }
        fclose($h);
        return $out;
    }

    /* ------------------------------------------------------------------ cleaning helpers */

    private function norm(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s))));
    }

    /** trims; "NA", "N/A", "-", "Not Available", "Nil" ... => null */
    private function blank($v): ?string
    {
        if ($v === null) return null;
        $v = trim(preg_replace('/\s+/', ' ', (string) $v));
        if ($v === '' || in_array(mb_strtolower($v), ['na', 'n/a', 'n.a.', '-', '--', '---', 'not available', 'nil', 'none', 'null', 'not applicable', 'unknown'], true)) return null;
        return $v;
    }

    private function nice($v): ?string
    {
        $v = $this->blank($v);
        if ($v === null) return null;
        return (mb_strtoupper($v) === $v || mb_strtolower($v) === $v) ? mb_convert_case($v, MB_CASE_TITLE, 'UTF-8') : $v;
    }

    private function up($v): ?string
    {
        $v = $this->blank($v);
        return $v === null ? null : mb_strtoupper($v);
    }

    private function key(string $s): string { return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s))); }

    private function surnameFrom(string $name): string
    {
        $p = preg_split('/\s+/', trim($name));
        return mb_strtoupper(end($p) ?: '-');
    }

    private function email($v): ?string
    {
        $v = $this->blank($v);
        return ($v && filter_var($v, FILTER_VALIDATE_EMAIL)) ? mb_strtolower($v) : null;
    }

    /** Optional WhatsApp number: the last 10 digits, or null when blank or not a number. Never blocks the row. */
    private function whatsapp($v): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $v);
        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    private function mobile($v): ?string
    {
        $n = preg_replace('/\D/', '', (string) $v);
        if (strlen($n) > 10) $n = substr($n, -10);
        return preg_match('/^[6-9]\d{9}$/', $n) ? $n : null;
    }

    private function mobileChecked(array $d, int $line): ?string
    {
        $raw = $this->blank($d['mobile'] ?? null);
        if ($raw === null) return null;
        $m = $this->mobile($raw);
        if (! $m) { $this->counters['bad_mobile']++; $this->note($line, "Mobile '$raw' is not a valid 10-digit number - left empty"); }
        return $m;
    }

    private function pincode($v, int $line): ?string
    {
        $v = $this->blank($v);
        if ($v === null || preg_match('/^0+$/', $v)) return null;
        $n = preg_replace('/\D/', '', $v);
        if (strlen($n) !== 6) { $this->counters['bad_pincode']++; $this->note($line, "Pincode '$v' is not 6 digits - left empty"); return null; }
        return $n;
    }

    private function blood($v, int $line): ?string
    {
        $v = $this->blank($v);
        if ($v === null) return null;
        if (preg_match('/^(AB|A|B|O)\s*(\+|-|positive|negative|ve)?$/i', str_replace(['ve', '`', "'"], ['', '', ''], $v), $m) && ! empty($m[2])) {
            return strtoupper($m[1]) . (str_starts_with(strtolower($m[2]), 'n') || $m[2] === '-' ? '-' : '+');
        }
        $this->counters['bad_blood']++;   // "Don't Know" etc.
        return null;
    }

    private function gender($v, ?string $rel): ?string
    {
        $g = match (mb_strtolower(mb_substr(trim((string) $v), 0, 1))) { 'm' => 'Male', 'f' => 'Female', 'o', 't' => 'Other', default => null };
        if ($g) return $g;
        $r = $this->canonRel($rel);
        return in_array($r, self::FEMALE_REL, true) ? 'Female' : (in_array($r, self::MALE_REL, true) ? 'Male' : null);
    }

    private function marital($v): ?string
    {
        $v = $this->blank($v);
        if ($v === null) return null;
        $u = mb_strtoupper($v);
        return match (true) {
            str_starts_with($u, 'UNMAR'), $u === 'SINGLE', $u === 'NEVER MARRIED' => 'UNMARRIED',
            str_starts_with($u, 'MARR') => 'MARRIED',
            $u === 'WIDOWER' => 'WIDOWER', str_starts_with($u, 'WIDOW') => 'WIDOW',
            str_starts_with($u, 'DIVOR') => 'DIVORCEE',
            default => $u,
        };
    }

    private function yes($v): ?bool
    {
        return match (mb_strtolower(trim((string) $v))) { 'yes', 'y', 'true', '1' => true, 'no', 'n', 'false', '0' => false, default => null };
    }

    private function isHead(?string $rel): bool
    {
        return in_array($this->norm((string) $rel), ['hof', 'self', 'head', 'head of family', 'head of the family'], true);
    }

    private function canonRel(?string $rel): ?string
    {
        $u = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $rel)));
        return in_array($u, self::RELATIONS, true) ? $u : null;
    }

    private function relation(?string $rel, int $line): string
    {
        $c = $this->canonRel($rel);
        if ($c) return $c;
        $this->counters['no_relation']++;
        return 'OTHER';
    }

    private function date($v, int $line, bool $notFuture = false): ?string
    {
        $v = $this->blank($v);
        if ($v === null || str_starts_with($v, '0000')) return null;
        $v = preg_replace_callback('/[A-Za-z]{3,}/', fn ($m) => ucfirst(strtolower(substr($m[0], 0, 3))), $v);   // 04-dec-1998 / DEC => Dec
        foreach (['d-M-Y', 'j-M-Y', 'd/M/Y', 'd-m-Y', 'd/m/Y', 'j/n/Y', 'Y-m-d', 'd-M-y', 'd.m.Y'] as $fmt) {
            try {
                $c = Carbon::createFromFormat('!' . $fmt, $v);
                if ($c && $c->year >= 1900 && $c->year <= (int) date('Y') + 1 && ! $c->isFuture()) return $c->toDateString();
                if ($c && $c->isFuture()) break;
            } catch (\Throwable) {}
        }
        $this->counters['bad_date']++;
        $this->note($line, "Date '$v' not understood - left empty");
        return null;
    }

    private function stateOf(?string $district): ?string
    {
        if (! $district || ! class_exists(GeoNode::class)) return null;
        try {
            $d = GeoNode::where('level', 'district')->whereRaw('LOWER(name) = ?', [mb_strtolower($district)])->first();
            $s = $d ? GeoNode::find($d->parent_id) : null;
            return $s ? mb_strtoupper($s->name) : null;
        } catch (\Throwable) { return null; }
    }

    /** Non-super users may only import their own area. Returns the reason when the family is outside it. */
    private function outsideArea(array $head): ?string
    {
        $u = $this->actor;
        if (! $u || $u->isSuper()) return null;
        $f = $u->areaFilter();
        if (! $f || ! $u->hasArea()) return 'you have no area assigned';
        if (! empty($f['district']) && ($dist = $this->up($head['district'] ?? null)) && mb_strtoupper($f['district']) !== $dist) return "district '$dist' is outside your area ({$f['district']})";
        if (! empty($f['pradesh']) && ($p = $this->up($head['pradesh'] ?? null)) && mb_strtoupper($f['pradesh']) !== $p) return "pradesh '$p' is outside your area ({$f['pradesh']})";
        return null;
    }

    private function skip(int $line, string $why): void
    {
        $this->stats['skipped']++;
        if (count($this->errors) < 300) $this->errors[] = "Line $line: $why";
    }

    private function note(int $line, string $why): void
    {
        if (count($this->notes) < 300) $this->notes[] = "Line $line: $why";
    }

    /* ------------------------------------------------------------------ undo */

    /**
     * Removes what a batch CREATED so a wrong file can be corrected and imported again.
     * A family or member that already has an app login (or cannot be deleted) is kept but deactivated/archived instead.
     * Rows the import only UPDATED are never touched.
     */
    public static function undo(ImportBatch $b): array
    {
        $out = ['deleted' => 0, 'kept' => 0];
        foreach (Family::withoutGlobalScopes()->where('import_batch_id', $b->id)->get() as $f) {
            $hasLogin = User::where('family_id', $f->id)->exists();
            if (! $hasLogin) {
                try {
                    DB::transaction(function () use ($f) {
                        Member::withoutGlobalScopes()->where('family_id', $f->id)->delete();
                        $f->delete();
                    });
                    $out['deleted']++;
                    continue;
                } catch (\Throwable) { /* referenced elsewhere: fall through to deactivate */ }
            }
            $f->update(['status' => 'inactive']);
            Member::withoutGlobalScopes()->where('family_id', $f->id)->where('status', '!=', 'archived')
                ->update(['status' => 'archived', 'archive_type' => 'Wrong Entry', 'archived_at' => now(), 'archive_note' => 'Import #' . $b->id . ' undone']);
            $out['kept']++;
        }
        // members this batch added to families that existed before
        foreach (Member::withoutGlobalScopes()->where('import_batch_id', $b->id)->get() as $m) {
            if (User::where('member_id', $m->id)->exists()) {
                $m->update(['status' => 'archived', 'archive_type' => 'Wrong Entry', 'archived_at' => now(), 'archive_note' => 'Import #' . $b->id . ' undone']);
            } else {
                $fam = $m->family_id;
                $m->delete();
                Family::withoutGlobalScopes()->find($fam)?->refreshCount();
            }
        }
        $b->update(['undone_at' => now()]);
        return $out;
    }
}
