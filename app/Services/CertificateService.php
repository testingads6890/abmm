<?php

namespace App\Services;

use App\Models\{Setting, UserNotification};
use Illuminate\Support\Facades\DB;

/** Landscape A4 PDF certificates (star of the month, event attendance, achievements). One per (user, type, ref). */
class CertificateService
{
    public function issue(int $userId, ?int $familyId, string $type, string $title, ?string $detail, string $ref = ''): ?int
    {
        abort_unless(class_exists(\Mpdf\Mpdf::class), 501, 'composer require mpdf/mpdf');
        $name = DB::table('users')->where('id', $userId)->value('name');
        if (! $name) return null;
        $id = DB::table('certificates')->insertOrIgnore(['number' => 'TMP' . uniqid(), 'user_id' => $userId, 'family_id' => $familyId, 'type' => $type, 'title' => $title, 'detail' => $detail, 'ref' => $ref, 'created_at' => now(), 'updated_at' => now()]);
        if (! $id) return null;
        $row = DB::table('certificates')->where(['user_id' => $userId, 'type' => $type, 'ref' => $ref])->first();
        $no = sprintf('CERT-%s-%06d', now()->format('Y'), $row->id);
        $dir = storage_path('app/private/certificates'); if (! is_dir($dir)) mkdir($dir, 0775, true);
        $tmp = storage_path('app/mpdf'); if (! is_dir($tmp)) mkdir($tmp, 0775, true);
        $org = Setting::get('org_name', 'Akhil Bharatvarshiya Maheshwari Mahasabha');
        $html = '<style>body{font-family:dejavusans;text-align:center;color:#1b1f4b}.b{border:6px double #e8772e;padding:30px}h1{font-size:30pt;color:#e8772e;margin:6px}.n{font-size:26pt;border-bottom:1px solid #999;display:inline-block;padding:0 30px;margin:14px}</style>'
            . '<div class="b"><div style="font-size:16pt"><b>' . e($org) . '</b></div><h1>CERTIFICATE</h1><div>of Recognition</div><p>This is proudly presented to</p><div class="n">' . e($name) . '</div>'
            . '<p style="font-size:14pt"><b>' . e($title) . '</b></p><p>' . e((string) $detail) . '</p><p style="font-size:9pt;margin-top:30px">Certificate no. ' . $no . ' · ' . now()->format('d M Y') . '</p></div>';
        $m = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'tempDir' => $tmp, 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $m->WriteHTML($html);
        $m->Output("$dir/$no.pdf", \Mpdf\Output\Destination::FILE);
        DB::table('certificates')->where('id', $row->id)->update(['number' => $no, 'path' => "certificates/$no.pdf"]);
        UserNotification::create(['user_id' => $userId, 'title' => "Aapko certificate mila: $title"]);
        return $row->id;
    }
}
