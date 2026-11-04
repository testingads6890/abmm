<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $guarded = [];
    protected $casts = ['warnings' => 'array', 'undone_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
