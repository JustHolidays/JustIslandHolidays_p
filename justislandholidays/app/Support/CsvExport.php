<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a CSV download. Cells that a spreadsheet would run as a formula are neutralised. */
final class CsvExport
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel reads accents correctly
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function cell(mixed $value): string
    {
        $value = match (true) {
            $value === null => '',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => json_encode($value),
            default => (string) $value,
        };

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
