<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $guarded = [];
    protected $casts = ['event_date' => 'date', 'is_public' => 'boolean'];
}
