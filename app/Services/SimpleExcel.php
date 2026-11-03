<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

/** Reader/writer for standard .xlsx files without additional Composer packages. */
class SimpleExcel
{
    public static function read(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            $handle = fopen($path, 'rb');
            if (! $handle) throw new RuntimeException('Tidak dapat membaca CSV.');
            $first = fgets($handle);
            if ($first === false) { fclose($handle); return []; }
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            rewind($handle);
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                $rows[] = array_map(fn ($v) => trim((string) $v), $row);
                if (count($rows) > 2001) break;
            }
            fclose($handle);
            return $rows;
        }
        if ($extension !== 'xlsx') throw new RuntimeException('Gunakan file .xlsx atau .csv, bukan .xls lama.');
        if (! class_exists(ZipArchive::class) || ! function_exists('simplexml_load_string')) {
            throw new RuntimeException('Aktifkan ekstensi PHP zip dan SimpleXML di Laragon, lalu restart Apache.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('File Excel tidak valid.');
        try {
            $sheetName = 'xl/worksheets/sheet1.xml';
            $workbook = $zip->getFromName('xl/workbook.xml');
            if ($workbook !== false) {
                $wb = self::xml($workbook);
                $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $firstSheet = $wb->xpath('//m:sheets/m:sheet')[0] ?? null;
                if ($firstSheet) {
                    $attr = $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                    $relationId = (string) ($attr['id'] ?? '');
                    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
                    if ($rels !== false) {
                        $relationXml = self::xml($rels);
                        foreach ($relationXml->Relationship as $rel) {
                            if ((string) $rel['Id'] === $relationId) {
                                $target = ltrim((string) $rel['Target'], '/');
                                $sheetName = str_starts_with($target, 'xl/') ? $target : 'xl/'.ltrim($target, '/');
                                if (str_contains($sheetName, '..')) throw new RuntimeException('Lokasi worksheet tidak valid.');
                                break;
                            }
                        }
                    }
                }
            }
            $stat = $zip->statName($sheetName);
            if (! $stat || $stat['size'] > 12 * 1024 * 1024) throw new RuntimeException('Worksheet Excel terlalu besar atau tidak ditemukan.');
            $sheetContent = $zip->getFromName($sheetName);
            if ($sheetContent === false) throw new RuntimeException('Worksheet Excel tidak ditemukan.');
            $shared = [];
            $stringStat = $zip->statName('xl/sharedStrings.xml');
            if ($stringStat && $stringStat['size'] > 8 * 1024 * 1024) throw new RuntimeException('Tabel teks Excel terlalu besar.');
            $strings = $zip->getFromName('xl/sharedStrings.xml');
            if ($strings !== false) {
                if (strlen($strings) > 8 * 1024 * 1024) throw new RuntimeException('Tabel teks Excel terlalu besar.');
                $stringsXml = self::xml($strings);
                foreach ($stringsXml->si as $si) $shared[] = self::richText($si);
            }
            $xml = self::xml($sheetContent);
            $rows = [];
            foreach ($xml->sheetData->row as $row) {
                $rowNumber = (int) ($row['r'] ?? (count($rows) + 1));
                while (count($rows) < $rowNumber - 1 && count($rows) < 2001) $rows[] = [];
                $values = [];
                $index = 0;
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    if (preg_match('/^([A-Z]+)/i', $ref, $match)) {
                        $index = 0;
                        foreach (str_split(strtoupper($match[1])) as $letter) $index = $index * 26 + ord($letter) - 64;
                        $index--;
                    }
                    if ($index > 80) continue;
                    $type = (string) $cell['t'];
                    $value = $type === 's' ? ($shared[(int) $cell->v] ?? '') :
                        ($type === 'inlineStr' ? self::richText($cell->is) : (string) $cell->v);
                    $values[$index] = trim($value);
                    $index++;
                }
                $rows[] = $values ? array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values) : [];
                if (count($rows) > 2001) break;
            }
            return $rows;
        } finally {
            $zip->close();
        }
    }

    private static function xml(string $data): \SimpleXMLElement
    {
        $xml = simplexml_load_string($data, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) throw new RuntimeException('Struktur XML Excel tidak valid.');
        return $xml;
    }

    private static function richText(\SimpleXMLElement $node): string
    {
        $text = (string) $node->t;
        foreach ($node->r as $run) $text .= (string) $run->t;
        return $text;
    }

    public static function create(array $rows, string $filename): string
    {
        if (! class_exists(ZipArchive::class)) throw new RuntimeException('Aktifkan ekstensi PHP zip pada Laragon.');
        $path = tempnam(sys_get_temp_dir(), 'excel_');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) throw new RuntimeException('Gagal menyiapkan file Excel.');
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="16"/><sheetData>';
        foreach ($rows as $rowId => $row) {
            $sheet .= '<row r="'.($rowId+1).'">';
            foreach (array_values($row) as $column => $value) {
                $number = $column + 1;
                $letter = '';
                while ($number) { $number--; $letter = chr(65 + $number % 26).$letter; $number = intdiv($number, 26); }
                $clean = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sheet .= '<c r="'.$letter.($rowId+1).'" t="inlineStr"><is><t xml:space="preserve">'.$clean.'</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        return $path;
    }

    public static function download(array $rows, string $filename)
    {
        $file = self::create($rows, $filename);
        return response()->download($file, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Create an import template with database-backed dropdowns and cell validation.
     */
    public static function downloadScheduleTemplate(
        array $headers,
        array $prodis,
        array $rooms,
        string $filename
    ) {
        $file = self::createScheduleTemplate($headers, $prodis, $rooms);

        return response()->download($file, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public static function createScheduleTemplate(array $headers, array $prodis, array $rooms): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Aktifkan ekstensi PHP zip pada Laragon.');
        }

        $headers = array_values(array_slice($headers, 0, 9));
        if (count($headers) !== 9) {
            throw new RuntimeException('Template jadwal harus memiliki tepat 9 kolom.');
        }

        $cleanList = static fn (array $values): array => array_values(array_unique(array_filter(
            array_map(static fn ($value) => trim((string) $value), $values),
            static fn ($value) => $value !== ''
        )));

        $prodis = $cleanList($prodis);
        $rooms = $cleanList($rooms);
        if ($prodis === [] || $rooms === []) {
            throw new RuntimeException('Daftar prodi dan ruangan aktif harus tersedia sebelum membuat template.');
        }

        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $times = [];
        for ($minutes = 0; $minutes < 24 * 60; $minutes += 15) {
            $times[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        $path = tempnam(sys_get_temp_dir(), 'schedule_template_');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Gagal menyiapkan template Excel.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $prodiEnd = count($prodis) + 1;
        $roomEnd = count($rooms) + 1;
        $dayEnd = count($days) + 1;
        $timeEnd = count($times) + 1;
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Jadwal" sheetId="1" r:id="rId1"/><sheet name="Referensi" sheetId="2" state="hidden" r:id="rId2"/></sheets>'
            .'<definedNames>'
            .'<definedName name="DaftarProdi">Referensi!$A$2:$A$'.$prodiEnd.'</definedName>'
            .'<definedName name="DaftarRuangan">Referensi!$B$2:$B$'.$roomEnd.'</definedName>'
            .'<definedName name="DaftarHari">Referensi!$C$2:$C$'.$dayEnd.'</definedName>'
            .'<definedName name="DaftarJam">Referensi!$D$2:$D$'.$timeEnd.'</definedName>'
            .'</definedNames><calcPr calcId="191029"/></workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="hh:mm"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF4F46E5"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        $mainRows = '<row r="1" ht="24" customHeight="1">';
        foreach ($headers as $column => $header) {
            $mainRows .= self::inlineCell(self::columnLetter($column + 1).'1', $header, 1);
        }
        $mainRows .= '</row>';
        for ($row = 2; $row <= 501; $row++) {
            $mainRows .= '<row r="'.$row.'">'
                .'<c r="A'.$row.'"/><c r="B'.$row.'"/><c r="C'.$row.'"/><c r="D'.$row.'"/><c r="E'.$row.'"/>'
                .'<c r="F'.$row.'" s="3"/><c r="G'.$row.'" s="3"/><c r="H'.$row.'" s="2"/><c r="I'.$row.'" s="2"/>'
                .'</row>';
        }

        $validations = [
            self::listValidation('A2:A501', 'DaftarProdi', 'Prodi', 'Pilih salah satu prodi aktif.'),
            self::textValidation('B2:B501', 255, 'Mata kuliah wajib diisi.'),
            self::textValidation('C2:C501', 255, 'Nama dosen wajib diisi.'),
            self::listValidation('D2:D501', 'DaftarRuangan', 'Ruangan', 'Pilih kode ruangan aktif.'),
            self::listValidation('E2:E501', 'DaftarHari', 'Hari', 'Pilih hari Senin sampai Sabtu.'),
            self::listValidation('F2:F501', 'DaftarJam', 'Jam mulai', 'Pilih jam mulai.'),
            self::listValidation('G2:G501', 'DaftarJam', 'Jam selesai', 'Pilih jam selesai setelah jam mulai.'),
            self::dateValidation('H2:H501', 'Tanggal mulai'),
            self::dateValidation('I2:I501', 'Tanggal berakhir'),
        ];
        $mainSheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:I501"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="1" width="38" customWidth="1"/><col min="2" max="3" width="30" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/><col min="5" max="7" width="14" customWidth="1"/><col min="8" max="9" width="18" customWidth="1"/></cols>'
            .'<sheetData>'.$mainRows.'</sheetData><autoFilter ref="A1:I501"/>'
            .'<dataValidations count="'.count($validations).'">'.implode('', $validations).'</dataValidations>'
            .'</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $mainSheet);

        $referenceHeaders = ['Prodi', 'Ruangan', 'Hari', 'Jam'];
        $referenceRows = '<row r="1">';
        foreach ($referenceHeaders as $column => $header) {
            $referenceRows .= self::inlineCell(self::columnLetter($column + 1).'1', $header, 1);
        }
        $referenceRows .= '</row>';
        $referenceCount = max(count($prodis), count($rooms), count($days), count($times));
        for ($index = 0; $index < $referenceCount; $index++) {
            $row = $index + 2;
            $referenceRows .= '<row r="'.$row.'">';
            foreach ([$prodis[$index] ?? null, $rooms[$index] ?? null, $days[$index] ?? null, $times[$index] ?? null] as $column => $value) {
                if ($value !== null) {
                    $referenceRows .= self::inlineCell(self::columnLetter($column + 1).$row, $value);
                }
            }
            $referenceRows .= '</row>';
        }
        $referenceSheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$referenceRows.'</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet2.xml', $referenceSheet);
        $zip->close();

        return $path;
    }

    private static function columnLetter(int $number): string
    {
        $letter = '';
        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)).$letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }

    private static function inlineCell(string $reference, mixed $value, int $style = 0): string
    {
        $clean = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $styleAttribute = $style > 0 ? ' s="'.$style.'"' : '';

        return '<c r="'.$reference.'"'.$styleAttribute.' t="inlineStr"><is><t xml:space="preserve">'.$clean.'</t></is></c>';
    }

    private static function listValidation(string $range, string $listName, string $title, string $prompt): string
    {
        return '<dataValidation type="list" errorStyle="stop" allowBlank="0" showDropDown="0" showInputMessage="1" showErrorMessage="1" sqref="'.$range.'" errorTitle="Pilihan tidak valid" error="Pilih nilai yang tersedia pada daftar." promptTitle="'.self::xmlText($title).'" prompt="'.self::xmlText($prompt).'"><formula1>'.$listName.'</formula1></dataValidation>';
    }

    private static function textValidation(string $range, int $maximum, string $error): string
    {
        return '<dataValidation type="textLength" operator="between" errorStyle="stop" allowBlank="0" showErrorMessage="1" sqref="'.$range.'" errorTitle="Data wajib diisi" error="'.self::xmlText($error).'"><formula1>1</formula1><formula2>'.$maximum.'</formula2></dataValidation>';
    }

    private static function dateValidation(string $range, string $title): string
    {
        return '<dataValidation type="date" operator="between" errorStyle="stop" allowBlank="0" showInputMessage="1" showErrorMessage="1" sqref="'.$range.'" errorTitle="Tanggal tidak valid" error="Isi tanggal antara tahun 2020 dan 2100." promptTitle="'.self::xmlText($title).'" prompt="Ketik tanggal dengan format YYYY-MM-DD."><formula1>DATE(2020,1,1)</formula1><formula2>DATE(2100,12,31)</formula2></dataValidation>';
    }

    private static function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
