# Listing Import Guard

A small PHP 8.2+ library for the moment before an agency CSV enters your property catalogue. Map the provider's columns, validate records, review rejected rows, then export spreadsheet-safe results. No network requests, no CMS writes and no dependencies.

Published by [NovAsia Estate](https://novasia.estate/), whose property research covers Cambodia, Thailand and Bali. This project handles the import boundary; it does not verify availability or the accuracy of an advertised price.

## Why reject a plausible-looking value?

`1,200` could be a grouping convention, a decimal convention or two CSV fields. `$` does not identify one currency. A missing source date must not become today's date. This library accepts explicit decimal strings and an explicit supported currency instead of guessing.

## Install and use

```sh
composer require novasia-estate/listing-import-guard
```

```php
require 'vendor/autoload.php';
use NovAsia\ListingImport\ImportGuard;

$guard = new ImportGuard([
    'id' => 'Reference', 'title' => 'Headline',
    'price' => 'Amount', 'currency' => 'Currency',
    'source_url' => 'Source', 'observed_at' => 'Checked',
]);
$report = $guard->importCsv(file_get_contents('agency.csv'), '2026-10-29');
foreach ($report['rejected'] as $row) {
    echo $row['record'] . ': ' . implode(', ', $row['errors']) . PHP_EOL;
}
file_put_contents('reviewed.csv', ImportGuard::exportCsv($report['accepted']));
```

`price` remains a string, including decimal precision. It is never converted to a float or an exchange-rate equivalent. Accepted rows have all six canonical fields. Duplicate identifiers after the first accepted record are rejected. Rejection positions count CSV records (a quoted multiline field is still one record), not physical lines.

## Contract

| Field | Rule |
| --- | --- |
| id | Non-empty, up to 200 bytes, unique among accepted rows |
| title | Non-empty, up to 2,000 bytes |
| price | Non-negative plain decimal string, up to 40 bytes; explicit zero allowed |
| currency | USD, THB, IDR, KHR, EUR, GBP, AUD or SGD; uppercase and explicit |
| source_url | HTTP(S), no embedded credentials, no literal private/reserved IP |
| observed_at | Actual YYYY-MM-DD date, no later than explicit as-of date |

CSV uses comma delimiters, a unique header, UTF-8 (optional BOM), at most 5 MB. Standard PHP `fgetcsv` quoting behavior applies. Extra unmapped columns are ignored. URL validation is syntactic; DNS aliases are not checked and this is **not an SSRF guard** for any later fetching code.

## WordPress or framework integration

Run validation before calling `wp_insert_post` or writing an ORM model. Keep the rejection report for editorial review. Store `price` as DECIMAL or a string, not FLOAT. Escape titles for the final output context (`esc_html` in WordPress); this library does not turn text into trusted HTML. Do not automatically fetch a source URL just because its syntax passed.

## Try the synthetic fixture

```sh
php examples/import.php
php tests/run.php
```

The first example row is accepted; the second is rejected for missing currency. The sample is invented and contains no real listing, customer record or market price.

## Related workflow

- [Property Schema Validator](https://github.com/novasia-estate/property-schema-validator) checks a JSON representation before sharing it downstream.
- [CSV Quality Lab](https://github.com/novasia-estate/csv-quality-lab) provides an interactive browser review when a PHP pipeline is not available.
- [NovAsia Estate](https://novasia.estate/) supplies the publisher's property guides and research context; this library carries no live inventory.

MIT licensed. No telemetry, tracking pixels, API keys or external service required. Issues and small reproducible failing CSV examples are welcome; remove private contact details before opening an issue.
