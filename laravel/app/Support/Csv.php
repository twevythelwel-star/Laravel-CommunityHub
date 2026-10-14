<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Building CSV exports that are safe to open in a spreadsheet.
 */
final class Csv
{
    /**
     * One quoted cell. A value starting with = + - @ (or a tab or carriage
     * return) is a formula to Excel and its kin, so a visitor named
     * =HYPERLINK(...) would run when staff opened the roster; such values get
     * a leading apostrophe, which the spreadsheet shows as plain text.
     */
    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $text = "'".$text;
        }

        return '"'.str_replace('"', '""', $text).'"';
    }

    /** @param  list<mixed>  $values */
    public static function row(array $values): string
    {
        return implode(',', array_map(self::cell(...), $values))."\n";
    }

    /**
     * A CSV file download, every cell made safe by cell().
     *
     * @param  list<string>  $header
     * @param  iterable<list<mixed>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->stream(function () use ($header, $rows) {
            echo self::row($header);
            foreach ($rows as $row) {
                echo self::row($row);
            }
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $filename).'"',
        ]);
    }
}
