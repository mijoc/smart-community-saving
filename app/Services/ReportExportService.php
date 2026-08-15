<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpWord\IOFactory as WordIO;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ReportExportService
 * -------------------
 * Generic, reusable exporter that turns a tabular dataset
 * (title + headers + rows + optional summary lines) into a
 * downloadable file in any of the four supported formats:
 *   pdf | xlsx | docx | csv
 *
 * Every report controller hands the same payload shape to this
 * service, so adding a new report = preparing rows, calling export().
 */
class ReportExportService
{
    /** Allowed format keys. */
    public const FORMATS = ['pdf', 'xlsx', 'docx', 'csv'];

    /**
     * @param  string                  $format    one of pdf|xlsx|docx|csv
     * @param  string                  $title     human title shown on the report
     * @param  array<int,string>       $headers   column headings
     * @param  iterable<int,array>     $rows      list of row arrays (same order as headers)
     * @param  array<string,string>    $meta      optional key=>value summary lines
     * @param  string                  $filename  base filename (no extension)
     */
    public function export(
        string $format,
        string $title,
        array $headers,
        iterable $rows,
        array $meta = [],
        string $filename = 'report',
    ) {
        $format = strtolower($format);
        if (! in_array($format, self::FORMATS, true)) {
            abort(400, "Unsupported export format: {$format}");
        }

        // Materialise iterables so we can iterate more than once.
        $rows = is_array($rows) ? $rows : iterator_to_array($rows);

        return match ($format) {
            'csv'  => $this->csv($title, $headers, $rows, $meta, $filename),
            'xlsx' => $this->xlsx($title, $headers, $rows, $meta, $filename),
            'docx' => $this->docx($title, $headers, $rows, $meta, $filename),
            'pdf'  => $this->pdf($title, $headers, $rows, $meta, $filename),
        };
    }

    // ------------------------------------------------------------------
    // CSV
    // ------------------------------------------------------------------
    protected function csv(string $title, array $headers, array $rows, array $meta, string $filename): StreamedResponse
    {
        $name = $this->safeName($filename).'.csv';

        return response()->streamDownload(function () use ($title, $headers, $rows, $meta) {
            $out = fopen('php://output', 'w');

            // BOM so Excel opens UTF-8 cleanly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$title]);
            fputcsv($out, ['Generated', now()->toDayDateTimeString()]);
            foreach ($meta as $k => $v) {
                fputcsv($out, [$k, $v]);
            }
            fputcsv($out, []); // blank line

            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : (string) $v, $r));
            }
            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ------------------------------------------------------------------
    // Excel (.xlsx)
    // ------------------------------------------------------------------
    protected function xlsx(string $title, array $headers, array $rows, array $meta, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->safeSheetTitle($title));

        $colCount = max(count($headers), 2);
        $lastCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        // Title block
        $row = 1;
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row++;

        $sheet->setCellValue("A{$row}", 'Generated: '.now()->toDayDateTimeString());
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
        $row++;

        foreach ($meta as $k => $v) {
            $sheet->setCellValue("A{$row}", (string) $k);
            $sheet->setCellValue("B{$row}", (string) $v);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++; // blank spacer

        // Header row
        $headerRow = $row;
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2A4D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        // Body
        $firstBody = $row;
        foreach ($rows as $r) {
            foreach (array_values($r) as $i => $v) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValueExplicit(
                    "{$col}{$row}",
                    is_scalar($v) || $v === null ? $v : (string) $v,
                    is_numeric($v) && ! is_string($v)
                        ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                        : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                );
            }
            $row++;
        }
        $lastBody = $row - 1;

        if ($lastBody >= $firstBody) {
            $sheet->getStyle("A{$firstBody}:{$lastCol}{$lastBody}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                ],
            ]);
        }

        // Auto-size columns
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))
                  ->setAutoSize(true);
        }

        $sheet->freezePane("A".($headerRow + 1));

        $name = $this->safeName($filename).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = SpreadsheetIO::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ------------------------------------------------------------------
    // Word (.docx)
    // ------------------------------------------------------------------
    protected function docx(string $title, array $headers, array $rows, array $meta, string $filename): StreamedResponse
    {
        $word = new PhpWord();
        $word->setDefaultFontName('DejaVu Sans');
        $word->setDefaultFontSize(10);

        $section = $word->addSection([
            'orientation' => 'landscape',
            'marginTop'   => 600, 'marginBottom' => 600,
            'marginLeft'  => 600, 'marginRight'  => 600,
        ]);

        $section->addText($title, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
        $section->addText('Generated: '.now()->toDayDateTimeString(),
            ['italic' => true, 'size' => 9], ['alignment' => Jc::CENTER]);

        if (! empty($meta)) {
            $section->addTextBreak(1);
            foreach ($meta as $k => $v) {
                $line = $section->addTextRun();
                $line->addText("{$k}: ", ['bold' => true]);
                $line->addText((string) $v);
            }
        }

        $section->addTextBreak(1);

        $tableStyle = [
            'borderColor' => 'CCCCCC', 'borderSize' => 6,
            'cellMargin'  => 60, 'alignment'   => Jc::CENTER,
        ];
        $word->addTableStyle('ReportTable', $tableStyle, [
            'bgColor' => '1F2A4D',
        ]);

        $table = $section->addTable('ReportTable');

        $table->addRow(null, ['tblHeader' => true]);
        foreach ($headers as $h) {
            $cell = $table->addCell(null, ['bgColor' => '1F2A4D']);
            $cell->addText((string) $h, ['bold' => true, 'color' => 'FFFFFF']);
        }

        foreach ($rows as $r) {
            $table->addRow();
            foreach ($r as $v) {
                $cell = $table->addCell();
                $cell->addText(
                    is_scalar($v) || $v === null ? (string) $v : (string) $v,
                );
            }
        }

        $name = $this->safeName($filename).'.docx';

        return response()->streamDownload(function () use ($word) {
            $writer = WordIO::createWriter($word, 'Word2007');
            $writer->save('php://output');
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    // ------------------------------------------------------------------
    // PDF
    // ------------------------------------------------------------------
    protected function pdf(string $title, array $headers, array $rows, array $meta, string $filename)
    {
        $pdf = Pdf::loadView('reports._table', [
            'title'    => $title,
            'headers'  => $headers,
            'rows'     => $rows,
            'meta'     => $meta,
            'genAt'    => now()->toDayDateTimeString(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->safeName($filename).'.pdf');
    }

    // ------------------------------------------------------------------
    protected function safeName(string $name): string
    {
        $name = trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $name), '_');
        return $name !== '' ? $name : 'report';
    }

    protected function safeSheetTitle(string $name): string
    {
        // Excel sheet title rules: <= 31 chars, no [ ] : * ? / \
        $clean = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $name);
        return mb_substr(trim($clean) ?: 'Report', 0, 31);
    }
}
