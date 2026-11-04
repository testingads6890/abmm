<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Unified ledger row: every rupee/dollar (subscription, form fee, donation, ad) goes through here. */
class Payment extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'float', 'tax_amount' => 'float', 'discount' => 'float', 'refunded' => 'float', 'paid_at' => 'datetime', 'reconciled' => 'boolean', 'meta' => 'array'];

    public function refunds() { return $this->hasMany(PaymentRefund::class); }
    public function receipt() { return $this->hasOne(Receipt::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function net(): float { return round($this->amount - $this->refunded, 2); }
}
