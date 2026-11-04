<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingEntry extends Model
{
    protected $table = 'meeting_list';
    protected $guarded = [];
    protected $casts = ['meeting_date' => 'date'];

    public const COMMITTEES = [
        'kariyakarni_sabha' => 'a) Kariyakarni Sabha',
        'kariyakarni_mandal_sabha' => 'b) Kariyakarni Mandal Sabha',
        'both' => '3) Both',
    ];

    public const TYPES = [
        'regular' => 'Regular',
        'special' => 'Special',
        'emergency' => 'Emergency',
        'annual' => 'Annual',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
