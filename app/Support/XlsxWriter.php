<?php

namespace App\Support;

use ZipArchive;

/**
 * Dependency-free streaming .xlsx writer (ZipArchive + inline strings).
 * Rows are streamed to a temp file, so 1 lakh+ rows do not blow memory.
 * Header row: bold, frozen, auto-filtered. Optional title line above it.
 */
class XlsxWriter
{
    /** @param iterable<array<int,scalar|null>> $rows  @return string path of the finished .xlsx (caller deletes it) */
    public static function toFile(string $sheetName, array $headers, iterable $rows, ?string $title = null): string
    {
        $headers = array_values($headers);
        $nCols = count($headers);
        $widths = array_map(fn ($h) => max(10, min(60, mb_strlen((string) $h) + 4)), $headers);

        $body = tempnam(sys_get_temp_dir(), 'xb');
        $bh = fopen($body, 'w');
        $r = 0;
        if ($title) { $r = 1; fwrite($bh, '<row r="1">' . self::cell('A1', $title, 2) . '</row>'); $r = 2; }
        $r++;
        $headerRow = $r;
        $cells = '';
        foreach ($headers as $i => $h) $cells .= self::cell(self::col($i) . $r, $h, 1);
        fwrite($bh, "<row r=\"$r\">$cells</row>");

        $buf = '';
        $n = 0;
        foreach ($rows as $row) {
            $r++;
            $cells = '';
            foreach (array_values($row) as $i => $v) {
                $cells .= self::cell(self::col($i) . $r, $v, 0);
                if ($n < 300 && $v !== null) {
                    $longest = max(array_map('mb_strlen', explode("\n", (string) $v)) ?: [0]);
                    $widths[$i] = max($widths[$i] ?? 10, min(60, $longest + 2));
                }
            }
            $n++;
            $buf .= "<row r=\"$r\">$cells</row>";
            if (strlen($buf) > 524288) { fwrite($bh, $buf); $buf = ''; }
        }
        fwrite($bh, $buf);
        fclose($bh);

        $sheet = tempnam(sys_get_temp_dir(), 'xs');
        $sh = fopen($sheet, 'w');
        fwrite($sh, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/><cols>');
        for ($i = 0; $i < $nCols; $i++) fwrite($sh, '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . ($widths[$i] ?? 12) . '" customWidth="1"/>');
        fwrite($sh, '</cols><sheetData>');
        $in = fopen($body, 'r');
        stream_copy_to_stream($in, $sh);
        fclose($in);
        fwrite($sh, '</sheetData>' . ($nCols ? '<autoFilter ref="A' . $headerRow . ':' . self::col($nCols - 1) . max($r, $headerRow) . '"/>' : '') . '</worksheet>');
        fclose($sh);
        unlink($body);

        $name = htmlspecialchars(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $sheetName) ?: 'Sheet1', 0, 31), ENT_XML1 | ENT_QUOTES);
        $path = tempnam(sys_get_temp_dir(), 'xl') . '.xlsx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1B1F4B"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($sheet);
        return $path;
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    }

    private static function cell(string $ref, $v, int $style): string
    {
        $s = $style ? ' s="' . $style . '"' : '';
        if ($v === null || $v === '') return "<c r=\"$ref\"$s/>";
        if (is_int($v) || is_float($v)) return "<c r=\"$ref\"$s><v>$v</v></c>";
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v);
        return "<c r=\"$ref\"$s t=\"inlineStr\"><is><t xml:space=\"preserve\">" . htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
    }
}
