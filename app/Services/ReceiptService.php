<?php

namespace App\Services;

use App\Models\{Payment, Receipt, Setting, UserNotification};
use App\Services\Messaging\MessageService;

/** PDF receipt for every paid payment; delivered inside the app (download URL), by e-mail and on WhatsApp. */
class ReceiptService
{
    public function __construct(private MessageService $msg) {}

    public function issue(Payment $p): Receipt
    {
        $r = Receipt::firstOrCreate(['payment_id' => $p->id], ['number' => 'TMP' . uniqid()]);
        if (str_starts_with($r->number, 'TMP')) $r->update(['number' => sprintf('RCT-%s-%06d', now()->format('Y'), $r->id)]);
        $this->render($r->fresh(), $p);
        return $r->fresh();
    }

    private function render(Receipt $r, Payment $p): void
    {
        abort_unless(class_exists(\Mpdf\Mpdf::class), 501, 'composer require mpdf/mpdf');
        $dir = storage_path('app/private/receipts'); if (! is_dir($dir)) mkdir($dir, 0775, true);
        $tmp = storage_path('app/mpdf'); if (! is_dir($tmp)) mkdir($tmp, 0775, true);
        $org = Setting::get('org_name', 'Akhil Bharatvarshiya Maheshwari Mahasabha');
        $addr = Setting::get('contact_address', '');
        $cur = $p->currency; $f = fn ($n) => number_format((float) $n, 2);
        $rows = [['Receipt no.', $r->number], ['Payment ref.', $p->number], ['Date', ($p->paid_at ?? now())->format('d M Y, h:i A')], ['Received from', $p->payer_name ?: '-'],
            ['Towards', ucfirst($p->purpose) . ($p->meta['title'] ?? null ? ' - ' . $p->meta['title'] : '')], ['Gateway', ucfirst($p->gateway) . ($p->gateway_payment_id ? " ({$p->gateway_payment_id})" : '')]];
        $html = '<style>body{font-family:dejavusans;font-size:10pt;color:#222}td{padding:5px 6px;border-bottom:0.3pt solid #ddd}.h{color:#1b1f4b;font-size:15pt}.tot td{font-weight:bold;font-size:12pt;border-top:1pt solid #1b1f4b}</style>'
            . '<div class="h"><b>' . e($org) . '</b></div><div style="font-size:8.5pt;color:#555">' . e($addr) . '</div><hr><h3>PAYMENT RECEIPT</h3><table width="100%">';
        foreach ($rows as [$k, $v]) $html .= '<tr><td width="35%"><b>' . e($k) . '</b></td><td>' . e((string) $v) . '</td></tr>';
        $html .= '</table><br><table width="100%">';
        if ($p->discount > 0) $html .= '<tr><td>Discount' . ($p->coupon ? ' (' . e($p->coupon) . ')' : '') . '</td><td align="right">- ' . $cur . ' ' . $f($p->discount) . '</td></tr>';
        if ($p->tax_amount > 0) $html .= '<tr><td>Includes tax</td><td align="right">' . $cur . ' ' . $f($p->tax_amount) . '</td></tr>';
        $html .= '<tr class="tot"><td>Amount paid</td><td align="right">' . $cur . ' ' . $f($p->amount) . '</td></tr></table>'
            . '<p style="font-size:8pt;color:#666;margin-top:24px">This is a computer generated receipt and does not require a signature.</p>';
        $m = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A5', 'tempDir' => $tmp, 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $m->SetTitle($r->number); $m->WriteHTML($html);
        $path = "receipts/{$r->number}.pdf";
        $m->Output(storage_path("app/private/$path"), \Mpdf\Output\Destination::FILE);
        $r->update(['path' => $path]);
    }

    public function absolute(Receipt $r): string { return storage_path('app/private/' . $r->path); }

    public function issueAndDeliver(Payment $p): Receipt
    {
        $r = $this->issue($p);
        $file = $this->absolute($r);
        // 1) inside the app
        if ($p->user_id) UserNotification::create(['user_id' => $p->user_id, 'title' => "Payment receipt {$r->number} ({$p->currency} " . number_format($p->amount, 2) . ')', 'url' => null]);
        // 2) e-mail
        if ($p->payer_email && $this->msg->email($p->payer_email, "Receipt {$r->number} - " . Setting::get('org_name', 'ABMM'), "Thank you. Your payment {$p->number} of {$p->currency} " . number_format($p->amount, 2) . " is received. Receipt attached.", $file, "{$r->number}.pdf", $p->family_id)) $r->update(['emailed_at' => now()]);
        // 3) WhatsApp (utility template with the PDF as header document)
        if ($p->payer_mobile && $this->msg->whatsappConfigured()) {
            $media = $this->msg->whatsappUpload($file);
            if ($this->msg->whatsapp($p->payer_mobile, (string) config('abmm.whatsapp.receipt_template'), [$p->payer_name ?: 'Member', $p->currency . ' ' . number_format($p->amount, 2), $r->number], 'utility', $media, "{$r->number}.pdf", $p->family_id)) $r->update(['whatsapped_at' => now()]);
        }
        return $r->fresh();
    }
}
