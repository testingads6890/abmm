<?php

namespace App\Support;

use App\Models\Lookup;

/**
 * Salary / income bands managed in Master Data. The band name itself carries the range, e.g.
 *   "2-5 Lakh"  "Below 2 Lakh"  "50 Lakh and above"  "10k-20k"  "60k-1 Lakh"  "1 Lakh and above".
 * parse() turns a name into [min, max] in rupees (null = open ended).
 */
class SalaryBand
{
    public static function parse(string $name): ?array
    {
        $s = strtolower(str_replace(',', '', $name));
        if (! preg_match_all('/(\d+(?:\.\d+)?)\s*(k|lakhs?|lacs?|lac|cr|crores?)?/', $s, $m, PREG_SET_ORDER)) return null;
        $unit = null;
        foreach (array_reverse($m) as $t) { if (! empty($t[2])) { $unit = $t[2]; break; } }   // "2-5 Lakh": first number borrows the unit of the last one
        $val = fn (array $t) => (float) $t[1] * self::mult($t[2] ?? $unit);
        $a = $val($m[0]);
        $b = isset($m[1]) ? $val($m[1]) : null;
        if (preg_match('/below|under|upto|up to|less/', $s)) return [0, (int) $a];
        if (preg_match('/above|plus|\+|more|over/', $s)) return [(int) $a, null];
        return $b !== null ? [(int) min($a, $b), (int) max($a, $b)] : [(int) $a, (int) $a];
    }

    private static function mult(?string $u): int
    {
        return match (true) { $u === null => 1, $u === 'k' => 1000, str_starts_with($u, 'cr') => 10000000, default => 100000 };
    }

    /** Active bands of a Master Data type as [['label', 'min', 'max'], ...] (unparsable names are skipped). */
    public static function bands(string $type): array
    {
        return cache()->remember("salary_bands.$type", 300, function () use ($type) {
            return Lookup::ofType($type)->active()->orderBy('sort')->orderBy('name')->pluck('name')->map(function ($n) {
                $r = self::parse($n);
                return $r ? ['label' => $n, 'min' => $r[0], 'max' => $r[1]] : null;
            })->filter()->values()->all();
        });
    }

    /** Human readable "₹5.5 Lakh" / "₹25,000" for display. */
    public static function money(?int $v, bool $monthly = false): string
    {
        if ($v === null) return '—';
        if ($v >= 10000000) return '₹' . rtrim(rtrim(number_format($v / 10000000, 2), '0'), '.') . ' Cr';
        if ($v >= 100000 && ! $monthly) return '₹' . rtrim(rtrim(number_format($v / 100000, 2), '0'), '.') . ' Lakh';
        return '₹' . number_format($v);
    }
}
