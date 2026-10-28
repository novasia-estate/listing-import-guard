<?php
declare(strict_types=1);
require __DIR__ . '/../src/ImportGuard.php';
use NovAsia\ListingImport\ImportGuard;
$checks = 0;
function check(bool $ok, string $name): void { global $checks; $checks++; if (!$ok) { throw new RuntimeException($name); } }
$guard = new ImportGuard(array_combine(ImportGuard::FIELDS, ImportGuard::FIELDS));
function csv(array $rows): string { $f = fopen('php://temp','r+'); fputcsv($f, ImportGuard::FIELDS, ',', '"', ''); foreach ($rows as $r) { fputcsv($f,$r,',','"',''); } rewind($f); $s=stream_get_contents($f); fclose($f); return $s; }
$base = ['demo-1','Synthetic villa, test only','2248250000.50','IDR','https://example.org/demo','2026-10-10'];
$r = $guard->importCsv(csv([$base]),'2026-10-29');
check(count($r['accepted'])===1 && $r['accepted'][0]['price']==='2248250000.50','decimal string preserved');
$cases = [2=>['1,000','-1','1e3','$500','NaN'], 3=>['','usd','XXX'],4=>['javascript:alert(1)','http://localhost/a','http://127.0.0.1/a','https://u:p@example.org/a'],5=>['2026-02-30','2026-11-01']];
foreach ($cases as $field=>$badValues) { foreach($badValues as $v) { $row=$base; $row[$field]=$v; check(count($guard->importCsv(csv([$row]),'2026-10-29')['rejected'])===1,'reject '.$v); } }
check(count($guard->importCsv(csv([$base,$base]),'2026-10-29')['rejected'])===1,'duplicate rejected');
$zero=$base;$zero[2]='0'; check(count($guard->importCsv(csv([$zero]),'2026-10-29')['accepted'])===1,'explicit zero accepted');
check(str_contains(ImportGuard::exportCsv([array_combine(ImportGuard::FIELDS,['=cmd','@title','1','USD','https://example.org','2026-10-10'])]),"'=cmd"),'export formula guarded');
check(ImportGuard::spreadsheetCell("\t=2+2")==="'\t=2+2",'whitespace formula guarded');
$custom=new ImportGuard(['id'=>'code','title'=>'label','price'=>'amount','currency'=>'ccy','source_url'=>'url','observed_at'=>'date']);
check(count($custom->importCsv("code,label,amount,ccy,url,date\na,Example,25.00,USD,https://example.org,2026-10-10",'2026-10-29')['accepted'])===1,'mapping');
try {$guard->importCsv("id,id\na,b",'2026-10-29');check(false,'bad header');} catch(InvalidArgumentException){check(true,'bad header rejected');}
echo "$checks checks passed\n";
