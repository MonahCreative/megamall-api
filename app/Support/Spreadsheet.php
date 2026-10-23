<?php

namespace App\Support;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Small wrapper around OpenSpout (installed with Filament) for Excel files:
 * write a workbook with one or more sheets, and read the first sheet of an .xlsx or .csv file.
 */
class Spreadsheet
{
    /**
     * @param  array<string, array{0: array<int, string>, 1: iterable<array<int, mixed>>}>  $sheets  sheet name => [headings, rows]
     */
    public static function write(string $path, array $sheets, float $columnWidth = 18): void
    {
        $options = new XlsxOptions;
        $widest = 1;
        foreach ($sheets as [$headings]) {
            $widest = max($widest, count($headings));
        }
        $options->setColumnWidthForRange($columnWidth, 1, $widest);

        $writer = new XlsxWriter($options);
        $writer->openToFile($path);
        $bold = (new Style)->setFontBold();

        $first = true;
        foreach ($sheets as $name => [$headings, $rows]) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(static::sheetName((string) $name));
            $first = false;

            $writer->addRow(Row::fromValues($headings, $bold));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(static::cell(...), array_values($row))));
            }
        }

        $writer->close();
    }

    /** Write the workbook to a temporary file and send it to the browser, deleting it afterwards. */
    public static function download(string $filename, array $sheets): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'mm-xlsx-');
        static::write($path, $sheets);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * Rows of the first sheet of an Excel (.xlsx) or CSV file, as arrays of plain values.
     * The file type is detected from its content, so the file name does not matter.
     *
     * @return array<int, array<int, mixed>>
     */
    public static function read(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('The file could not be opened.');
        }
        $start = (string) fread($handle, 4096);
        fclose($handle);

        if (str_starts_with($start, "PK\x03\x04")) {
            $reader = new XlsxReader;
        } elseif (str_starts_with($start, "\xD0\xCF\x11\xE0")) {
            throw new RuntimeException('This is an old Excel file (.xls). In Excel choose File > Save As > Excel Workbook (.xlsx), then upload again.');
        } else {
            $options = new CsvOptions;
            // Excel in some languages saves CSV with ; between columns.
            $firstLine = strtok($start, "\n") ?: '';
            if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $options->FIELD_DELIMITER = ';';
            }
            $reader = new CsvReader($options);
        }

        $rows = [];
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private static function cell(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i'),
            $value instanceof \BackedEnum => $value->value,
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => implode(', ', $value),
            default => $value,
        };
    }

    private static function sheetName(string $name): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), 0, 31) ?: 'Sheet';
    }
}
