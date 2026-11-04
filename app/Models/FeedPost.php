<?php

namespace App\Models;

use App\Models\Concerns\{HasArea, StampsGeo};
use App\Services\FcmService;
use Illuminate\Database\Eloquent\Model;

class FeedPost extends Model
{
    use HasArea, StampsGeo;
    protected static array $areaColumns = ['pradesh', 'district', 'country', 'city', 'zone'];

    public const CATEGORIES = [
        'college_rank' => 'College / University rank', 'school_rank' => 'School / Board result', 'govt_exam' => 'Government exam selection',
        'sports' => 'Sports', 'arts' => 'Arts & culture', 'business' => 'Business / Startup', 'other' => 'Other achievement',
    ];
    public const TYPES = ['achievement' => 'Achievement', 'news' => 'News', 'condolence' => 'Condolence'];
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'changes' => 'Changes needed', 'rejected' => 'Rejected'];

    protected $guarded = [];
    protected $casts = ['pinned' => 'boolean', 'proof_verified' => 'boolean', 'publish_at' => 'datetime', 'expires_at' => 'datetime', 'reviewed_at' => 'datetime'];

    /** Approved, already published, not expired. */
    public function scopeLive($q)
    {
        return $q->where('status', 'approved')->where(fn ($w) => $w->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public const TOPPER_CATEGORIES = ['college_rank', 'school_rank', 'govt_exam'];

    /** FCM topic for an audience: district | state (falls back to pradesh for admin posts) | all. */
    public function topicFor(string $aud): ?string
    {
        return match ($aud) {
            'district' => $this->district ? FcmService::topicFor('district', $this->district) : null,
            'state' => $this->state ? FcmService::topicFor('state', $this->state) : ($this->pradesh ? FcmService::topicFor('pradesh', $this->pradesh) : null),
            'all' => FcmService::topicFor('country', null),
            default => null,
        };
    }

    /** Push to a topic. null = nothing to do (no audience / FCM not configured), true = sent, false = failed. */
    public function pushToAudience(string $aud, string $title, string $body): ?bool
    {
        $fcm = app(FcmService::class);
        $topic = $this->topicFor($aud);
        if (! $topic || ! $fcm->configured()) return null;
        try { $fcm->sendToTopic($topic, $title, $body, ['type' => 'feed', 'id' => $this->id]); return true; }
        catch (\Throwable $e) { report($e); return false; }
    }

    public function member() { return $this->belongsTo(Member::class)->withoutGlobalScopes(); }
    public function submitter() { return $this->belongsTo(User::class, 'user_id'); }
}
