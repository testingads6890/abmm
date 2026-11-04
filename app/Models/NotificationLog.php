<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $guarded = [];
    protected $casts = ['filters' => 'array'];
    protected $table = 'notifications_log';
    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
}
