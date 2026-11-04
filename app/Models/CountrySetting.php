<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-country currency, gateway, tax and language. Falls back to India defaults when a country has no row. */
class CountrySetting extends Model
{
    protected $guarded = [];
    protected $casts = ['tax_rate' => 'float', 'is_active' => 'boolean'];

    public static function forCountry(?string $country): self
    {
        $row = $country ? static::whereRaw('LOWER(country) = ?', [mb_strtolower(trim($country))])->first() : null;
        return $row ?: new static(['country' => $country ?: 'INDIA', 'dial_code' => '+91', 'currency' => 'INR', 'gateway' => 'razorpay',
            'tax_name' => 'GST', 'tax_rate' => 18, 'language' => 'en', 'timezone' => 'Asia/Kolkata', 'is_active' => true]);
    }
}
