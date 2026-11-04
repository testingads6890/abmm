<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = [];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'auto_renew' => 'boolean', 'reminded_at' => 'datetime'];

    public function plan() { return $this->belongsTo(Plan::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function family() { return $this->belongsTo(Family::class)->withoutGlobalScopes(); }
    public function isLive(): bool { return $this->status === 'active' && (! $this->ends_on || $this->ends_on->gte(today())); }
}
