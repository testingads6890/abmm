<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = [];
    protected $casts = ['value' => 'float', 'is_active' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date'];

    /** Returns the discount for a gross amount, or null when this coupon cannot be used. */
    public function discountFor(float $amount, ?string $audience = null): ?float
    {
        if (! $this->is_active) return null;
        if ($this->valid_from && now()->lt($this->valid_from->startOfDay())) return null;
        if ($this->valid_to && now()->gt($this->valid_to->endOfDay())) return null;
        if ($this->max_uses !== null && $this->used >= $this->max_uses) return null;
        if ($this->audience && $audience && $this->audience !== $audience) return null;
        $d = $this->type === 'flat' ? $this->value : round($amount * $this->value / 100, 2);
        return min($amount, max(0, $d));
    }
}
