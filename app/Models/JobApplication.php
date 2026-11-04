<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $guarded = [];

    public function job() { return $this->belongsTo(JobPost::class, 'job_id')->withoutGlobalScopes(); }
    public function user() { return $this->belongsTo(User::class); }
    public function profile() { return $this->hasOne(JobProfile::class, 'user_id', 'user_id'); }
}
