<?php

namespace App\Services;

use App\Models\MatrimonyProfile;
use Illuminate\Support\Facades\{Http, Log};

/**
 * Biodata handling: read an uploaded biodata (text / PDF / image) and pre-fill the Parinay form, and render a profile as a PDF biodata.
 * Text extraction order: plain text -> `pdftotext` (poppler) -> `tesseract` OCR -> Claude (if ANTHROPIC_API_KEY is set). If nothing can read the file the upload is kept
 * and the member fills the form by hand (status "manual"). The parsed values are only a draft: the member confirms them before anything is saved.
 */
class BiodataService
{
    private const LABELS = [
        'name' => ['name', 'full name', 'candidate name', 'naam', 'groom name', 'bride name'],
        'dob' => ['dob', 'd.o.b', 'date of birth', 'birth date', 'janm tithi', 'janam tithi', 'born'],
        'height' => ['height', 'ht', 'lambai', 'hight'],
        'qualification' => ['qualification', 'education', 'educational qualification', 'padhai', 'shiksha', 'degree'],
        'occupation' => ['occupation', 'profession', 'job', 'working as', 'designation', 'business', 'vyavsay', 'work'],
        'income' => ['income', 'annual income', 'salary', 'package', 'ctc', 'varshik aay'],
        'gotra' => ['gotra', 'gotr'],
        'father' => ['father', "father's name", 'father name', 'pita', 'pitaji'],
        'mother' => ['mother', "mother's name", 'mother name', 'mata', 'mataji'],
        'city' => ['city', 'place', 'native place', 'residence', 'address', 'current location', 'pata', 'shahar'],
        'mobile' => ['mobile', 'mobile no', 'phone', 'contact', 'contact no', 'whatsapp', 'mob'],
        'diet' => ['diet', 'food habit', 'food habits', 'khan pan', 'khaan-paan'],
        'manglik' => ['manglik', 'mangal'],
        'marital' => ['marital status', 'marital', 'status'],
    ];

    public function extract(string $path, string $mime): array
    {
        $text = ''; $method = null;
        if (str_starts_with($mime, 'text/')) { $text = (string) file_get_contents($path); $method = 'text'; }
        elseif ($mime === 'application/pdf' && $this->has('pdftotext')) { $text = (string) shell_exec('pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null'); $method = 'pdftotext'; }
        elseif (str_starts_with($mime, 'image/') && $this->has('tesseract')) { $text = (string) shell_exec('tesseract ' . escapeshellarg($path) . ' stdout -l eng 2>/dev/null'); $method = 'tesseract'; }
        $text = trim(preg_replace('/[ \t]+/', ' ', $text));
        if (mb_strlen($text) < 25 && config('abmm.ai.anthropic_key') && in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            $t = $this->viaClaude($path, $mime); if ($t) { $text = $t; $method = 'claude'; }
        }
        return [mb_strlen($text) >= 10 ? $text : '', $method];
    }

