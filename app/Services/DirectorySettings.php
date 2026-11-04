<?php

namespace App\Services;

use App\Models\Setting;

/** Super Admin switch for the member Directory: global on/off and per-pradesh off list. */
class DirectorySettings
{
    public static function offPradesh(): array
    {
        $v = json_decode((string) Setting::get('dir_off_pradesh', '[]'), true);
        return is_array($v) ? $v : [];
    }

    public static function enabled(?string $pradesh): bool
    {
        if (Setting::get('dir_on', '1') !== '1') return false;
        return ! $pradesh || ! in_array($pradesh, self::offPradesh(), true);
    }
}
