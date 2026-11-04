<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Brand colours chosen in Admin > Settings (Primary and Secondary). Every shade used by the CSS is derived from the two
 * colours, and the result is printed as one :root block in the layouts, so a change shows on every page straight away.
 */
class Theme
{
    public const DEFAULT_PRIMARY = '#c62828';   // logo red
    public const DEFAULT_SECONDARY = '#5c1010'; // logo maroon

    /** The colours in use: the chosen Primary and Secondary plus their derived shades. */
    public static function palette(): array
    {
        $p = self::valid(Setting::get('theme_primary')) ?? self::DEFAULT_PRIMARY;
        $s = self::valid(Setting::get('theme_secondary')) ?? self::DEFAULT_SECONDARY;
        return [
            'primary' => $p, 'primary_dark' => self::mix($p, '#000000', .18), 'primary_light' => self::mix($p, '#ffffff', .88),
            'secondary' => $s, 'secondary_light' => self::mix($s, '#ffffff', .15), 'secondary_lighter' => self::mix($s, '#ffffff', .92),
        ];
    }

    public static function css(): string
    {
        $c = self::palette();
        [$sr, $sg, $sb] = self::rgb($c['secondary']);

        return sprintf(
            ':root{--saffron:%s;--saffron-d:%s;--saffron-l:%s;--ink:%s;--ink-2:%s;--ink-l:%s;--shadow:0 10px 30px -12px rgba(%d,%d,%d,.25)}',
            $c['primary'], $c['primary_dark'], $c['primary_light'],
            $c['secondary'], $c['secondary_light'], $c['secondary_lighter'],
            $sr, $sg, $sb,
        );
    }

    /** A valid "#rrggbb" colour in lower case, or null. */
    public static function valid(?string $hex): ?string
    {
        return $hex && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : null;
    }

    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    /** Blend $a towards $b by $t (0 = $a, 1 = $b). */
    private static function mix(string $a, string $b, float $t): string
    {
        [$r1, $g1, $b1] = self::rgb($a);
        [$r2, $g2, $b2] = self::rgb($b);
        $f = fn (int $x, int $y) => (int) round($x + ($y - $x) * $t);
        return sprintf('#%02x%02x%02x', $f($r1, $r2), $f($g1, $g2), $f($b1, $b2));
    }
}
