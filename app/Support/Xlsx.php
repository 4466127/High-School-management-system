<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * خواندن و ساختن فایل ساده‌ی Excel (.xlsx) با افزونه‌های zip و SimpleXML خود PHP، بدون بسته‌ی بیرونی.
 * فقط آنچه ورود گروهی لازم دارد: خواندن متن خانه‌های برگه‌ی اول، و ساختن فایل نمونه (برگه‌ها با سربرگ پررنگ،
 * راست‌به‌چپ، همه‌ی خانه‌ها با قالب «متن» تا صفر اول شماره‌ی تماس و تاریخ شمسی دست نخورد).
 */
final class Xlsx
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * سطرهای برگه‌ی اول (هر سطر: آرایه‌ی متن خانه‌ها به ترتیب ستون؛ ستون خالی = '').
     *
     * @return list<list<string>>
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Not an xlsx file');
        }

        try {
            $shared = self::sharedStrings($zip);
            $sheetPath = self::firstSheetPath($zip);
            // محافظت در برابر فایل فشرده‌ی بسیار بزرگ
            if (($zip->statName($sheetPath)['size'] ?? 0) > 30 * 1024 * 1024) {
                throw new RuntimeException('Sheet too large');
            }
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) {
                throw new RuntimeException('Sheet not found');
            }
        } finally {
            $zip->close();
        }

        $sheet = self::xml($xml);
        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            $next = 0;
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $index = $ref !== '' ? self::columnIndex($ref) : $next;
                $cells[$index] = self::cellValue($c, $shared);
                $next = $index + 1;
            }
            if ($cells === []) {
                $rows[] = [];

                continue;
            }
            $line = array_fill(0, max(array_keys($cells)) + 1, '');
            foreach ($cells as $i => $value) {
                $line[$i] = $value;
            }
            $rowNumber = (int) $row['r'];
            if ($rowNumber > 0) {
                while (count($rows) < $rowNumber - 1) {
                    $rows[] = [];
                }
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * ساختن فایل xlsx.
     *
     * @param  list<array{name: string, rows: list<list<string>>, widths?: list<int>}>  $sheets  سطر اول هر برگه سربرگ (پررنگ) است
     */
    public static function build(array $sheets): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $overrides = '';
        $workbookSheets = '';
        $rels = '';
        foreach (array_values($sheets) as $i => $sheet) {
            $n = $i + 1;
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", self::sheetXml($sheet['rows'], $sheet['widths'] ?? []));
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbookSheets .= '<sheet name="'.self::esc(mb_substr($sheet['name'], 0, 31)).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }
        $stylesId = count($sheets) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="'.self::NS.'" xmlns:r="'.self::REL_NS.'"><sheets>'.$workbookSheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels
            .'<Relationship Id="rId'.$stylesId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        // سبک ۰ = متن عادی (قالب @)، سبک ۱ = سربرگ پررنگ با زمینه
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="'.self::NS.'">'
            .'<fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><name val="Tahoma"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCE6F5"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>');
        $zip->close();

        $binary = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $binary;
    }

    /** @param list<list<string>> $rows */
    private static function sheetXml(array $rows, array $widths): string
    {
        $maxCols = max(1, ...array_map('count', $rows ?: [[]]));
        $cols = '<cols>';
        for ($i = 0; $i < $maxCols; $i++) {
            $w = $widths[$i] ?? 18;
            // style="0" روی کل ستون: خانه‌ای که کاربر تازه می‌نویسد هم «متن» است
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" style="0" customWidth="1"/>';
        }
        $cols .= '</cols>';

        $data = '';
        foreach ($rows as $r => $row) {
            $data .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $value) {
                $style = $r === 0 ? ' s="1"' : ' s="0"';
                $data .= '<c r="'.self::columnName($c).($r + 1).'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
            }
            $data .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::NS.'" xmlns:r="'.self::REL_NS.'">'
            .'<sheetViews><sheetView rightToLeft="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$cols.'<sheetData>'.$data.'</sheetData></worksheet>';
    }

    /** @return list<string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $strings = [];
        foreach (self::xml($xml)->si as $si) {
            $strings[] = self::text($si);
        }

        return $strings;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook !== false && $rels !== false) {
            $wb = self::xml($workbook);
            $first = $wb->sheets->sheet[0] ?? null;
            if ($first) {
                $rid = (string) $first->attributes(self::REL_NS)['id'];
                foreach (self::xml($rels)->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = ltrim((string) $rel['Target'], '/');

                        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function cellValue(SimpleXMLElement $c, array $shared): string
    {
        $type = (string) $c['t'];

        $value = match ($type) {
            's' => $shared[(int) $c->v] ?? '',
            'inlineStr' => self::text($c->is),
            'b' => ((string) $c->v) === '1' ? '1' : '0',
            default => (string) $c->v,
        };

        // عدد صحیح ذخیره‌شده به شکل 799123456.0 یا 1E+3 ← متن ساده
        if ($type === '' && is_numeric($value) && (float) $value == (int) (float) $value && abs((float) $value) < 1e15) {
            $value = (string) (int) (float) $value;
        }

        return trim($value);
    }

    private static function text(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private static function xml(string $xml): SimpleXMLElement
    {
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($doc === false) {
            throw new RuntimeException('Invalid xml');
        }

        return $doc;
    }

    /** «C12» ← ۲ */
    private static function columnIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/', strtoupper($ref), $m);
        $index = 0;
        foreach (str_split($m[0] ?? 'A') as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return $index - 1;
    }

    private static function columnName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - 1, 26);
        }

        return $name;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
