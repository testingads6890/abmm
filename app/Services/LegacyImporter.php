<?php

namespace App\Services;

use App\Models\{Family, Member};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Imports the old ABMM Sampark export (CSV, UTF-8) into families / members.
 * Header names are matched loosely (see ALIASES), so both the old system's Excel->CSV export
 * and a hand-made sheet work. Existing rows are updated (matched by family_code, else legacy_id,
 * else mobile + head name), so re-running an import is safe.
 */
class LegacyImporter
{
    private const ALIASES = [
        'family_code' => ['family_code', 'family id', 'familyid', 'family_id', 'fid'],
        'legacy_id' => ['legacy_id', 'member id', 'memberid', 'old_id', 'id'],
        'surname' => ['surname', 'sir name', 'last name', 'lastname'],
        'head_name' => ['head_name', 'name', 'head of the family', 'name (head of the family)', 'full name', 'hof name', 'first name', 'firstname'],
        'father_husband_name' => ['father_husband_name', "father's/ husband name", "father's/husband name", 'father name', 'husband name', 'father/husband name', "father's / husband name"],
        'mother_name' => ['mother_name', "mother's name", 'mother name'],
        'mobile' => ['mobile', 'mobile number', 'mobile no', 'contact', 'phone', 'mob'],
        'gender' => ['gender', 'sex'],
        'member_count' => ['member_count', 'no. of members', 'no of members', 'no. of family member', 'members', 'family members'],
        'resident_type' => ['resident_type', 'resident type'],
        'address' => ['address', 'res. address', 'residence address', 'res address'],
        'city' => ['city', 'village/town/city', 'town', 'village', 'village/town'],
        'country' => ['country'], 'state' => ['state', 'govt. state', 'govt state'],
        'district' => ['district'], 'tehsil' => ['tehsil', 'tehsil / town', 'tehsil/town'],
        'pradesh' => ['pradesh', 'abmm pradesh'], 'pincode' => ['pincode', 'pin code', 'pin'],
        'aadhar' => ['aadhar', 'aadhaar', 'aadhar card number', 'aadhar number'],
        'phone' => ['telephone', 'telephone no', 'tel'], 'email' => ['email', 'e-mail', 'email id'],
        'dob' => ['dob', 'date of birth', 'birth date'], 'marital_status' => ['marital_status', 'marital status'],
        'anniversary' => ['anniversary', 'marriage anniversary'], 'blood_group' => ['blood_group', 'blood group'],
        'education' => ['education'], 'occupation' => ['occupation'], 'business_type' => ['business_type', 'business type'],
        'profession' => ['profession'], 'income_range' => ['income_range', 'income'],
    ];

    public array $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
    public array $errors = [];

    /** @return array{created:int,updated:int,skipped:int} */
    public function importFamilies(string $path, bool $dryRun = false): array
    {
        $h = $this->open($path, $map);
        DB::beginTransaction();
        try {
            $line = 1;
            while (($row = fgetcsv($h)) !== false) {
                $line++;
                if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) continue;
                $d = $this->mapRow($map, $row);
                $this->normaliseFamily($d);
                $v = Validator::make($d, ['head_name' => 'required|max:120', 'mobile' => 'required|digits:10', 'district' => 'nullable|max:80', 'pincode' => 'nullable|max:10']);
                if ($v->fails()) { $this->skip($line, $v->errors()->first()); continue; }

                $existing = $this->find($d);
                $payload = $d;
                unset($payload['aadhar']);
                if ($existing) {
                    $existing->fill(array_filter($payload, fn ($x) => $x !== null && $x !== ''))->save();
                    $existing->syncHead();
                    if (! empty($d['aadhar'])) $existing->update(['aadhar' => $d['aadhar']]);
                    $this->stats['updated']++;
                } else {
                    $payload['family_code'] = $d['family_code'] ?: Family::nextCode();
                    $f = new Family($payload + ['status' => 'active']);
                    if (! empty($d['aadhar'])) $f->aadhar = $d['aadhar'];
                    $f->save();
                    $f->syncHead();
                    $this->stats['created']++;
                }
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($h);
        }
        return $this->stats;
    }

