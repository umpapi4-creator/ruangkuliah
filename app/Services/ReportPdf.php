<?php

namespace App\Services;

/** Small dependency-free PDF text renderer for management summaries (A4 portrait). */
class ReportPdf
{
    public static function download(array $lines, string $filename)
    {
        $pages = array_chunk($lines, 48);
        if (! $pages) $pages = [[]];
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
        $kids = [];
        $id = 4;
        foreach ($pages as $index => $pageLines) {
            $pageId = $id++;
            $streamId = $id++;
            $kids[] = $pageId.' 0 R';
            $cmd = "BT /F1 9 Tf 13 TL 46 797 Td\n";
            foreach ($pageLines as $row) {
                $safe = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $row);
                $safe = preg_replace('/[^\x20-\x7E]/', '?', $safe === false ? '' : $safe);
                $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], substr($safe, 0, 92));
                $cmd .= '('.$safe.") Tj T*\n";
            }
            $cmd .= '() Tj T* (Halaman '.($index+1).' / '.count($pages).") Tj\nET";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$streamId.' 0 R >>';
            $objects[$streamId] = '<< /Length '.strlen($cmd).' >>' . "\nstream\n".$cmd."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $positions = [0];
        foreach ($objects as $n => $body) {
            $positions[$n] = strlen($pdf);
            $pdf .= $n." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(max(array_keys($objects))+1)."\n0000000000 65535 f \n";
        foreach ($objects as $n => $body) $pdf .= sprintf('%010d 00000 n ', $positions[$n])."\n";
        $pdf .= 'trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>'."\nstartxref\n".$xref."\n%%EOF";
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => strlen($pdf),
        ]);
    }
}
