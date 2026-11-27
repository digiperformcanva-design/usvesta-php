<?php
/** Minimal .xlsx reader (first worksheet) – replaces openpyxl for the bulk product upload. */
final class Xlsx
{
    /** @return array<int, array<int, mixed>> rows of cell values (row 0 = header) */
    public static function readRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('Not a valid .xlsx file');
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml);
            foreach ($sx->si as $si) {
                if (isset($si->t)) { $shared[] = (string)$si->t; continue; }
                $s = '';
                foreach ($si->r as $r) $s .= (string)$r->t;
                $shared[] = $s;
            }
        }
        // locate first sheet via workbook rels
        $sheetPath = 'xl/worksheets/sheet1.xml';
        if (($wb = $zip->getFromName('xl/workbook.xml')) !== false && ($rels = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
            $wbx = simplexml_load_string($wb);
            $wbx->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $first = $wbx->sheets->sheet[0] ?? null;
            if ($first) {
                $rid = (string)$first->attributes('r', true)->id;
                $rx = simplexml_load_string($rels);
                foreach ($rx->Relationship as $rel) {
                    if ((string)$rel['Id'] === $rid) {
                        $t = ltrim((string)$rel['Target'], '/');
                        $sheetPath = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                    }
                }
            }
        }
        $xml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($xml === false) throw new RuntimeException('Worksheet not found');
        $sx = simplexml_load_string($xml);
        $rows = [];
        foreach ($sx->sheetData->row as $row) {
            $r = (int)$row['r'] - 1;
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = self::colIndex(preg_replace('/\d+/', '', $ref));
                $type = (string)$c['t'];
                $v = isset($c->v) ? (string)$c->v : null;
                if ($type === 's') $v = $shared[(int)$v] ?? '';
                elseif ($type === 'inlineStr') $v = (string)($c->is->t ?? '');
                elseif ($type === 'b') $v = $v === '1';
                elseif ($v !== null && is_numeric($v)) $v = str_contains($v, '.') || str_contains(strtolower($v), 'e') ? (float)$v : (int)$v;
                $cells[$col] = $v;
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) $line[] = $cells[$i] ?? null;
                $rows[$r] = $line;
            }
        }
        ksort($rows);
        return array_values($rows);
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n - 1;
    }
}
