<?php
// app/Support/CsvExport.php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV download. Every cell is neutralised against spreadsheet
 * formula injection: student names and reasons are user-supplied, and a
 * value starting with = + - or @ would run as a formula when the Auditor
 * opens the file in Excel.
 */
class CsvExport
{
    /**
     * @param array<int, string>             $header
     * @param iterable<int, array<int, mixed>> $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly

            fputcsv($out, array_map([self::class, 'safe'], $header), ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Prefixes a quote when a cell could be read as a formula. */
    public static function safe(mixed $value): string
    {
        $cell = (string) $value;

        return $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'" . $cell
            : $cell;
    }
}
