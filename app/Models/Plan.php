<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    public const AUDIENCES = ['member' => 'Member / Family', 'business' => 'Business listing', 'bureau' => 'Marriage bureau / Parichay', 'vendor' => 'Event vendor',
        'advertiser' => 'Advertiser', 'institution' => 'Bhavan / Hotel / Hospital / Clinic / Organisation', 'training' => 'AI training & courses',
        'organiser' => 'Event organiser', 'recruiter' => 'Job recruiter'];
    public const PERIODS = ['monthly' => 1, 'quarterly' => 3, 'half_yearly' => 6, 'yearly' => 12, 'one_time' => 0];

    protected $guarded = [];
    protected $casts = ['features' => 'array', 'price' => 'float', 'is_active' => 'boolean'];

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function months(): int { return self::PERIODS[$this->period] ?? 0; }
}
