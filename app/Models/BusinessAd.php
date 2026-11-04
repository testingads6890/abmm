<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessAd extends Model
{
    protected $guarded = [];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'target' => 'array'];
    public function business() { return $this->belongsTo(Business::class); }
}
