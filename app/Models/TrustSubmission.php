<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustSubmission extends Model
{
    protected $guarded = [];
    protected $casts = ['paid_at' => 'datetime', 'submitted_at' => 'datetime', 'data' => 'array'];

    public function trust() { return $this->belongsTo(Trust::class); }
    public function department() { return $this->belongsTo(Department::class); }
}
