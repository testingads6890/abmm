<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessReview extends Model
{
    protected $guarded = [];
    public function business() { return $this->belongsTo(Business::class); }
}
