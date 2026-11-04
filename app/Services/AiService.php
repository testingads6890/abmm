<?php

namespace App\Services;

use App\Models\{JobPost, JobProfile, MatrimonyProfile};
use Illuminate\Support\Facades\{Http, Log};

/**
 * AI helpers for Matrimony, Jobs and keyword search.
 *  - understand():   free text (English / Hindi / Hinglish) -> structured filters ("CA ladka Jaipur 28 saal veg", "part time accountant Pune 20k")
 *  - matchScore():   explainable compatibility score + reasons between two matrimony profiles
 *  - jobScore():     explainable fit between a job seeker profile and a job (also used to rank applicants)
 *  - draft*():       bio / job description drafts
 * Everything works offline with rules; if ANTHROPIC_API_KEY is set the text understanding and drafts are refined by Claude.
 */
class AiService
{
    private const STOP = ['a', 'an', 'the', 'in', 'at', 'for', 'of', 'me', 'mein', 'main', 'ka', 'ki', 'ke', 'ko', 'se', 'hai', 'chahiye', 'chahie', 'wala', 'wali', 'jo', 'aur', 'and', 'or', 'with', 'job', 'jobs', 'naukri', 'rishta',
        'profile', 'saal', 'years', 'year', 'yrs', 'age', 'umar', 'lakh', 'lac', 'lpa', 'per', 'month', 'ft', 'feet', 'cm', 'to', 'below', 'above', 'under', 'zyada', 'kam', 'ladka', 'ladki', 'boy', 'girl', 'groom', 'bride'];

    public function enabled(): bool { return (bool) config('abmm.ai.anthropic_key'); }

