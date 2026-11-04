<?php

namespace App\Models;

use App\Support\CustomForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Trust extends Model
{
    protected $guarded = [];
    protected $hidden = ['aadhar_enc'];
    protected $casts = ['extra' => 'array', 'dob' => 'date'];

    public function submissions() { return $this->hasMany(TrustSubmission::class); }

    public function setAadharAttribute(?string $v): void
    {
        $this->attributes['aadhar_enc'] = $v ? Crypt::encryptString($v) : null;
    }

    public function aadharPlain(): ?string
    {
        try { return $this->aadhar_enc ? Crypt::decryptString($this->aadhar_enc) : null; } catch (\Throwable) { return null; }
    }

    /** XXXX XXXX 1234 — the full number is never shown in lists, PDFs or e-mails. */
    public function aadharMasked(): string
    {
        $a = $this->aadharPlain();
        return $a ? 'XXXX XXXX ' . substr($a, -4) : '';
    }

    /** Label => value pairs used for the PDF and the department e-mail (custom fields only for this trust's category). */
    public function details(): array
    {
        return array_filter([
            'Category' => $this->trust_category, 'Name of Trust' => $this->trust_name,
            'Name' => $this->name, 'Email ID' => $this->email, 'Date of Birth' => $this->dob?->format('d-M-Y'), 'Mobile Number' => $this->contact,
            'Gender' => $this->gender, 'Marital Status' => $this->marital_status, 'City' => $this->city, 'Aadhar Card' => $this->aadharMasked(),
            'Address' => $this->address, 'Country' => $this->country, 'Govt. State' => $this->state, 'District' => $this->district, 'Pincode' => $this->pincode,
        ], fn ($v) => $v !== null && $v !== '') + CustomForm::display('trust', $this->extra, false, $this->trust_category);
    }
}
