<?php

namespace App\Models;

use App\Models\Concerns\{HasArea, StampsGeo};
use Illuminate\Database\Eloquent\Model;

class JobPost extends Model
{
    use HasArea, StampsGeo;
    protected static array $areaColumns = ['pradesh', 'district', 'country', 'city', 'zone'];
    protected $table = 'jobs_board';

    public const TYPES = ['full_time' => 'Full time', 'part_time' => 'Part time', 'internship' => 'Internship', 'freelance' => 'Freelance'];
    public const STATUSES = ['unpaid' => 'Awaiting payment', 'pending' => 'Pending', 'live' => 'Live', 'rejected' => 'Rejected', 'closed' => 'Closed'];
    public const APP_STATUSES = ['applied' => 'Applied', 'shortlisted' => 'Shortlisted', 'hired' => 'Selected', 'rejected' => 'Not selected'];

    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'decided_at' => 'datetime', 'featured_until' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function applications() { return $this->hasMany(JobApplication::class, 'job_id'); }

    /** Approved and not yet expired. */
    public function isFeatured(): bool { return (bool) ($this->featured_until && $this->featured_until->isFuture()); }

    public function scopeLive($q)
    {
        return $q->where('status', 'live')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