    /** Calls Claude; returns null when not configured / failing so callers keep their rule-based result. */
    private function llm(string $system, string $user, int $max = 500): ?string
    {
        if (! $this->enabled()) return null;
        try {
            $r = Http::timeout((int) config('abmm.ai.timeout', 12))->withHeaders(['x-api-key' => config('abmm.ai.anthropic_key'), 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', ['model' => config('abmm.ai.model'), 'max_tokens' => $max, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]]]);
            return $r->successful() ? trim((string) ($r->json('content.0.text') ?? '')) ?: null : null;
        } catch (\Throwable $e) { Log::warning('AI call failed: ' . $e->getMessage()); return null; }
    }

    public static function tokens(string $text): array
    {
        $t = preg_split('/[^\p{L}\p{N}\+\#\.]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_filter($t, fn ($w) => mb_strlen($w) > 1 && ! in_array($w, self::STOP, true) && ! is_numeric($w))));
    }

    /** domain = matrimony | job | all. $places = known city / state names (lower-case) used to spot places in the text. */
    public function understand(string $text, string $domain, array $places = []): array
    {
        $t = ' ' . mb_strtolower(trim($text)) . ' ';
        $f = [];
        if ($domain === 'matrimony') {
            if (preg_match('/\b(ladki|ladkee|girl|bride|female|dulhan|kanya|beti)\b/u', $t)) $f['gender'] = 'Female';
            elseif (preg_match('/\b(ladka|boy|groom|male|dulha|var|beta)\b/u', $t)) $f['gender'] = 'Male';
            if (preg_match('/(\d{2})\s*(?:-|–|to|se)\s*(\d{2})/u', $t, $m) && $m[1] >= 18 && $m[2] <= 70 && $m[1] <= $m[2]) { $f['age_min'] = (int) $m[1]; $f['age_max'] = (int) $m[2]; }
            elseif (preg_match('/(?:under|below|se kam|tak)\s*(\d{2})|(\d{2})\s*(?:se kam|tak|below)/u', $t, $m)) { $f['age_max'] = (int) ($m[1] ?: $m[2]); }
            elseif (preg_match('/(\d{2})\s*(?:saal|years|year|yrs|yr)\b/u', $t, $m) && $m[1] >= 18 && $m[1] <= 70) { $f['age_min'] = (int) $m[1] - 2; $f['age_max'] = (int) $m[1] + 2; }
            if (preg_match('/\b(\d)\s*(?:\'|ft|feet|foot|fit)\s*(\d{1,2})?/u', $t, $m)) $f['height_min'] = (int) round(((int) $m[1] * 12 + (int) ($m[2] ?? 0)) * 2.54) - 3;
            elseif (preg_match('/\b(1[4-9]\d|2[0-2]\d)\s*cm\b/u', $t, $m)) $f['height_min'] = (int) $m[1] - 3;
            if (preg_match('/(\d+(?:\.\d+)?)\s*(?:lakh|lac|lpa|l\b)/u', $t, $m)) $f['income_min'] = (int) round((float) $m[1] * 100000);
            if (preg_match('/\b(non[\s-]?manglik|not manglik|manglik nahi|non manglik)\b/u', $t)) $f['manglik'] = 'no'; elseif (preg_match('/\bmanglik\b/u', $t)) $f['manglik'] = 'yes';
            if (preg_match('/\b(jain)\b/u', $t)) $f['diet'] = 'jain'; elseif (preg_match('/\b(veg|vegetarian|shakahari|pure veg)\b/u', $t)) $f['diet'] = 'veg';
            if (preg_match('/\b(unmarried|never married|kunwara|kunwari)\b/u', $t)) $f['marital'] = 'never'; elseif (preg_match('/\b(divorcee|divorced|talaq)\b/u', $t)) $f['marital'] = 'divorc'; elseif (preg_match('/\b(widow|widower)\b/u', $t)) $f['marital'] = 'widow';
        } elseif ($domain === 'job') {
            if (preg_match('/\b(work from home|wfh|remote|ghar se)\b/u', $t)) $f['work_mode'] = 'remote'; elseif (preg_match('/\bhybrid\b/u', $t)) $f['work_mode'] = 'hybrid';
            if (preg_match('/\b(part[\s-]?time)\b/u', $t)) $f['type'] = 'part_time'; elseif (preg_match('/\b(intern|internship)\b/u', $t)) $f['type'] = 'internship'; elseif (preg_match('/\b(freelance|freelancer)\b/u', $t)) $f['type'] = 'freelance'; elseif (preg_match('/\bfull[\s-]?time\b/u', $t)) $f['type'] = 'full_time';
            if (preg_match('/\b(fresher|freshers|no experience)\b/u', $t)) $f['exp'] = 0; elseif (preg_match('/(\d{1,2})\s*\+?\s*(?:saal|years|year|yrs|yr)\b/u', $t, $m)) $f['exp'] = (int) $m[1];
            if (preg_match('/(\d+(?:\.\d+)?)\s*k\b/u', $t, $m)) $f['salary_min'] = (int) round((float) $m[1] * 1000);
            elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:lakh|lac|lpa)\b/u', $t, $m)) $f['salary_min'] = (int) round((float) $m[1] * 100000 / (preg_match('/lpa/u', $t) ? 12 : 1));
        }
        foreach ($places as $p) if ($p !== '' && str_contains($t, ' ' . $p . ' ')) { $f['places'][] = $p; }
        $kw = self::tokens(preg_replace('/\b(work from home|wfh|part[\s-]?time|full[\s-]?time|non[\s-]?manglik)\b/u', ' ', $text));
        $kw = array_values(array_filter($kw, fn ($w) => ! in_array($w, $f['places'] ?? [], true) && ! in_array($w, ['veg', 'vegetarian', 'jain', 'manglik', 'unmarried', 'divorcee', 'widow', 'widower', 'fresher', 'freshers', 'remote', 'wfh', 'hybrid', 'intern', 'internship', 'freelance', 'part', 'time', 'full', 'non', 'k'], true)));
        if ($kw) $f['keywords'] = $kw;
        if ($this->enabled() && $domain !== 'all') {
            $j = $this->llm('Extract search filters as one JSON object only (no prose). Keys allowed: ' . ($domain === 'matrimony' ? 'gender(Male|Female), age_min, age_max, height_min(cm), income_min(rupees/year), manglik(yes|no), diet(veg|jain), keywords[](qualification/occupation/community words)'
                : 'type(full_time|part_time|internship|freelance), work_mode(onsite|remote|hybrid), exp(years), salary_min(rupees/month), keywords[](role/skill words)') . '. The text may be English, Hindi or Hinglish.', $text, 250);
            $a = $j ? json_decode(preg_replace('/^```(?:json)?|```$/m', '', $j), true) : null;
            if (is_array($a)) $f = array_merge($f, array_filter(array_intersect_key($a, array_flip(['gender', 'age_min', 'age_max', 'height_min', 'income_min', 'manglik', 'diet', 'type', 'work_mode', 'exp', 'salary_min', 'keywords'])), fn ($v) => $v !== null && $v !== ''));
        }
        return $f;
    }

    // ---------------- matrimony ----------------

    /** @return array{score:int, reasons:string[], concerns:string[]} 0-100, how well $b suits $a (and vice versa through partner prefs of both). */
    public function matchScore(MatrimonyProfile $a, MatrimonyProfile $b): array
    {
        $score = 50.0; $why = []; $bad = [];
        $pa = (array) $a->partner_pref; $pb = (array) $b->partner_pref;
        $ageB = $b->dob?->age; $ageA = $a->dob?->age;
        // age: preferred window first, else the usual few-years rule (male older by 0-6 years)
        if ($ageB) {
            if (isset($pa['age_min'], $pa['age_max'])) { if ($ageB >= $pa['age_min'] && $ageB <= $pa['age_max']) { $score += 10; $why[] = "Age {$ageB} is within their preference ($pa[age_min]-$pa[age_max])"; } else { $score -= 12; $bad[] = "Age {$ageB} is outside their preference"; } }
            elseif ($ageA) { $gap = strcasecmp((string) $a->gender, 'male') === 0 ? $ageA - $ageB : $ageB - $ageA; if ($gap >= 0 && $gap <= 5) { $score += 8; $why[] = 'Good age gap'; } elseif ($gap < -2 || $gap > 8) { $score -= 8; $bad[] = 'Age gap is large'; } }
        }
        if ($ageA && isset($pb['age_min'], $pb['age_max'])) { if ($ageA >= $pb['age_min'] && $ageA <= $pb['age_max']) { $score += 5; $why[] = 'You are within their preferred age range'; } else { $score -= 6; } }
        if (! empty($pa['height_min']) && $b->height_cm) { if ($b->height_cm >= $pa['height_min']) { $score += 4; $why[] = 'Height matches the preference'; } else { $score -= 5; $bad[] = 'Height is below the preference'; } }
        if (! empty($pa['income_min']) && $b->annual_income) { if ($b->annual_income >= $pa['income_min']) { $score += 5; $why[] = 'Income matches the preference'; } else { $score -= 4; } }
        foreach (['qualification' => 'Padhai', 'marital' => 'Marital status', 'diet' => 'Khaan-paan', 'state' => 'Rajya'] as $k => $label) {
            if (empty($pa[$k])) continue;
            $col = $k === 'marital' ? 'marital_status' : $k; $want = array_map('mb_strtolower', (array) $pa[$k]);
            if (in_array(mb_strtolower((string) $b->{$col}), $want, true)) { $score += 4; $why[] = "$label matches their preference"; }
        }
        if ($b->manglik && ! $a->manglik) { $score -= 4; $bad[] = 'Keep the Manglik status in mind'; } elseif ((bool) $b->manglik === (bool) $a->manglik) { $score += 3; $why[] = 'Manglik status samaan'; }
        if ($a->diet && $b->diet && mb_strtolower($a->diet) === mb_strtolower($b->diet)) { $score += 5; $why[] = 'Similar food habits (' . $b->diet . ')'; }
        elseif ($a->diet && $b->diet && (stripos($a->diet, 'non') !== false) !== (stripos($b->diet, 'non') !== false)) { $score -= 7; $bad[] = 'Khaan-paan alag'; }
        if ($a->city && $b->city && mb_strtolower($a->city) === mb_strtolower($b->city)) { $score += 8; $why[] = 'Ek hi sheher (' . $b->city . ')'; } elseif ($a->state && $b->state && mb_strtolower($a->state) === mb_strtolower($b->state)) { $score += 4; $why[] = 'Ek hi rajya'; }
        if ($a->gotra && $b->gotra && mb_strtolower($a->gotra) === mb_strtolower($b->gotra)) { $score -= 40; $bad[] = 'Same gotra'; }
        if ($a->annual_income && $b->annual_income && abs($a->annual_income - $b->annual_income) <= 300000) { $score += 3; }
        if ($a->qualification && $b->qualification && mb_strtolower($a->qualification) === mb_strtolower($b->qualification)) { $score += 3; $why[] = 'Samaan shiksha star'; }
        return ['score' => (int) max(1, min(99, round($score))), 'reasons' => array_slice($why, 0, 5), 'concerns' => array_slice($bad, 0, 3)];
    }

    /** Hinglish / Hindi helper words -> extra search terms */
    public const SYNONYMS = ['shaadi' => ['marriage', 'vivah', 'matrimony'], 'vivah' => ['marriage', 'shaadi'], 'doctor' => ['clinic', 'hospital', 'dr'], 'dawai' => ['pharma', 'medical'], 'khana' => ['caterer', 'food', 'halwai', 'restaurant'],
        'halwai' => ['caterer', 'sweets'], 'kapda' => ['textile', 'garments', 'cloth'], 'sona' => ['jewellery', 'gold'], 'gehne' => ['jewellery'], 'pandit' => ['purohit', 'pooja'], 'photo' => ['photographer', 'videographer'], 'dharamshala' => ['bhawan', 'bhavan'],
        'bhavan' => ['bhawan', 'dharamshala'], 'hotel' => ['resort', 'lodge'], 'school' => ['education', 'academy'], 'vakil' => ['advocate', 'lawyer', 'legal'], 'ca' => ['chartered', 'accountant', 'accounts'], 'accountant' => ['ca', 'accounts', 'tally']];

    public function expand(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $t) { $out[$t] = array_values(array_unique(array_merge([$t], self::SYNONYMS[$t] ?? []))); }
        return $out;                                              // token => [token + synonyms]
    }

    public function draftAbout(array $p): string
    {
        $age = ! empty($p['dob']) ? \Carbon\Carbon::parse($p['dob'])->age : null;
        $bits = array_filter([($p['name'] ?? '') ? explode(' ', trim($p['name']))[0] : null, $age ? "$age saal" : null, $p['qualification'] ?? null, ! empty($p['occupation']) ? $p['occupation'] : null, ! empty($p['city']) ? $p['city'] . ' se' : null]);
        $diet = ! empty($p['diet']) ? ' Khaan-paan: ' . $p['diet'] . '.' : '';
        $fallback = 'Hum ' . implode(', ', $bits) . ' ).' . $diet . ' Looking for an understanding and supportive life partner who values family values and traditions.';
        $ai = $this->llm('Write a warm, respectful 2-3 sentence matrimonial profile "about me" in simple Hinglish (Roman script). No phone numbers, no exaggeration, nothing invented beyond the facts given.', json_encode($p, JSON_UNESCAPED_UNICODE), 220);
        return $ai ?: $fallback;
    }

    // ---------------- jobs ----------------

    /** How well a job seeker profile fits a job. */
    public function jobScore(JobProfile $p, JobPost $j): array
    {
        $score = 30.0; $why = []; $bad = [];
        $skills = self::tokens(implode(' ', [$p->skills, $p->headline])); $need = self::tokens(implode(' ', [$j->skills, $j->title, $j->category]));
        $hit = array_values(array_intersect($skills, $need));
        if ($need) { $r = count($hit) / max(1, min(count($need), 6)); $score += min(35, $r * 35); if ($hit) $why[] = 'Skills match: ' . implode(', ', array_slice($hit, 0, 4)); else $bad[] = 'Skills do not appear to match'; }
        if ($p->category && $j->category && mb_strtolower($p->category) === mb_strtolower($j->category)) { $score += 8; $why[] = 'Your preferred category'; }
        $exp = (int) $p->experience_years;
        if ($j->exp_min !== null && $exp < (int) $j->exp_min) { $score -= 10; $bad[] = 'Kam experience (' . $j->exp_min . '+ years needed)'; } elseif ($j->exp_max !== null && $exp > (int) $j->exp_max + 3) { $score -= 4; } else { $score += 10; $why[] = 'Experience matches'; }
        $cities = array_map('mb_strtolower', array_filter(array_merge([$p->city], preg_split('/\s*,\s*/', (string) $p->preferred_cities))));
        if ($j->work_mode === 'remote') { $score += 6; $why[] = 'Work from home'; } elseif ($j->city && in_array(mb_strtolower($j->city), $cities, true)) { $score += 10; $why[] = 'Your city (' . $j->city . ') me'; } elseif ($j->city) { $score -= 5; $bad[] = 'Dusre sheher me (' . $j->city . ')'; }
        if ($p->expected_salary_num && $j->salary_max) { if ($j->salary_max >= $p->expected_salary_num) { $score += 8; $why[] = 'Salary expectation can be met'; } else { $score -= 6; $bad[] = 'Below the salary expectation'; } }
        if ($p->job_type && $j->job_type === $p->job_type) { $score += 4; }
        if ($p->education && $j->education && mb_strtolower($p->education) === mb_strtolower($j->education)) { $score += 4; $why[] = 'Education match'; }
        if ($need && ! $hit) $score = min($score, 42);          // nothing in common with the role: never a recommendation
        return ['score' => (int) max(1, min(99, round($score))), 'reasons' => array_slice($why, 0, 5), 'concerns' => array_slice($bad, 0, 3)];
    }

    public function draftJob(array $d): string
    {
        $title = $d['title'] ?? 'Role'; $skills = trim((string) ($d['skills'] ?? '')); $city = $d['city'] ?? null; $co = $d['company'] ?? null;
        $fallback = ($co ? "$co ko" : 'Hamein') . " $title is needed" . ($city ? " ($city)" : '') . ".\n\nResponsibilities:\n- Handle the daily work of the role\n- Coordinate with the team and management\n- Complete work on time and report\n\nRequirements:\n" . ($skills ? "- Skills: $skills\n" : '') . (! empty($d['experience']) ? '- Experience: ' . $d['experience'] . "\n" : '') . "- Good communication and honesty\n\nInterested candidates, please apply from the app.";
        return $this->llm('Write a clear job description (responsibilities, requirements) in simple Hinglish (Roman script), max 120 words. Use only the facts given.', json_encode($d, JSON_UNESCAPED_UNICODE), 400) ?: $fallback;
    }

    // ---------------- academy ----------------

    /** Short-video course pack from a topic: lesson titles, 60-second narration scripts, summaries and MCQs. Rule-based skeleton when AI is off. */
    public function draftCourse(string $topic, string $segment = 'general', int $lessons = 5): array
    {
        $lessons = max(1, min(10, $lessons));
        $json = $this->llm('You write short-video training courses for the Maheshwari community in simple Hinglish (Roman script). Reply ONLY with JSON: {"description":"...","lessons":[{"title":"...","summary":"1-2 sentences","script":"60-second narration"}],"questions":[{"question":"...","options":["a","b","c","d"],"correct":0,"explanation":"..."}]}. Exactly '
            . $lessons . ' lessons and ' . ($lessons * 2) . ' multiple-choice questions. Facts must be general and safe; no medical, legal or investment advice.', json_encode(['topic' => $topic, 'audience' => $segment]), 3500);
        if ($json && preg_match('/\{.*\}/s', $json, $m) && ($d = json_decode($m[0], true)) && ! empty($d['lessons'])) {
            $d['questions'] = array_values(array_filter($d['questions'] ?? [], fn ($q) => ! empty($q['question']) && is_array($q['options'] ?? null) && count($q['options']) >= 2 && isset($q['correct']) && isset($q['options'][$q['correct']])));
            $d['ai'] = true;
            return $d;
        }
        $out = ['ai' => false, 'description' => "Short video course: $topic", 'lessons' => [], 'questions' => []];
        foreach (['Introduction', 'Basics', 'Common mistakes', 'Practical tips', 'Summary and next steps'] as $i => $t) {
            if ($i >= $lessons) break;
            $out['lessons'][] = ['title' => "$topic - $t", 'summary' => "$t of $topic (draft - edit before publishing).", 'script' => "Hello! Today we will look at $topic - '$t'. (Write the script here.)"];
        }
        return $out;
    }
}
