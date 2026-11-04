<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/** One entry point for Excel (.xlsx), CSV and PDF downloads. $rows is any iterable of plain arrays. */
class Exporter
{
    public const MAX_XLSX = 100000;
    public const MAX_PDF = 3000;

    public static function download(string $format, string $name, string $title, array $headers, iterable $rows): Response
    {
        $file = $name . '-' . now()->format('Ymd-His');
        return match ($format) {
            'xlsx' => response()->download(XlsxWriter::toFile($title, $headers, self::limit($rows, self::MAX_XLSX), $title), "$file.xlsx")->deleteFileAfterSend(true),
            'pdf' => self::pdf($file, $title, $headers, $rows),
            default => response()->streamDownload(function () use ($headers, $rows) {
                $o = fopen('php://output', 'w');
                fwrite($o, "\xEF\xBB\xBF");
                fputcsv($o, $headers);
                foreach (self::limit($rows, self::MAX_XLSX) as $row) fputcsv($o, array_map([self::class, 'safe'], $row));
                fclose($o);
            }, "$file.csv", ['Content-Type' => 'text/csv']),
        };
    }

    private static function pdf(string $file, string $title, array $headers, iterable $rows): Response
    {
        if (! class_exists(\Mpdf\Mpdf::class)) {
            abort(501, 'To enable PDF, run once: composer require mpdf/mpdf');
        }
        $rows = iterator_to_array(self::limit($rows, self::MAX_PDF + 1), false);
        $truncated = count($rows) > self::MAX_PDF;
        $rows = array_slice($rows, 0, self::MAX_PDF);
        $dir = storage_path('app/mpdf');
        if (! is_dir($dir)) mkdir($dir, 0775, true);

        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_top' => 22, 'margin_bottom' => 14, 'margin_left' => 10, 'margin_right' => 10,
            'default_font_size' => 8.5, 'tempDir' => $dir, 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $org = \App\Models\Setting::get('org_name', 'ABMM Sampark');
        $mpdf->SetTitle($title);
        $mpdf->SetHTMLHeader('<div style="border-bottom:1px solid #e8772e;padding-bottom:3px"><b style="font-size:12pt;color:#1b1f4b">' . e($org) . '</b><br><span style="font-size:9pt">' . e($title) . '</span></div>');
        $mpdf->SetHTMLFooter('<table width="100%" style="font-size:7pt;color:#666"><tr><td>' . now()->format('d-M-Y h:i A') . '</td><td align="right">Page {PAGENO} / {nb}</td></tr></table>');
        $html = '<style>table{border-collapse:collapse;width:100%}th{background:#1b1f4b;color:#fff;text-align:left;padding:4px;font-size:8pt}td{border-bottom:0.3pt solid #ccc;padding:3px 4px;vertical-align:top}</style><table repeat_header="1"><thead><tr>';
        foreach ($headers as $h) $html .= '<th>' . e($h) . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $v) $html .= '<td>' . nl2br(e((string) $v)) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>' . ($truncated ? '<p><i>Note: PDF shows only the first ' . self::MAX_PDF . ' rows. Use Excel for the full data.</i></p>' : '');
        $mpdf->WriteHTML($html);
        return response($mpdf->Output('', 'S'), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $file . '.pdf"']);
    }

    private static function limit(iterable $rows, int $max): \Generator
    {
        $i = 0;
        foreach ($rows as $r) { if ($i++ >= $max) return; yield $r; }
    }

    /** Neutralise spreadsheet formula injection (CSV only; xlsx uses inline strings). */
    public static function safe($v): string
    {
        $v = str_replace("\n", ' | ', (string) $v);
        return $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'" . $v : $v;
    }
}
