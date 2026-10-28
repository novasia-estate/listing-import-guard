<?php
declare(strict_types=1);
namespace NovAsia\ListingImport;

/** Validation at the import boundary. Does not fetch URLs or write to a CMS. */
final class ImportGuard
{
    public const FIELDS = ['id', 'title', 'price', 'currency', 'source_url', 'observed_at'];

    /** @param array<string,string> $mapping canonical field => input column */
    public function __construct(private array $mapping)
    {
        $keys = array_keys($mapping);
        sort($keys);
        $expected = self::FIELDS;
        sort($expected);
        if ($keys !== $expected || count(array_unique(array_values($mapping))) !== 6) {
            throw new \InvalidArgumentException('Map each of the six fields to a distinct column.');
        }
        foreach ($mapping as $column) {
            if (!is_string($column) || trim($column) === '') {
                throw new \InvalidArgumentException('Column names must be non-empty strings.');
            }
        }
    }

    /** @return array{accepted:array, rejected:array} */
    public function importCsv(string $csv, string $asOf): array
    {
        self::date($asOf);
        if (strlen($csv) > 5_000_000 || str_contains($csv, "\0") || preg_match('//u', $csv) !== 1) {
            throw new \InvalidArgumentException('Use UTF-8 CSV up to 5 MB, without NUL bytes.');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);
        try {
            $header = fgetcsv($stream, null, ',', '"', '');
            if ($header === false || count(array_unique($header)) !== count($header)) {
                throw new \InvalidArgumentException('CSV needs a unique header.');
            }
            foreach ($this->mapping as $column) {
                if (!in_array($column, $header, true)) {
                    throw new \InvalidArgumentException('Missing column: ' . $column);
                }
            }
            $accepted = $rejected = $seen = [];
            $line = 1;
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) { continue; }
                if (count($values) !== count($header)) {
                    $rejected[] = ['record' => $line, 'errors' => ['column_count']];
                    continue;
                }
                $input = array_combine($header, $values);
                $row = [];
                foreach ($this->mapping as $field => $column) { $row[$field] = trim($input[$column]); }
                $errors = [];
                if ($row['id'] === '' || strlen($row['id']) > 200) { $errors[] = 'id'; }
                if (isset($seen[$row['id']])) { $errors[] = 'duplicate_id'; }
                if ($row['title'] === '' || strlen($row['title']) > 2000) { $errors[] = 'title'; }
                // Deliberately reject currency symbols, exponent notation and grouping separators.
                if (!preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $row['price']) || strlen($row['price']) > 40) { $errors[] = 'price_decimal'; }
                if (!in_array($row['currency'], ['USD', 'THB', 'IDR', 'KHR', 'EUR', 'GBP', 'AUD', 'SGD'], true)) { $errors[] = 'unsupported_currency'; }
                if (!self::publicHttpUrl($row['source_url'])) { $errors[] = 'source_url'; }
                try { self::date($row['observed_at']); if ($row['observed_at'] > $asOf) { $errors[] = 'future_observation'; } }
                catch (\InvalidArgumentException) { $errors[] = 'observed_at'; }
                if ($errors) { $rejected[] = ['record' => $line, 'id' => $row['id'], 'errors' => $errors]; }
                else { $seen[$row['id']] = true; $accepted[] = $row; }
            }
            return ['accepted' => $accepted, 'rejected' => $rejected];
        } finally { fclose($stream); }
    }

    /** Neutralize formula-leading text when exporting to spreadsheet applications. */
    public static function spreadsheetCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@\-]/', $value) ? "'" . $value : $value;
    }

    public static function exportCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        try {
            fputcsv($stream, self::FIELDS, ',', '"', '', "\r\n");
            foreach ($rows as $row) {
                $values = [];
                foreach (self::FIELDS as $field) { $values[] = self::spreadsheetCell((string)($row[$field] ?? '')); }
                fputcsv($stream, $values, ',', '"', '', "\r\n");
            }
            rewind($stream);
            return stream_get_contents($stream);
        } finally { fclose($stream); }
    }

    private static function date(string $value): void
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) { throw new \InvalidArgumentException('Use an actual YYYY-MM-DD date.'); }
    }

    private static function publicHttpUrl(string $value): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) { return false; }
        $parts = parse_url($value);
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) { return false; }
        $host = trim($parts['host'] ?? '', '[]');
        if (strtolower($host) === 'localhost' || !str_contains($host, '.')) { return false; }
        if (filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return false; }
        return true; // Syntax check only: no DNS resolution and no assertion of source truth.
    }
}
