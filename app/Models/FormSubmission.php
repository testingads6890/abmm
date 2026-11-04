<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $guarded = [];
    protected $casts = ['data' => 'array', 'decided_at' => 'datetime'];

    public function form() { return $this->belongsTo(FormDefinition::class, 'form_id'); }
    public function payment() { return $this->belongsTo(Payment::class); }
}
