<?php
require __DIR__ . '/../src/ImportGuard.php';
use NovAsia\ListingImport\ImportGuard;
$guard = new ImportGuard(array_combine(ImportGuard::FIELDS, ImportGuard::FIELDS));
$result = $guard->importCsv(file_get_contents(__DIR__.'/listings.csv'), '2026-10-29');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
