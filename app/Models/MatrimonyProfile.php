<?php

namespace App\Models;

use App\Models\Concerns\HasArea;
use Illuminate\Database\Eloquent\Model;

class MatrimonyProfile extends Model
{
    use HasArea;

    protected static array $areaColumns = ['pradesh', 'district'];
    protected $guarded = [];
    protected $casts = ['extra' => 'array', 'dob' => 'date', 'registered_at' => 'datetime', 'manglik' => 'boolean', 'paid_until' => 'date'];
    public function family() { return $this->belongsTo(Family::class); }
    public function getAgeAttribute(): int { return $this->dob->age; }
}