    private function has(string $bin): bool
    {
        if (! function_exists('shell_exec')) return false;
        return trim((string) shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null')) !== '';
    }

    private function viaClaude(string $path, string $mime): ?string
    {
        try {
            $block = $mime === 'application/pdf' ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode(file_get_contents($path))]]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode(file_get_contents($path))]];
            $r = Http::timeout(30)->withHeaders(['x-api-key' => config('abmm.ai.anthropic_key'), 'anthropic-version' => '2023-06-01'])->post('https://api.anthropic.com/v1/messages', [
                'model' => config('abmm.ai.model'), 'max_tokens' => 1200, 'messages' => [['role' => 'user', 'content' => [$block, ['type' => 'text', 'text' => 'Transcribe this matrimonial biodata as plain text, one "Label: value" per line. No commentary.']]]]]);
            return $r->successful() ? trim((string) $r->json('content.0.text')) : null;
        } catch (\Throwable $e) { Log::warning('Biodata AI failed: ' . $e->getMessage()); return null; }
    }

    private static function date(string $v): ?string
    {
        $v = trim(preg_replace('/(\d)(st|nd|rd|th)\b/i', '$1', $v));
        if (preg_match('/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/', $v, $m)) { $y = (int) $m[3]; if ($y < 100) $y += $y > 30 ? 1900 : 2000; return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null; }
        $t = strtotime($v); return $t && (int) date('Y', $t) > 1940 && (int) date('Y', $t) <= (int) date('Y') - 15 ? date('Y-m-d', $t) : null;
    }

    private static function height(string $v): ?int
    {
        if (preg_match('/(\d)\s*(?:\'|ft|feet|foot|fit|\.)\s*(\d{1,2})?/i', $v, $m) && (int) $m[1] >= 4 && (int) $m[1] <= 7) return (int) round(((int) $m[1] * 12 + (int) ($m[2] ?? 0)) * 2.54);
        if (preg_match('/(1[3-9]\d|2[0-2]\d)\s*(?:cm)?/i', $v, $m)) return (int) $m[1];
        return null;
    }

    private static function income(string $v): ?int
    {
        $v = strtolower(str_replace(',', '', $v));
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:lpa|lakh|lac|l\b)/', $v, $m)) return (int) round((float) $m[1] * 100000);
        if (preg_match('/(\d+(?:\.\d+)?)\s*k\b/', $v, $m)) return (int) round((float) $m[1] * 12000);       // "50k" is read as per month
        if (preg_match('/(\d{5,9})/', $v, $m)) return (int) $m[1];
        return null;
    }

    /** "Label : value" lines (also "Label - value") -> fields for the Parinay form. */
    public function parse(string $text): array
    {
        $lines = preg_split('/\R/u', $text); $raw = [];
        foreach ($lines as $line) {
            if (! preg_match('/^\s*[\p{L}\.\' ]{2,32}?\s*[:\-–=]\s*(.+)$/u', $line, $mm)) continue;
            [$label] = preg_split('/\s*[:\-–=]\s*/u', $line, 2); $label = mb_strtolower(trim($label, " .\t"));
            foreach (self::LABELS as $key => $alts) if (in_array($label, $alts, true) && ! isset($raw[$key])) { $raw[$key] = trim($mm[1]); break; }
        }
        $f = [];
        if (isset($raw['name'])) $f['name'] = mb_substr(trim(preg_replace('/\s+/', ' ', $raw['name'])), 0, 120);
        if (isset($raw['dob']) && ($d = self::date($raw['dob']))) $f['dob'] = $d;
        if (isset($raw['height']) && ($h = self::height($raw['height']))) $f['height_cm'] = $h;
        foreach (['qualification', 'occupation', 'gotra', 'city'] as $k) if (isset($raw[$k])) $f[$k] = mb_substr($raw[$k], 0, 120);
        if (isset($raw['income']) && ($i = self::income($raw['income']))) $f['annual_income'] = $i;
        if (isset($raw['mobile']) && preg_match('/(?:\+?91[\s\-]?)?([6-9]\d{9})/', preg_replace('/[\s\-]/', '', $raw['mobile']), $m)) $f['mobile'] = $m[1];
        if (isset($raw['father'])) $f['father_name'] = mb_substr($raw['father'], 0, 120);
        if (isset($raw['mother'])) $f['mother_name'] = mb_substr($raw['mother'], 0, 120);
        if (isset($raw['diet'])) $f['diet'] = stripos($raw['diet'], 'non') !== false ? 'Non-Vegetarian' : (stripos($raw['diet'], 'jain') !== false ? 'Jain' : 'Vegetarian');
        if (isset($raw['manglik'])) $f['manglik'] = ! preg_match('/\b(no|non|nahi|not)\b/i', $raw['manglik']);
        if (isset($raw['marital'])) $f['marital_status'] = preg_match('/divorc/i', $raw['marital']) ? 'Divorcee' : (preg_match('/widow/i', $raw['marital']) ? 'Widowed' : 'Never Married');
        if (preg_match('/\b(male|boy|groom|ladka)\b/i', $text) && ! preg_match('/\b(female|girl|bride|ladki)\b/i', $text)) $f['gender'] = 'Male';
        elseif (preg_match('/\b(female|girl|bride|ladki)\b/i', $text)) $f['gender'] = 'Female';
        return $f;
    }

    private function html(MatrimonyProfile $p, bool $withContact): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rows = ['Name' => $p->name, 'Parinay ID' => $p->parinay_id, 'Gender' => $p->gender, 'Date of birth / Age' => ($p->dob?->format('d M Y') ?? '') . ($p->dob ? ' (' . $p->dob->age . ' yrs)' : ''), 'Height' => $p->height ?: ($p->height_cm ? $p->height_cm . ' cm' : ''),
            'Marital status' => $p->marital_status, 'Qualification' => trim($p->qualification . ' ' . $p->degree), 'Occupation' => trim($p->occupation_type . ' ' . $p->occupation), 'Annual income' => $p->annual_income ? number_format($p->annual_income) : '',
            'Gotra' => $p->gotra, 'Manglik' => $p->manglik ? 'Yes' : 'No', 'Diet' => $p->diet, 'City / State' => trim($p->city . ', ' . $p->state, ', ')] + ($withContact ? ['Mobile' => $p->mobile, 'Address' => $p->address] : []);
        $html = '<h2 style="text-align:center;color:#8a1c1c">Mangal Parinay - Biodata</h2><table width="100%" cellpadding="6" style="border-collapse:collapse;font-size:12pt">';
        foreach ($rows as $k => $v) if (trim((string) $v) !== '') $html .= '<tr><td style="border-bottom:1px solid #ddd;width:35%;color:#555">' . $e($k) . '</td><td style="border-bottom:1px solid #ddd"><b>' . $e($v) . '</b></td></tr>';
        return $html . '</table>' . ($p->about ? '<p style="margin-top:14px"><b>About</b><br>' . nl2br($e($p->about)) . '</p>' : '') . '<p style="font-size:9pt;color:#777;margin-top:24px">Generated by ABMM Sampark. ' . ($withContact ? '' : 'Contact details are shared only after both families agree.') . '</p>';
    }

    /** PDF biodata of a profile. Contact details only when $withContact. */
    public function pdf(MatrimonyProfile $p, bool $withContact = false): string { return $this->pdfMany([$p], $withContact); }

    /** One PDF, one page per profile (used for the scheduled digest). */
    public function pdfMany(iterable $profiles, bool $withContact = false): string
    {
        if (! class_exists(\Mpdf\Mpdf::class)) abort(501, 'To enable PDF, run once: composer require mpdf/mpdf');
        $dir = storage_path('app/mpdf'); if (! is_dir($dir)) @mkdir($dir, 0775, true);
        $m = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $dir]);
        $first = true;
        foreach ($profiles as $p) { if (! $first) $m->AddPage(); $m->WriteHTML($this->html($p, $withContact)); $first = false; }
        return $m->Output('', 'S');
    }
}