    /** Members CSV: needs a family_code (or family mobile) column + name, relation, gender, dob, ... */
    public function importMembers(string $path, bool $dryRun = false): array
    {
        $h = $this->open($path, $map, ['family_code' => ['family_code', 'family id', 'familyid'], 'name' => ['name', 'member name', 'full name'], 'relation' => ['relation', 'relationship', 'rishta'], 'gender' => ['gender'], 'dob' => ['dob', 'date of birth'], 'blood_group' => ['blood group', 'blood_group'], 'education' => ['education'], 'occupation' => ['occupation'], 'marital_status' => ['marital status', 'marital_status'], 'mobile' => ['mobile', 'mobile number']]);
        DB::beginTransaction();
        try {
            $line = 1;
            while (($row = fgetcsv($h)) !== false) {
                $line++;
                $d = $this->mapRow($map, $row);
                if (empty($d['name'])) continue;
                $fam = Family::where('family_code', $d['family_code'] ?? '')->first();
                if (! $fam) { $this->skip($line, 'family_code not found: ' . ($d['family_code'] ?? '-')); continue; }
                $m = Member::firstOrNew(['family_id' => $fam->id, 'name' => $d['name']]);
                $m->fill(array_filter([
                    'relation' => $d['relation'] ?? 'Member', 'gender' => $this->gender($d['gender'] ?? null), 'dob' => $this->date($d['dob'] ?? null),
                    'blood_group' => $d['blood_group'] ?? null, 'education' => $d['education'] ?? null, 'occupation' => $d['occupation'] ?? null,
                    'marital_status' => $d['marital_status'] ?? null, 'mobile' => $this->mobile($d['mobile'] ?? null),
                ]));
                $m->relation ??= 'Member';
                $m->surname ??= $fam->surname;
                $m->exists ? $this->stats['updated']++ : $this->stats['created']++;
                $m->save();
                if ($m->wasRecentlyCreated && ! $dryRun) $fam->refreshCount();
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            fclose($h);
        }
        return $this->stats;
    }

    private function open(string $path, &$map, ?array $aliases = null)
    {
        $h = fopen($path, 'r');
        if (! $h) throw new \RuntimeException('The file could not be opened.');
        $first = fgets($h);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // strip BOM
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        rewind($h);
        $head = fgetcsv($h, 0, $delim);
        $head[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $head[0]);
        $aliases ??= self::ALIASES;
        $map = [];
        foreach ($head as $i => $col) {
            $c = strtolower(trim(preg_replace('/\s+/', ' ', (string) $col)));
            foreach ($aliases as $field => $names) {
                if (in_array($c, $names, true) && ! in_array($field, $map, true)) { $map[$i] = $field; break; }
            }
        }
        if (! $map) throw new \RuntimeException('No recognised column header found.');
        $this->delim = $delim;
        return $h;
    }

    private string $delim = ',';

    private function mapRow(array $map, array $row): array
    {
        $d = [];
        foreach ($map as $i => $field) $d[$field] = isset($row[$i]) ? trim((string) $row[$i]) : null;
        return $d;
    }

    private function normaliseFamily(array &$d): void
    {
        $d['mobile'] = $this->mobile($d['mobile'] ?? null);
        $d['gender'] = $this->gender($d['gender'] ?? null) ?? 'Male';
        $d['dob'] = $this->date($d['dob'] ?? null);
        $d['anniversary'] = $this->date($d['anniversary'] ?? null);
        $d['member_count'] = max(1, (int) ($d['member_count'] ?? 1));
        $d['country'] = $d['country'] ?? 'INDIA' ?: 'INDIA';
        $d['surname'] = $d['surname'] ?? $this->surnameFrom($d['head_name'] ?? '');
        $d['father_husband_name'] = $d['father_husband_name'] ?? '-';
        $d['address'] = $d['address'] ?? '-';
        $d['city'] = $d['city'] ?? ($d['district'] ?? '-');
        $d['resident_type'] = (isset($d['resident_type']) && stripos($d['resident_type'], 'nri') !== false) ? 'NRI' : 'Resident';
        $d['income_range'] = $d['income_range'] ?? 'Not specified';
        foreach (['state', 'district', 'pradesh', 'tehsil'] as $k) if (! empty($d[$k])) $d[$k] = strtoupper($d[$k]);
        $d['family_code'] = isset($d['family_code']) ? preg_replace('/\D/', '', $d['family_code']) : null;
        if (! empty($d['aadhar'])) $d['aadhar'] = preg_match('/^\d{12}$/', preg_replace('/\D/', '', $d['aadhar'])) ? preg_replace('/\D/', '', $d['aadhar']) : null;
        $d = array_map(fn ($v) => $v === '' ? null : $v, $d);
    }

    private function find(array $d): ?Family
    {
        if (! empty($d['family_code']) && ($f = Family::where('family_code', $d['family_code'])->first())) return $f;
        if (! empty($d['legacy_id']) && ($f = Family::where('legacy_id', $d['legacy_id'])->first())) return $f;
        return Family::where('mobile', $d['mobile'])->where('head_name', $d['head_name'])->first();
    }

    private function mobile(?string $v): ?string
    {
        $n = preg_replace('/\D/', '', (string) $v);
        return strlen($n) >= 10 ? substr($n, -10) : null;
    }

    private function gender(?string $v): ?string
    {
        return match (strtolower(substr(trim((string) $v), 0, 1))) { 'm' => 'Male', 'f' => 'Female', 'o', 't' => 'Other', default => null };
    }

    private function date(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || str_starts_with($v, '0000')) return null;
        foreach (['d-M-Y', 'd-m-Y', 'd/m/Y', 'Y-m-d', 'd-M-y', 'm/d/Y'] as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $v);
                if ($c && $c->format($fmt) === $v && $c->year > 1900 && $c->lte(now())) return $c->toDateString();
            } catch (\Throwable) {}
        }
        return null;
    }

    private function surnameFrom(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        return strtoupper(end($parts) ?: '-');
    }

    private function skip(int $line, string $why): void
    {
        $this->stats['skipped']++;
        if (count($this->errors) < 200) $this->errors[] = "Line $line: $why";
    }
}
