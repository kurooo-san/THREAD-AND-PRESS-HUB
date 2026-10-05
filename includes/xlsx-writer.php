<?php
/**
 * Minimal .xlsx writer (ZipArchive + hand-written SpreadsheetML), so the sales
 * report opens in Excel already formatted without adding a library.
 *
 * $sheets = [[
 *   'name'   => 'Orders',
 *   'widths' => [10, 18, ...],          // column widths in characters
 *   'rows'   => [[cell, cell, ...], ...],
 *   'freeze' => 5,                      // rows kept visible when scrolling (optional)
 *   'filter' => 'A5:L40',               // header + data range for the filter arrows (optional)
 * ], ...]
 *
 * A cell is null (empty), a string, a number, or
 * ['v' => value, 's' => style, 'f' => formula] with style one of the keys of XLSX_STYLES.
 */

const XLSX_STYLES = [
    ''         => 0,
    'title'    => 1,
    'header'   => 2,
    'money'    => 3,
    'datetime' => 4,
    'bold'     => 5,
    'totalMoney' => 6,
    'total'    => 7,
    'int'      => 8,
    'date'     => 9,
    'totalInt' => 10,
    'muted'    => 11,
];

/** 0 -> A, 25 -> Z, 26 -> AA */
function xlsxCol(int $i): string
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
        $s = chr(65 + ($i - 1) % 26) . $s;
    }
    return $s;
}

/** 'Y-m-d H:i:s' (local time) -> Excel date serial. */
function xlsxDate(string $value): float
{
    $d = new DateTime($value, new DateTimeZone('UTC'));
    return $d->getTimestamp() / 86400 + 25569;
}

function xlsxEsc(string $s): string
{
    // Control characters other than tab/newline make the file unreadable.
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsxCellXml(string $ref, $cell): string
{
    if ($cell === null) return '';
    $style = 0;
    $formula = null;
    if (is_array($cell)) {
        $style   = XLSX_STYLES[$cell['s'] ?? ''] ?? 0;
        $formula = $cell['f'] ?? null;
        $cell    = $cell['v'] ?? null;
    }
    $s = $style ? ' s="' . $style . '"' : '';
    if ($formula !== null) {
        return '<c r="' . $ref . '"' . $s . '><f>' . xlsxEsc($formula) . '</f></c>';
    }
    if (is_int($cell) || is_float($cell)) {
        return '<c r="' . $ref . '"' . $s . '><v>' . $cell . '</v></c>';
    }
    if ($cell === null || $cell === '') {
        return $style ? '<c r="' . $ref . '"' . $s . '/>' : '';
    }
    return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . xlsxEsc((string)$cell) . '</t></is></c>';
}

function xlsxSheetXml(array $sheet): string
{
    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $x .= '<sheetViews><sheetView workbookViewId="0">';
    if (!empty($sheet['freeze'])) {
        $n = (int)$sheet['freeze'];
        $x .= '<pane ySplit="' . $n . '" topLeftCell="A' . ($n + 1) . '" activePane="bottomLeft" state="frozen"/>';
    }
    $x .= '</sheetView></sheetViews>';
    if (!empty($sheet['widths'])) {
        $x .= '<cols>';
        foreach ($sheet['widths'] as $i => $w) {
            $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $x .= '</cols>';
    }
    $x .= '<sheetData>';
    foreach ($sheet['rows'] as $r => $row) {
        $x .= '<row r="' . ($r + 1) . '">';
        foreach (array_values($row) as $c => $cell) {
            $x .= xlsxCellXml(xlsxCol($c) . ($r + 1), $cell);
        }
        $x .= '</row>';
    }
    $x .= '</sheetData>';
    if (!empty($sheet['filter'])) {
        $x .= '<autoFilter ref="' . $sheet['filter'] . '"/>';
    }
    return $x . '</worksheet>';
}

function xlsxStylesXml(): string
{
    $peso = '&quot;₱&quot;#,##0.00';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="3">'
        . '<numFmt numFmtId="164" formatCode="' . $peso . '"/>'
        . '<numFmt numFmtId="165" formatCode="mmm d, yyyy h:mm AM/PM"/>'
        . '<numFmt numFmtId="166" formatCode="ddd, mmm d, yyyy"/>'
        . '</numFmts>'
        . '<fonts count="5">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="16"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><i/><sz val="10"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="4">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F2937"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left/><right/><top style="thin"><color rgb="FF9CA3AF"/></top><bottom/><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="12">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                          // 0 default
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                            // 1 title
        . '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'              // 2 header
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                  // 3 money
        . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                  // 4 datetime
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                            // 5 bold
        . '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>' // 6 total money
        . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'                         // 7 total label
        . '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                    // 8 int
        . '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                  // 9 date
        . '<xf numFmtId="3" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'   // 10 total int
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                            // 11 muted note
        . '</cellXfs>'
        . '</styleSheet>';
}

/** Builds the workbook into a temp file and returns its path, or null when zip is unavailable. */
function xlsxBuild(array $sheets): ?string
{
    if (!class_exists('ZipArchive')) return null;
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) return null;

    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wbSheets = $wbRels = $names = '';
    foreach (array_values($sheets) as $i => $sheet) {
        $n = $i + 1;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wbSheets .= '<sheet name="' . xlsxEsc($sheet['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        if (!empty($sheet['filter'])) {
            [$a, $b] = explode(':', $sheet['filter']);
            $abs = function ($ref) {
                preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
                return '$' . $m[1] . '$' . $m[2];
            };
            $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\''
                . xlsxEsc($sheet['name']) . '\'!' . $abs($a) . ':' . $abs($b) . '</definedName>';
        }
        $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', xlsxSheetXml($sheet));
    }
    $ct .= '</Types>';
    $wbRels .= '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $zip->addFromString('[Content_Types].xml', $ct);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $wbSheets . '</sheets>'
        . ($names ? '<definedNames>' . $names . '</definedNames>' : '')
        . '</workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');
    $zip->addFromString('xl/styles.xml', xlsxStylesXml());
    $zip->close();
    return $path;
}
