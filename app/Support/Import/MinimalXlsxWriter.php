<?php

declare(strict_types=1);

namespace App\Support\Import;

use RuntimeException;
use ZipArchive;

final class MinimalXlsxWriter
{
    /**
     * @param  list<list<string>>  $rows
     */
    public static function write(string $absolutePath, array $rows): void
    {
        $shared = [];
        $sharedIndex = [];
        $sheetRowsXml = '';

        foreach ($rows as $rowIndex => $cells) {
            $excelRow = $rowIndex + 1;
            $cellXml = '';
            foreach ($cells as $colIndex => $value) {
                $column = self::columnLetter($colIndex);
                $ref = $column.$excelRow;
                $value = (string) $value;

                if (! array_key_exists($value, $sharedIndex)) {
                    $sharedIndex[$value] = count($shared);
                    $shared[] = $value;
                }

                $idx = $sharedIndex[$value];
                $cellXml .= '<c r="'.$ref.'" t="s"><v>'.$idx.'</v></c>';
            }

            $sheetRowsXml .= '<row r="'.$excelRow.'">'.$cellXml.'</row>';
        }

        $sharedXml = '';
        foreach ($shared as $string) {
            $sharedXml .= '<si><t>'.htmlspecialchars($string, ENT_XML1 | ENT_COMPAT, 'UTF-8').'</t></si>';
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
                .'</Relationships>',
            'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($shared).'" uniqueCount="'.count($shared).'">'
                .$sharedXml
                .'</sst>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetData>'.$sheetRowsXml.'</sheetData></worksheet>',
        ];

        $zip = new ZipArchive();
        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create xlsx file.');
        }

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index;
        do {
            $letter = chr(65 + ($n % 26)).$letter;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $letter;
    }
}
