<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** City-wise zone (Zone 1, Zone 2 ...) under Country > State > District > City. */
class Zone extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q) { return $q->where('is_active', true); }
}
