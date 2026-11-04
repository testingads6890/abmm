<?php

namespace App\Models;

use App\Models\Concerns\{HasArea, StampsGeo};
use Illuminate\Database\Eloquent\Model;

class HelpRequest extends Model
{
    use HasArea, StampsGeo;
    protected static array $areaColumns = ['pradesh', 'district', 'country', 'city', 'zone'];

    public const TYPES = ['education' => 'Education', 'medical' => 'Medical', 'scholarship' => 'Scholarship', 'other' => 'Other'];
    public const STATUSES = ['submitted' => 'Submitted', 'in_review' => 'In review', 'approved' => 'Approved', 'forwarded' => 'Forwarded', 'rejected' => 'Rejected', 'closed' => 'Closed'];

    protected $guarded = [];
    protected $casts = ['documents' => 'array', 'amount' => 'decimal:2', 'decided_at' => 'datetime'];

    public function member() { return $this->belongsTo(Member::class)->withoutGlobalScopes(); }
    public function user() { return $this->belongsTo(User::class); }
}
