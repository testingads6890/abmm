<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $guarded = [];
    protected $casts = ['emailed_at' => 'datetime', 'whatsapped_at' => 'datetime'];
    public function payment() { return $this->belongsTo(Payment::class); }
}
