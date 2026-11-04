<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormDefinition extends Model
{
    public const TYPES = ['text' => 'Text', 'textarea' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'select' => 'Dropdown', 'checkbox' => 'Yes / No', 'file' => 'File upload'];

    protected $guarded = [];
    protected $casts = ['fields' => 'array', 'fee' => 'float', 'needs_approval' => 'boolean', 'is_active' => 'boolean'];

    public function submissions() { return $this->hasMany(FormSubmission::class, 'form_id'); }
    public function scopeActive($q) { return $q->where('is_active', true); }
}
