<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'float'];
    public function payment() { return $this->belongsTo(Payment::class); }
}
