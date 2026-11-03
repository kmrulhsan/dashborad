<?php
define('APP', 1);
require __DIR__ . '/config.php';
defined('DATA_DIR') || define('DATA_DIR', dirname(rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/')) . '/bt_data');
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Strict', 'path' => '/']);
session_name('bt_sess'); session_start();
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store');
if ($https) header('Strict-Transport-Security: max-age=31536000');

/* ---------- helpers ---------- */
function db() { static $p; return $p ??= new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function N($v) { return is_numeric($v) ? +$v : 0; }
function sm($a, $f) { return array_sum(array_map($f, $a)); }
function ig($n) { $n = (string)round(abs($n)); if (strlen($n) <= 3) return $n; $r = substr($n, 0, -3);
  return preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $r) . ',' . substr($n, -3); }
function m($v) { return ($v === '' || $v === null) ? '' : ($v < 0 ? '−' : '') . '৳' . ig($v); }
function q($v) { return ($v === '' || $v === null) ? '' : rtrim(rtrim(number_format(N($v), 2), '0'), '.'); }
function me() { return $_SESSION['u'] ?? null; }
function csrf() { return $_SESSION['c'] ??= bin2hex(random_bytes(24)); }
function tok() { return '<input type="hidden" name="_c" value="' . h(csrf()) . '">'; }
function go($u) { header('Location: ' . $u); exit; }
function tick($b) { return $b ? '<span class="pos">✔</span>' : '<span class="neg">✘</span>'; }
function logA($act, $ent = null, $rid = null, $det = '') {
  db()->prepare("INSERT INTO audit(at,user_id,username,action,entity,rec_id,detail,ip) VALUES(NOW(),?,?,?,?,?,?,?)")
    ->execute([me()['id'] ?? null, me()['username'] ?? ($_POST['username'] ?? null), $act, $ent, $rid, mb_substr($det, 0, 2000), $_SERVER['REMOTE_ADDR'] ?? '']);
}

/* ---------- roles: edit here to change access ('w' = view + edit, 'r' = view only, '' = hidden) ---------- */
const PERM = [
  'chairman' => ['items' => 'w', 'customers' => 'w', 'purchases' => 'w', 'sales' => 'w', 'expenses' => 'w', 'trucks' => 'w', 'trips' => 'w', 'texp' => 'w', 'vat' => 'w'],
  'manager'  => ['items' => 'w', 'customers' => 'w', 'purchases' => 'w', 'sales' => 'w', 'expenses' => '',  'trucks' => 'w', 'trips' => 'w', 'texp' => 'w', 'vat' => 'w'],
  'accounts' => ['items' => 'r', 'customers' => 'w', 'purchases' => 'w', 'sales' => 'w', 'expenses' => 'w', 'trucks' => 'r', 'trips' => 'r', 'texp' => 'w', 'vat' => 'w'],
];
function can($e, $mode) { $l = PERM[me()['role'] ?? ''][$e] ?? ''; return $mode === 'r' ? $l !== '' : $l === 'w'; }
function chair() { return (me()['role'] ?? '') === 'chairman'; }

/* ---------- lists ---------- */
$CUR = ['BDT', 'JPY', 'USD', 'INR', 'CNY']; $ORI = ['Japan', 'India', 'Other']; $PORT = ['Chattogram', 'Mongla', 'Benapole', 'Other'];
$PAY = ['Cash', 'bKash', 'Nagad', 'Bank Transfer', 'Cheque', 'LC', 'Due / Credit']; $PST = ['Ordered', 'Shipped', 'At Port', 'Cleared', 'In Stock'];
$CAT = ['Rent', 'Salaries & Wages', 'Utilities', 'Port & Clearing', 'Marketing', 'Showroom & Yard', 'Licence & Govt Fees', 'Insurance', 'Bank Charges', 'Office', 'Other'];
$TCAT = ['Repair & Maintenance', 'Tyres', 'Driver Salary', 'Insurance', 'Tax Token & Fitness', 'Route Permit', 'Fuel (not on a trip)', 'Parking & Toll', 'Spare Parts', 'Other'];
$TTY = ['Own car delivery', 'Client job']; $TST = ['Planned', 'In Transit', 'Delivered', 'Cancelled']; $ACT = ['Active', 'Inactive'];
$DIS = explode(',', 'Bagerhat,Bandarban,Barguna,Barishal,Bhola,Bogura,Brahmanbaria,Chandpur,Chapainawabganj,Chattogram,Chuadanga,Cox’s Bazar,Cumilla,Dhaka,Dinajpur,Faridpur,Feni,Gaibandha,Gazipur,Gopalganj,Habiganj,Jamalpur,Jashore,Jhalokathi,Jhenaidah,Joypurhat,Khagrachhari,Khulna,Kishoreganj,Kurigram,Kushtia,Lakshmipur,Lalmonirhat,Madaripur,Magura,Manikganj,Meherpur,Moulvibazar,Munshiganj,Mymensingh,Naogaon,Narail,Narayanganj,Narsingdi,Natore,Netrokona,Nilphamari,Noakhali,Pabna,Panchagarh,Patuakhali,Pirojpur,Rajbari,Rajshahi,Rangamati,Rangpur,Satkhira,Shariatpur,Sherpur,Sirajganj,Sunamganj,Sylhet,Tangail,Thakurgaon');
const KINDS = ['invoice' => 'Customer invoice (PDF)', 'nid' => 'Customer NID', 'tin' => 'Customer TIN certificate', 'm63' => 'Mushak 6.3 copy', 'm43' => 'Mushak 4.3 copy', 'm91' => 'Mushak 9.1 return copy', 'other' => 'Other document'];
function F($k, $l, $t, $o = null, $x = 0) { return [$k, $l, $t, $o, $x]; }

/* ---------- data access & calculations ---------- */
function S() { global $E; static $s;
  if ($s === null) { $s = array_fill_keys(array_keys($E), []);
    foreach (db()->query("SELECT id,entity,data FROM records WHERE deleted_at IS NULL") as $r) {
      if (!isset($s[$r['entity']])) continue; $d = json_decode($r['data'], true) ?: []; $d['id'] = (int)$r['id']; $s[$r['entity']][] = $d; } }
  return $s; }
function cust($id) { foreach (S()['customers'] as $c) if ((string)$c['id'] === (string)$id) return $c; return []; }
function custName($id) { return cust($id)['name'] ?? ''; }
function truck($reg) { foreach (S()['trucks'] as $t) if ((string)$t['reg'] === (string)$reg) return $t; return []; }
function pc($p) { $g = N($p['price'] ?? 0) * N($p['rate'] ?? 0);
  return ['goods' => $g, 'landed' => $g + N($p['freight'] ?? 0) + N($p['duty'] ?? 0) + N($p['clearing'] ?? 0) + N($p['inland'] ?? 0) + N($p['other'] ?? 0)]; }
function LC($c) { static $mp; if ($mp === null) { $mp = []; foreach (S()['purchases'] as $p) { $k = (string)($p['item'] ?? ''); $mp[$k] = ($mp[$k] ?? 0) + pc($p)['landed']; } } return $mp[(string)$c] ?? 0; }
function PS($c) { static $mp; if ($mp === null) { $mp = []; foreach (S()['purchases'] as $p) $mp[(string)($p['item'] ?? '')] = $p['status'] ?? ''; } return $mp[(string)$c] ?? ''; }
function SL($c) { static $mp; if ($mp === null) { $mp = []; foreach (S()['sales'] as $s) $mp[(string)($s['item'] ?? '')] = $s; } return $mp[(string)$c] ?? null; }
function sc($s) { $t = N($s['price'] ?? 0) - N($s['disc'] ?? 0); $c = LC($s['item'] ?? ''); return ['total' => $t, 'due' => $t - N($s['recv'] ?? 0), 'cost' => $c, 'profit' => $t - $c]; }
function sold($i) { return SL($i['code']) !== null; }
function carStat($i) { return sold($i) ? 'Sold' : (PS($i['code']) ?: 'No purchase yet'); }
function custSum($id, $k) { $t = 0; foreach (S()['sales'] as $s) if ((string)($s['cust'] ?? '') === (string)$id) $t += ($k === 'recv' ? N($s['recv'] ?? 0) : sc($s)[$k]); return $t; }
function dc($d) { $c = N($d['fuel'] ?? 0) + N($d['dpay'] ?? 0) + N($d['toll'] ?? 0) + N($d['load'] ?? 0) + N($d['other'] ?? 0);
  return ['cost' => $c, 'margin' => (($d['ty'] ?? '') === 'Client job' && ($d['inc'] ?? '') !== '') ? N($d['inc']) - $c : '']; }
function tdays($d) { if (!$d) return null; $t = strtotime($d); return $t === false ? null : (int)floor(($t - strtotime(date('Y-m-d'))) / 86400); }
function expList($t) { $o = []; foreach (['ins' => 'Insurance', 'fit' => 'Fitness', 'tax' => 'Tax token', 'permit' => 'Route permit'] as $k => $l) { $d = tdays($t[$k] ?? ''); if ($d !== null) $o[] = [$l, $t[$k], $d]; }
  usort($o, fn($a, $b) => $a[2] <=> $b[2]); return $o; }
function nextExp($t) { $x = expList($t); if (!$x) return ''; [$l, $dt, $d] = $x[0]; return ($d <= 30 ? '⚠ ' : '') . $l . ' ' . $dt . ($d < 0 ? ' (overdue)' : ' (' . $d . ' days)'); }
function TS($reg) { static $mp; if ($mp === null) { $mp = [];
    foreach (S()['trips'] as $r) { $k = (string)($r['truck'] ?? ''); $c = dc($r)['cost']; $x = &$mp[$k];
      $x['n'] = ($x['n'] ?? 0) + 1; $x['km'] = ($x['km'] ?? 0) + N($r['km'] ?? 0);
      if (($r['ty'] ?? '') === 'Client job') { $x['inc'] = ($x['inc'] ?? 0) + N($r['inc'] ?? 0); $x['cc'] = ($x['cc'] ?? 0) + $c; } else { $x['own'] = ($x['own'] ?? 0) + $c; } unset($x); }
    foreach (S()['texp'] as $r) { $k = (string)($r['truck'] ?? ''); $mp[$k]['exp'] = ($mp[$k]['exp'] ?? 0) + N($r['amt'] ?? 0); } }
  $x = $mp[(string)$reg] ?? []; $o = ['n' => 0, 'km' => 0, 'inc' => 0, 'cc' => 0, 'own' => 0, 'exp' => 0]; foreach ($o as $k => $v) $o[$k] = $x[$k] ?? 0;
  $o['net'] = $o['inc'] - $o['cc'] - $o['exp']; return $o; }
function FC($rid, $kind) { static $mp; if ($mp === null) { $mp = [];
    foreach (db()->query("SELECT rec_id,kind,COUNT(*) AS n FROM files WHERE deleted_at IS NULL GROUP BY rec_id,kind") as $r) $mp[(int)$r['rec_id']][$r['kind']] = (int)$r['n']; }
  return $mp[(int)$rid][$kind] ?? 0; }
function vatMiss($i) { if (!sold($i)) return null; $x = [];
  if (($i['m63no'] ?? '') === '') $x[] = 'Mushak 6.3 no.'; if (($i['m43'] ?? '') !== 'Yes') $x[] = 'Mushak 4.3'; if (($i['m91'] ?? '') === '') $x[] = 'Mushak 9.1 month';
  foreach (['invoice' => 'Invoice PDF', 'nid' => 'NID', 'tin' => 'TIN'] as $k => $l) if (!FC($i['id'], $k)) $x[] = $l;
  return $x; }
function vatCell($i) { $x = vatMiss($i); return $x === null ? '' : ($x ? 'Missing ' . count($x) : 'Complete'); }
function itemText($i) { $t = ($i['code'] ?? '') . ' ' . ($i['name'] ?? '') . ' ' . ($i['make'] ?? '') . ' ' . ($i['model'] ?? '') . ' ' . ($i['m63no'] ?? '') . ' ' . ($i['m43ref'] ?? '');
  if ($s = SL($i['code'])) { $c = cust($s['cust'] ?? ''); $t .= ' ' . ($s['inv'] ?? '') . ' ' . ($c['name'] ?? '') . ' ' . ($c['phone'] ?? '') . ' ' . ($c['nid'] ?? '') . ' ' . ($c['tin'] ?? ''); }
  return $t; }

/* ---------- entities (screens) ---------- */
$E = [
 'items' => ['t' => 'Inventory (cars)', 's' => 'code', 'r' => ['code'], 'f' => [F('code', 'Chassis no.', 't'), F('name', 'Car (make / model / year)', 't'), F('make', 'Make', 't', null, 1), F('model', 'Model', 't', null, 1), F('year', 'Year', 'n', null, 1), F('colour', 'Colour', 't', null, 1), F('asking', 'Asking price (BDT)', 'n')],
   'c' => [['Status', fn($r) => carStat($r), 't'], ['Landed cost', fn($r) => LC($r['code']), 'm'], ['Sold price', fn($r) => ($s = SL($r['code'])) ? sc($s)['total'] : '', 'm'], ['Profit', fn($r) => ($s = SL($r['code'])) ? sc($s)['profit'] : '', 'm'], ['VAT file', fn($r) => vatCell($r), 't']]],
 'customers' => ['t' => 'Customers', 's' => 'name', 'r' => ['name'], 'f' => [F('name', 'Name / company', 't'), F('phone', 'Phone', 't'), F('address', 'Address', 't', null, 1), F('district', 'District', 's', $DIS, 1), F('nid', 'NID no.', 't', null, 1), F('tin', 'TIN / BIN', 't', null, 1)],
   'c' => [['Total purchases', fn($r) => custSum($r['id'], 'total'), 'm'], ['Received', fn($r) => custSum($r['id'], 'recv'), 'm'], ['Outstanding due', fn($r) => custSum($r['id'], 'due'), 'm']]],
 'purchases' => ['t' => 'Purchases (cars)', 's' => 'date', 'r' => ['date', 'item'], 'f' => [F('date', 'Date', 'd'), F('item', 'Chassis no.', 's', 'items'), F('supplier', 'Supplier', 't'), F('origin', 'Origin country', 's', $ORI, 1), F('port', 'Port of entry', 's', $PORT, 1), F('price', 'Car price (foreign currency)', 'n'), F('cur', 'Currency', 's', $CUR), F('rate', 'Exchange rate (BDT per 1 foreign)', 'n'), F('freight', 'Freight & insurance (BDT)', 'n', null, 1), F('duty', 'Duty & taxes: CD/SD/VAT/AT/AIT (BDT)', 'n', null, 1), F('clearing', 'C&F / port / clearing (BDT)', 'n', null, 1), F('inland', 'Inland transport (BDT)', 'n', null, 1), F('other', 'Other costs (BDT)', 'n', null, 1), F('ref', 'LC / invoice / B/E no.', 't', null, 1), F('paid', 'Paid to supplier (BDT)', 'n'), F('status', 'Status', 's', $PST)],
   'c' => [['Goods cost', fn($r) => pc($r)['goods'], 'm'], ['Landed cost', fn($r) => pc($r)['landed'], 'm'], ['Supplier balance', fn($r) => pc($r)['goods'] - N($r['paid'] ?? 0), 'm']]],
 'sales' => ['t' => 'Sales (cars)', 's' => 'date', 'r' => ['date', 'item', 'cust', 'price'], 'f' => [F('date', 'Date', 'd'), F('inv', 'Invoice no.', 't'), F('cust', 'Customer', 's', 'cust'), F('item', 'Chassis no.', 's', 'items'), F('price', 'Sale price (BDT)', 'n'), F('disc', 'Discount (BDT)', 'n', null, 1), F('pay', 'Payment method', 's', $PAY), F('recv', 'Amount received (BDT)', 'n')],
   'c' => [['Total', fn($r) => sc($r)['total'], 'm'], ['Due', fn($r) => sc($r)['due'], 'm'], ['Car cost', fn($r) => sc($r)['cost'], 'm'], ['Profit', fn($r) => sc($r)['profit'], 'm']]],
 'expenses' => ['t' => 'Expenses', 's' => 'date', 'r' => ['date', 'amt'], 'f' => [F('date', 'Date', 'd'), F('cat', 'Category', 's', $CAT), F('desc', 'Description', 't'), F('amt', 'Amount (BDT)', 'n'), F('via', 'Paid via', 's', $PAY), F('to', 'Paid to', 't')], 'c' => []],
 'trucks' => ['t' => 'Trucks', 's' => 'reg', 'r' => ['reg'], 'f' => [F('reg', 'Truck / vehicle no.', 't'), F('model', 'Model (e.g. Ashok Leyland Boss 1618)', 't'), F('year', 'Year', 'n', null, 1), F('chassis', 'Chassis no.', 't', null, 1), F('driver', 'Regular driver', 't'), F('status', 'Status', 's', $ACT), F('ins', 'Insurance expires', 'd', null, 1), F('fit', 'Fitness certificate expires', 'd', null, 1), F('tax', 'Tax token expires', 'd', null, 1), F('permit', 'Route permit expires', 'd', null, 1)],
   'c' => [['Trips', fn($r) => TS($r['reg'])['n'], 'q'], ['Freight income', fn($r) => TS($r['reg'])['inc'], 'm'], ['Truck expenses', fn($r) => TS($r['reg'])['exp'], 'm'], ['Net profit', fn($r) => TS($r['reg'])['net'], 'm'], ['Next document expiry', fn($r) => nextExp($r), 't']]],
 'trips' => ['t' => 'Trips & Routes', 's' => 'date', 'r' => ['date', 'ty', 'truck'], 'f' => [F('date', 'Date', 'd'), F('ty', 'Trip type', 's', $TTY), F('truck', 'Truck', 's', 'trucks'), F('driver', 'Driver (blank = regular driver)', 't'), F('from', 'From', 't'), F('to', 'To', 't'), F('party', 'Customer / client', 't'), F('car', 'Car being delivered (own car delivery)', 's', 'items'), F('km', 'Distance (km)', 'n'), F('fuel', 'Fuel (BDT)', 'n', null, 1), F('dpay', 'Driver & helper pay (BDT)', 'n', null, 1), F('toll', 'Toll / ferry (BDT)', 'n', null, 1), F('load', 'Loading / unloading (BDT)', 'n', null, 1), F('other', 'Other trip costs (BDT)', 'n', null, 1), F('inc', 'Freight income (client jobs) (BDT)', 'n'), F('status', 'Status', 's', $TST)],
   'c' => [['Trip cost', fn($r) => dc($r)['cost'], 'm'], ['Margin', fn($r) => dc($r)['margin'], 'm'], ['Cost / km', fn($r) => N($r['km'] ?? 0) ? dc($r)['cost'] / N($r['km']) : '', 'm']]],
 'texp' => ['t' => 'Truck expenses', 's' => 'date', 'r' => ['date', 'truck', 'amt'], 'f' => [F('date', 'Date', 'd'), F('truck', 'Truck', 's', 'trucks'), F('cat', 'Category', 's', $TCAT), F('desc', 'Description', 't'), F('amt', 'Amount (BDT)', 'n'), F('via', 'Paid via', 's', $PAY), F('to', 'Paid to (workshop / person)', 't')], 'c' => []],
];
function opts($f) { $o = $f[3];
  if ($o === 'items') return array_map(fn($i) => [(string)$i['code'], $i['code'] . ' – ' . ($i['name'] ?? '')], S()['items']);
  if ($o === 'cust') return array_map(fn($c) => [(string)$c['id'], $c['name']], S()['customers']);
  if ($o === 'trucks') return array_map(fn($t) => [(string)$t['reg'], $t['reg'] . ' – ' . ($t['model'] ?? '')], S()['trucks']);
  return array_map(fn($v) => [$v, $v], $o); }
function disp($f, $v) { if (($f[3] ?? null) === 'cust') return custName($v);
  if ($f[0] === 'year') return (string)$v;
  if ($f[2] === 'n') return (str_contains($f[1], 'BDT') || $f[0] === 'rate') ? m($v) : q($v); return (string)$v; }
function fmt($v, $k) { return $k === 'm' ? m($v) : ($k === 'q' ? q($v) : (string)$v); }
function lab($ent, $k) { global $E; foreach ($E[$ent]['f'] as $f) if ($f[0] === $k) return $f[1]; return $k; }

/* ---------- layout ---------- */
const SECP = ['dash' => 'car', 'items' => 'car', 'car' => 'car', 'vat' => 'car', 'purchases' => 'car', 'sales' => 'car', 'customers' => 'car', 'expenses' => 'car', 'tdash' => 'truck', 'trips' => 'truck', 'trucks' => 'truck', 'texp' => 'truck'];
const TABS = ['car' => ['dash' => 'Dashboard', 'items' => 'Inventory', 'vat' => 'VAT / Mushak', 'purchases' => 'Purchases', 'sales' => 'Sales', 'customers' => 'Customers', 'expenses' => 'Expenses', 'audit' => 'Activity log'],
              'truck' => ['tdash' => 'Overview', 'trips' => 'Trips & Routes', 'trucks' => 'Trucks', 'texp' => 'Truck expenses', 'audit' => 'Activity log']];
function page($title, $body) { global $E;
  $p = $_GET['p'] ?? 'home'; if (isset(SECP[$p])) $_SESSION['sec'] = SECP[$p]; $sec = SECP[$p] ?? ($_SESSION['sec'] ?? 'car'); $nav = '';
  if (me()) {
    if ($p !== 'home') { $nav .= '<span class="tag">' . ($sec === 'car' ? 'Car section' : 'Truck business section') . '</span>';
      foreach (TABS[$sec] as $k => $l) { if ($k === 'audit' && !chair()) continue; if ($k === 'vat' && !can('vat', 'r')) continue; if (isset($E[$k]) && !can($k, 'r')) continue;
        $on = $p === $k || ($k === 'items' && $p === 'car'); $nav .= '<a href="?p=' . $k . '"' . ($on ? ' class="on"' : '') . '>' . h($l) . '</a>'; } }
    $nav = '<nav>' . $nav . '<span style="margin-left:auto;display:flex;gap:6px"><a href="?p=home">Switch section</a><a href="?p=pw">Password</a><form method="post" style="margin:0">' . tok() . '<input type="hidden" name="a" value="logout"><button class="b">Log out (' . h(me()['username']) . ')</button></form></span></nav>'; }
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . h($title) . ' – Auto &amp; Truck Tracker</title><style>
:root{--bg:#f4f6f8;--cd:#fff;--tx:#1b2430;--mu:#667085;--bd:#e1e5ea;--pr:#1f4e78;--ng:#c0392b;--ok:#1e8449;--hd:#eef2f6}
@media(prefers-color-scheme:dark){:root{--bg:#12161c;--cd:#1b222b;--tx:#e8ecf1;--mu:#98a2b3;--bd:#2d3744;--pr:#5aa0dc;--ng:#ff7b6b;--ok:#4cc38a;--hd:#232c37}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--tx);font:14px/1.4 -apple-system,"Segoe UI",Roboto,Arial,sans-serif}
header{padding:14px 16px 4px}h1{font-size:18px;margin:0;color:var(--pr)}.sub{color:var(--mu);font-size:12px}h3{margin:16px 0 6px;font-size:14px}
nav{display:flex;gap:6px;overflow-x:auto;padding:8px 16px;align-items:center}nav a,.b{white-space:nowrap;border:1px solid var(--bd);background:var(--cd);color:var(--tx);padding:8px 12px;border-radius:8px;font:inherit;text-decoration:none;cursor:pointer}
nav a.on,.p{background:var(--pr);color:#fff;border-color:var(--pr)}.tag{padding:2px 10px;border-radius:99px;background:var(--hd);font-size:12px;white-space:nowrap}main{padding:8px 16px 40px}
.bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:6px 0 10px}.bar h2{font-size:16px;margin:0 auto 0 0}
input,select{font:inherit;color:var(--tx);background:var(--cd);border:1px solid var(--bd);border-radius:8px;padding:8px 10px;width:100%}.bar input{width:auto;min-width:220px}
.w{overflow-x:auto;border:1px solid var(--bd);border-radius:10px;background:var(--cd)}table{border-collapse:collapse;width:100%;min-width:600px}
th,td{padding:8px 10px;border-bottom:1px solid var(--bd);text-align:left;white-space:nowrap}th{background:var(--hd);font-size:12px;color:var(--mu)}
.r{text-align:right}.tot td{font-weight:700;background:var(--hd)}.neg{color:var(--ng)}.pos{color:var(--ok)}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:12px}.card{background:var(--cd);border:1px solid var(--bd);border-radius:10px;padding:12px}
.card b{display:block;font-size:18px}.card span{color:var(--mu);font-size:12px}.g{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;background:var(--cd);border:1px solid var(--bd);border-radius:10px;padding:14px}
.g label{display:flex;flex-direction:column;font-size:12px;color:var(--mu);gap:3px}.e{color:var(--ng);margin:8px 0}.ok{color:var(--ok);margin:8px 0}.box{max-width:360px;margin:60px auto;background:var(--cd);border:1px solid var(--bd);border-radius:12px;padding:20px}
.box input{margin:6px 0 12px}.lk{background:none;border:0;color:var(--pr);cursor:pointer;font:inherit;padding:0 6px;text-decoration:underline}
.pick{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;max-width:760px;margin:20px auto}.pick a{display:block;background:var(--cd);color:var(--tx);border:1px solid var(--bd);border-radius:14px;padding:22px;text-decoration:none}
.pick a:hover{border-color:var(--pr)}.pick b{display:block;font-size:18px;color:var(--pr);margin:6px 0}.pick span{color:var(--mu);font-size:13px;display:block;margin-top:6px}.pick i{font-size:30px;font-style:normal}
</style></head><body><header><h1>Auto &amp; Truck Tracker</h1><div class="sub">Japanese reconditioned cars · truck transport and routes — amounts in BDT (৳)</div></header>' . $nav . '<main>' . $body . '</main></body></html>'; exit; }

/* ---------- session / auth ---------- */
if (me() && time() - ($_SESSION['t'] ?? 0) > 1800) { session_unset(); session_destroy(); go('index.php?timeout=1'); }
if (me()) $_SESSION['t'] = time();
if (me() && empty($_SESSION['mg'])) { db()->exec("CREATE TABLE IF NOT EXISTS files(id INT AUTO_INCREMENT PRIMARY KEY, rec_id INT NOT NULL, kind VARCHAR(12) NOT NULL, orig VARCHAR(120) NOT NULL, stored VARCHAR(60) NOT NULL, mime VARCHAR(40) NOT NULL, size INT NOT NULL, uploaded_by INT NOT NULL, uploaded_at DATETIME NOT NULL, deleted_at DATETIME NULL, KEY(rec_id,deleted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); $_SESSION['mg'] = 1; }
$msg = ''; $err = ''; $formRow = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals(csrf(), (string)($_POST['_c'] ?? ''))) { http_response_code(400); exit('Session expired. Go back and reload the page.'); }
  $a = $_POST['a'] ?? '';
  if ($a === 'login') {
    $u = db()->prepare("SELECT * FROM users WHERE username=? AND active=1"); $u->execute([trim((string)($_POST['username'] ?? ''))]); $u = $u->fetch();
    if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) { $err = 'Too many failed attempts. Try again in 15 minutes.'; }
    else {
      $ok = password_verify((string)($_POST['password'] ?? ''), $u['pass_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012346');
      if ($ok && $u) { session_regenerate_id(true); $_SESSION['u'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'name' => $u['name'], 'role' => $u['role']]; $_SESSION['t'] = time(); $_SESSION['c'] = bin2hex(random_bytes(24));
        db()->prepare("UPDATE users SET fails=0,locked_until=NULL WHERE id=?")->execute([$u['id']]); logA('login'); go('?p=home'); }
      if ($u) { $f = $u['fails'] + 1; db()->prepare("UPDATE users SET fails=?,locked_until=? WHERE id=?")->execute([$f >= 5 ? 0 : $f, $f >= 5 ? date('Y-m-d H:i:s', time() + 900) : null, $u['id']]); }
      logA('login_failed'); $err = 'Wrong username or password.'; usleep(400000);
    }
  } elseif (!me()) { go('index.php'); }
  elseif ($a === 'logout') { logA('logout'); session_unset(); session_destroy(); go('index.php'); }
  elseif ($a === 'pw') {
    $uid = me()['id']; $new = (string)($_POST['new'] ?? '');
    if (chair() && !empty($_POST['uid'])) $uid = (int)$_POST['uid'];
    $row = db()->prepare("SELECT pass_hash FROM users WHERE id=?"); $row->execute([$uid]); $row = $row->fetch();
    if (!$row) $err = 'User not found.';
    elseif ($uid === me()['id'] && !password_verify((string)($_POST['cur'] ?? ''), $row['pass_hash'])) $err = 'Current password is wrong.';
    elseif (strlen($new) < 10) $err = 'New password must be at least 10 characters.';
    elseif ($new !== ($_POST['new2'] ?? '')) $err = 'The two new passwords do not match.';
    else { db()->prepare("UPDATE users SET pass_hash=?,fails=0,locked_until=NULL WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $uid]); logA('password', 'users', $uid); $msg = 'Password changed.'; }
    $_GET['p'] = 'pw';
  } elseif ($a === 'vatsave' || $a === 'upload' || $a === 'fdel') {
    if (!can('vat', 'w') || ($a === 'fdel' && !chair())) { http_response_code(403); exit('Not allowed.'); }
    $id = (int)($_POST['id'] ?? 0);
    $o = db()->prepare("SELECT data FROM records WHERE id=? AND entity='items' AND deleted_at IS NULL"); $o->execute([$id]); $o = $o->fetch();
    if (!$o) { http_response_code(404); exit('Not found.'); }
    $d = json_decode($o['data'], true) ?: []; $_GET['p'] = 'car'; $_GET['id'] = $id;
    if ($a === 'vatsave') {
      $n = []; foreach (['m63no', 'm63date', 'm43', 'm43ref', 'm91'] as $k) $n[$k] = mb_substr(trim((string)($_POST[$k] ?? '')), 0, 60);
      if ($n['m63date'] !== '' && !preg_match('/^\d{4}-\d\d-\d\d$/', $n['m63date'])) $err = 'Mushak 6.3 date is not a valid date.';
      elseif (!in_array($n['m43'], ['', 'Yes', 'No'], true)) $err = 'Mushak 4.3: choose Yes or No.';
      elseif ($n['m91'] !== '' && !preg_match('/^\d{4}-\d\d$/', $n['m91'])) $err = 'Mushak 9.1 month must look like 2026-10.';
      else { $diff = []; foreach ($n as $k => $v) if (($d[$k] ?? '') != $v) $diff[$k] = [$d[$k] ?? '', $v];
        db()->prepare("UPDATE records SET data=?,updated_at=NOW() WHERE id=?")->execute([json_encode($n + $d, JSON_UNESCAPED_UNICODE), $id]);
        logA('vat_edit', 'items', $id, json_encode($diff, JSON_UNESCAPED_UNICODE)); $_SESSION['fl'] = 'VAT details saved.'; go('?p=car&id=' . $id); }
    } elseif ($a === 'fdel') {
      db()->prepare("UPDATE files SET deleted_at=NOW() WHERE id=? AND rec_id=? AND deleted_at IS NULL")->execute([(int)($_POST['fid'] ?? 0), $id]);
      logA('file_delete', 'files', (int)($_POST['fid'] ?? 0)); $_SESSION['fl'] = 'File removed (the stored copy is kept on the server).'; go('?p=car&id=' . $id);
    } else {
      $k = $_POST['kind'] ?? ''; $f = $_FILES['file'] ?? null;
      if (!isset(KINDS[$k])) $err = 'Unknown document type.';
      elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) $err = 'Upload failed. The file may be larger than the server limit (' . ini_get('upload_max_filesize') . ').';
      elseif ($f['size'] > 5 * 1024 * 1024) $err = 'File is larger than 5 MB.';
      else {
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])] ?? null;
        $dir = DATA_DIR . '/files';
        if (!$ext) $err = 'Only PDF, JPG or PNG files are allowed.';
        elseif (!is_dir($dir) && !@mkdir($dir, 0700, true)) $err = 'Cannot create the storage folder. Check DATA_DIR in config.php.';
        else { $sn = bin2hex(random_bytes(16)) . '.' . $ext;
          if (!@move_uploaded_file($f['tmp_name'], "$dir/$sn")) $err = 'Could not save the file. Check the folder permissions.';
          else { $on = mb_substr(preg_replace('/[^\w. \-()]+/u', '_', basename((string)$f['name'])), 0, 100);
            db()->prepare("INSERT INTO files(rec_id,kind,orig,stored,mime,size,uploaded_by,uploaded_at) VALUES(?,?,?,?,?,?,?,NOW())")
              ->execute([$id, $k, $on, $sn, $ext === 'pdf' ? 'application/pdf' : ($ext === 'jpg' ? 'image/jpeg' : 'image/png'), (int)$f['size'], me()['id']]);
            logA('upload', 'files', (int)db()->lastInsertId(), $k . ': ' . $on); $_SESSION['fl'] = 'File uploaded.'; go('?p=car&id=' . $id); } } }
    }
  } elseif ($a === 'save' || $a === 'del') {
    $ent = $_POST['e'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    if (!isset($E[$ent]) || !can($ent, 'w') || ($a === 'del' && !chair())) { http_response_code(403); exit('Not allowed.'); }
    $_GET['p'] = $ent;
    if ($a === 'del') {
      if (empty($_POST['ok'])) { page('Delete', '<div class="box"><p>Delete this record? It is hidden from the app but kept in the database and the activity log.</p><form method="post">' . tok() . '<input type="hidden" name="a" value="del"><input type="hidden" name="e" value="' . h($ent) . '"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="ok" value="1"><button class="b p">Yes, delete</button> <a class="b" href="?p=' . h($ent) . '">Cancel</a></form></div>'); }
      db()->prepare("UPDATE records SET deleted_at=NOW() WHERE id=? AND entity=? AND deleted_at IS NULL")->execute([$id, $ent]); logA('delete', $ent, $id); go('?p=' . $ent);
    }
    $d = []; $old = null;
    if ($id) { $o = db()->prepare("SELECT data FROM records WHERE id=? AND entity=? AND deleted_at IS NULL"); $o->execute([$id, $ent]); $o = $o->fetch(); if (!$o) { http_response_code(404); exit('Not found.'); } $old = json_decode($o['data'], true) ?: []; }
    foreach ($E[$ent]['f'] as $f) { [$k, $l, $t] = $f; $v = trim((string)($_POST[$k] ?? ''));
      if ($t === 'n') { if ($v !== '' && !is_numeric($v)) $err = "$l must be a number."; $d[$k] = $v === '' ? '' : +$v; }
      elseif ($t === 'd') { if ($v !== '' && !preg_match('/^\d{4}-\d\d-\d\d$/', $v)) $err = "$l is not a valid date."; $d[$k] = $v; }
      elseif ($t === 's') { if ($v !== '' && !in_array($v, array_column(opts($f), 0), true)) $err = "$l: please choose from the list."; $d[$k] = $v; }
      else $d[$k] = mb_substr($v, 0, 200); }
    if ($old) foreach (['m63no', 'm63date', 'm43', 'm43ref', 'm91'] as $k) if (isset($old[$k])) $d[$k] = $old[$k];   // keep VAT details when the car is edited
    foreach ($E[$ent]['r'] as $rk) if (!$err && ($d[$rk] ?? '') === '') $err = lab($ent, $rk) . ' is required.';
    if (!$err) { $uk = ['items' => 'code', 'trucks' => 'reg'][$ent] ?? null;
      if ($uk) foreach (S()[$ent] as $x) if ((string)$x[$uk] === (string)$d[$uk] && $x['id'] !== $id) $err = 'This ' . lab($ent, $uk) . ' already exists.'; }
    if (!$err && $ent === 'sales') { $ex = SL($d['item']); if ($ex && $ex['id'] !== $id) $err = 'This car is already sold (invoice ' . ($ex['inv'] ?? '') . ').'; }
    if (!$err && $ent === 'trips') { if ($d['ty'] === 'Own car delivery' && $d['car'] === '') $err = 'Choose the car being delivered.'; if ($d['driver'] === '') $d['driver'] = truck($d['truck'])['driver'] ?? ''; }
    if ($err) { $formRow = $d + ['id' => $id]; $_GET['f'] = $id ?: 'new'; }
    else {
      if ($id) { db()->prepare("UPDATE records SET data=?,updated_at=NOW() WHERE id=?")->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $id]);
        $diff = []; foreach ($d as $k => $v) if (($old[$k] ?? '') != $v) $diff[$k] = [$old[$k] ?? '', $v]; logA('edit', $ent, $id, json_encode($diff, JSON_UNESCAPED_UNICODE)); }
      else { db()->prepare("INSERT INTO records(entity,data,created_by,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())")->execute([$ent, json_encode($d, JSON_UNESCAPED_UNICODE), me()['id']]);
        logA('add', $ent, (int)db()->lastInsertId(), json_encode($d, JSON_UNESCAPED_UNICODE)); }
      go('?p=' . $ent);
    }
  }
}

if (!me()) {
  page('Login', '<div class="box"><h2 style="margin-top:0">Sign in</h2>' . (isset($_GET['timeout']) ? '<div class="e">Logged out after 30 minutes of inactivity.</div>' : '') . ($err ? '<div class="e">' . h($err) . '</div>' : '')
    . '<form method="post" autocomplete="off">' . tok() . '<input type="hidden" name="a" value="login"><label>Username<input name="username" required autofocus></label><label>Password<input name="password" type="password" required></label><button class="b p" style="width:100%">Sign in</button></form></div>');
}

/* ---------- pages ---------- */
$p = $_GET['p'] ?? 'home';
$cd = fn($l, $v, $c = '') => '<div class="card"><span>' . $l . '</span><b class="' . $c . '">' . $v . '</b></div>';
$tbl = function ($heads, $rows) { $o = '<div class="w"><table><tr>'; foreach ($heads as $i => $hh) $o .= '<th class="' . ($i ? 'r' : '') . '">' . h($hh) . '</th>'; $o .= '</tr>';
  foreach ($rows as $r) { $o .= '<tr>'; foreach ($r as $i => $v) $o .= '<td class="' . ($i ? 'r' : '') . (is_numeric($v) && $v < 0 ? ' neg' : '') . '">' . ($i ? (is_string($v) ? h($v) : m($v)) : h($v)) . '</td>'; $o .= '</tr>'; }
  return $o . '</table></div>'; };

if ($p === 'home') {
  $nI = count(S()['items']); $nS = count(array_filter(S()['items'], 'sold')); $inc = count(array_filter(S()['items'], fn($x) => !empty(vatMiss($x))));
  $ym = date('Y-m'); $nT = count(array_filter(S()['trips'], fn($x) => strpos($x['date'] ?? '', $ym) === 0)); $ex = 0;
  foreach (S()['trucks'] as $t) foreach (expList($t) as $e) if ($e[2] <= 30) $ex++;
  page('Home', '<div style="text-align:center;margin-top:18px"><h2 style="margin:0">Welcome, ' . h(me()['name']) . '</h2><div class="sub">Choose the section you want to work in</div></div><div class="pick">'
    . '<a href="?p=dash"><i>🚗</i><b>Car section</b><span>Imports, inventory, VAT / Mushak files, sales, customers and expenses for Japanese reconditioned cars.</span><span>' . $nI . ' cars · ' . $nS . ' sold · ' . $inc . ' with incomplete VAT files</span></a>'
    . '<a href="?p=tdash"><i>🚚</i><b>Truck business section</b><span>Trips and routes, trucks and drivers, repairs, tyres, salaries, insurance and freight income.</span><span>' . $nT . ' trips this month · ' . $ex . ' truck documents due within 30 days</span></a></div>');
}
if ($p === 'dash') {
  $pur = sm(S()['purchases'], fn($x) => pc($x)['landed']); $sal = sm(S()['sales'], fn($x) => sc($x)['total']); $cg = sm(S()['sales'], fn($x) => sc($x)['cost']); $due = sm(S()['sales'], fn($x) => sc($x)['due']);
  $own = sm(S()['trips'], fn($x) => ($x['ty'] ?? '') === 'Own car delivery' ? dc($x)['cost'] : 0);
  $cx = can('expenses', 'r'); $ex = $cx ? sm(S()['expenses'], fn($x) => N($x['amt'] ?? 0)) : 0;
  $sv = sm(array_filter(S()['items'], fn($x) => !sold($x)), fn($x) => LC($x['code'])); $net = $sal - $cg - $own - $ex;
  $inc = count(array_filter(S()['items'], fn($x) => !empty(vatMiss($x)))); $cnt = fn($sts) => count(array_filter(S()['items'], fn($x) => in_array(carStat($x), $sts)));
  page('Dashboard', '<div class="cards">' . $cd($cx ? 'Net profit' : 'Net profit (before expenses)', m($net), $net < 0 ? 'neg' : 'pos') . $cd('Stock value (unsold cars)', m($sv)) . $cd('Cars in stock', $cnt(['In Stock'])) . $cd('Cars on the way', $cnt(['Ordered', 'Shipped', 'At Port', 'Cleared']))
    . $cd('Due from customers', m($due)) . $cd('Sold cars: VAT files incomplete', $inc, $inc ? 'neg' : 'pos') . '</div>'
    . $tbl(['Car section', 'Purchases (landed)', 'Sales', 'Cost of cars sold', 'Gross profit', 'Delivery costs', 'Expenses', 'Net profit'], [['All cars', $pur, $sal, $cg, $sal - $cg, $own, $ex, $net]])
    . '<p class="sub">Delivery costs = trips of your own trucks that carried sold cars to customers (Truck section). Net profit = gross profit − delivery costs − expenses.</p>');
}
if ($p === 'tdash') {
  $tr = S()['trips']; $cl = array_filter($tr, fn($x) => ($x['ty'] ?? '') === 'Client job'); $ow = array_filter($tr, fn($x) => ($x['ty'] ?? '') !== 'Client job');
  $inc = sm($cl, fn($x) => N($x['inc'] ?? 0)); $cc = sm($cl, fn($x) => dc($x)['cost']); $oc = sm($ow, fn($x) => dc($x)['cost']); $tx = sm(S()['texp'], fn($x) => N($x['amt'] ?? 0)); $net = $inc - $cc - $tx;
  $al = []; foreach (S()['trucks'] as $t) foreach (expList($t) as $e) if ($e[2] <= 30) $al[] = [$t['reg'], $e[0], $e[1], $e[2]];
  $pend = count(array_filter($tr, fn($x) => in_array($x['status'] ?? '', ['Planned', 'In Transit'])));
  $o = '<div class="cards">' . $cd('Net profit (client jobs)', m($net), $net < 0 ? 'neg' : 'pos') . $cd('Freight income', m($inc)) . $cd('Client-job trip costs', m($cc)) . $cd('Truck expenses', m($tx)) . $cd('Own car deliveries (cost)', m($oc)) . $cd('Trips not delivered yet', $pend) . $cd('Documents due in 30 days', count($al), $al ? 'neg' : 'pos') . '</div>';
  if ($al) { $o .= '<h3>Document reminders</h3><div class="w"><table><tr><th>Truck</th><th>Document</th><th>Expires</th><th>Days left</th></tr>'; usort($al, fn($a, $b) => $a[3] <=> $b[3]);
    foreach ($al as $x) $o .= '<tr><td>' . h($x[0]) . '</td><td>' . h($x[1]) . '</td><td>' . h($x[2]) . '</td><td class="neg">' . ($x[3] < 0 ? 'Overdue by ' . abs($x[3]) : $x[3]) . '</td></tr>'; $o .= '</table></div>'; }
  $rows = []; foreach (S()['trucks'] as $t) { $s = TS($t['reg']); $rows[] = [$t['reg'] . ' – ' . ($t['model'] ?? ''), (string)$s['n'], number_format($s['km']) . ' km', $s['inc'], $s['cc'], $s['own'], $s['exp'], $s['net']]; }
  $o .= '<h3>By truck</h3>' . $tbl(['Truck', 'Trips', 'Distance', 'Freight income', 'Client-job costs', 'Own-delivery cost', 'Truck expenses', 'Net profit'], $rows);
  $mo = []; foreach ($tr as $r) { $k = substr($r['date'] ?? '', 0, 7); if ($k === '') continue; $c = dc($r)['cost']; $mo[$k]['n'] = ($mo[$k]['n'] ?? 0) + 1;
    if (($r['ty'] ?? '') === 'Client job') { $mo[$k]['inc'] = ($mo[$k]['inc'] ?? 0) + N($r['inc'] ?? 0); $mo[$k]['cc'] = ($mo[$k]['cc'] ?? 0) + $c; } else $mo[$k]['own'] = ($mo[$k]['own'] ?? 0) + $c; }
  foreach (S()['texp'] as $r) { $k = substr($r['date'] ?? '', 0, 7); if ($k === '') continue; $mo[$k]['ex'] = ($mo[$k]['ex'] ?? 0) + N($r['amt'] ?? 0); }
  krsort($mo); $rows = []; foreach ($mo as $k => $v) { $inc2 = $v['inc'] ?? 0; $rows[] = [$k, (string)($v['n'] ?? 0), $inc2, $v['cc'] ?? 0, $v['own'] ?? 0, $v['ex'] ?? 0, $inc2 - ($v['cc'] ?? 0) - ($v['ex'] ?? 0)]; }
  $o .= '<h3>By month</h3>' . $tbl(['Month', 'Trips', 'Freight income', 'Client-job costs', 'Own-delivery cost', 'Truck expenses', 'Net profit'], $rows);
  $cats = []; foreach (S()['texp'] as $r) $cats[$r['cat'] ?? 'Other'] = ($cats[$r['cat'] ?? 'Other'] ?? 0) + N($r['amt'] ?? 0); arsort($cats);
  $o .= '<h3>Truck expenses by category (all time)</h3>' . $tbl(['Category', 'Amount'], array_map(fn($k, $v) => [$k ?: 'Other', $v], array_keys($cats), $cats));
  page('Truck overview', $o . '<p class="sub">Net profit = freight income − client-job trip costs − truck expenses (repairs, tyres, salaries, insurance…). Own car delivery trips earn no freight income; their cost is shown separately and counted as delivery cost in the Car section.</p>');
}
if ($p === 'pw') {
  $us = chair() ? db()->query("SELECT id,username FROM users ORDER BY id")->fetchAll() : [];
  $o = ($msg ? '<div class="ok">' . h($msg) . '</div>' : '') . ($err ? '<div class="e">' . h($err) . '</div>' : '') . '<form method="post" class="g" style="max-width:420px;grid-template-columns:1fr">' . tok() . '<input type="hidden" name="a" value="pw">';
  if ($us) { $o .= '<label>Change password for<select name="uid"><option value="">My own account</option>'; foreach ($us as $x) $o .= '<option value="' . $x['id'] . '">' . h($x['username']) . '</option>'; $o .= '</select></label>'; }
  $o .= '<label>Current password (own account only)<input type="password" name="cur" autocomplete="current-password"></label><label>New password (min 10 characters)<input type="password" name="new" autocomplete="new-password"></label><label>Repeat new password<input type="password" name="new2" autocomplete="new-password"></label><button class="b p">Change password</button></form>';
  page('Password', $o);
}
if ($p === 'audit' && chair()) {
  $o = '<div class="bar"><h2>Activity log <span class="sub">(last 300)</span></h2></div><div class="w"><table><tr><th>When</th><th>User</th><th>Action</th><th>Area</th><th>Record</th><th>Details</th><th>IP</th></tr>';
  foreach (db()->query("SELECT * FROM audit ORDER BY id DESC LIMIT 300") as $r) $o .= '<tr><td>' . h($r['at']) . '</td><td>' . h($r['username']) . '</td><td>' . h($r['action']) . '</td><td>' . h($r['entity']) . '</td><td>' . h($r['rec_id']) . '</td><td style="white-space:normal;max-width:420px;word-break:break-word">' . h($r['detail']) . '</td><td>' . h($r['ip']) . '</td></tr>';
  page('Activity log', $o . '</table></div>');
}
if ($p === 'file') {
  if (!can('vat', 'r')) go('?p=home');
  $r = db()->prepare("SELECT * FROM files WHERE id=? AND deleted_at IS NULL"); $r->execute([(int)($_GET['id'] ?? 0)]); $r = $r->fetch();
  $path = $r ? DATA_DIR . '/files/' . basename($r['stored']) : '';
  if (!$r || !is_file($path)) { http_response_code(404); exit('File not found.'); }
  logA('download', 'files', (int)$r['id']);
  header('Content-Type: ' . $r['mime']); header('Content-Length: ' . filesize($path));
  header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $r['orig']) . '"'); readfile($path); exit;
}
if ($p === 'car') {
  if (!can('items', 'r')) go('?p=home');
  $id = (int)($_GET['id'] ?? 0); $it = null; foreach (S()['items'] as $x) if ($x['id'] === $id) $it = $x; if (!$it) go('?p=items');
  $msg = $msg ?: ($_SESSION['fl'] ?? ''); unset($_SESSION['fl']);
  $miss = vatMiss($it); $wv = can('vat', 'w'); $pv = ($_POST['a'] ?? '') === 'vatsave'; $sl = SL($it['code']);
  $vv = fn($k) => $pv ? ($_POST[$k] ?? '') : ($it[$k] ?? '');
  $o = '<div class="bar"><h2>' . h($it['code']) . ' – ' . h($it['name'] ?? '') . '</h2><a class="b" href="?p=items">← Inventory</a></div>' . ($msg ? '<div class="ok">' . h($msg) . '</div>' : '') . ($err ? '<div class="e">' . h($err) . '</div>' : '');
  $o .= '<div class="card" style="margin-bottom:12px"><span>VAT file status</span><b>' . ($miss === null ? '<span class="sub">Checked once the car is sold</span>' : ($miss ? '<span class="neg">Incomplete – missing: ' . h(implode(', ', $miss)) . '</span>' : '<span class="pos">Complete – all VAT files present</span>')) . '</b></div>';
  $kv = fn($k, $v) => '<tr><th style="width:160px">' . h($k) . '</th><td style="white-space:normal">' . h($v) . '</td></tr>';
  $o .= '<div class="w" style="margin-bottom:12px"><table>' . $kv('Status', carStat($it)) . $kv('Make / model / year / colour', trim(($it['make'] ?? '') . ' ' . ($it['model'] ?? '') . ' ' . ($it['year'] ?? '') . ' ' . ($it['colour'] ?? ''))) . $kv('Landed cost', m(LC($it['code']))) . $kv('Asking price', m($it['asking'] ?? '')) . '</table></div>';
  $o .= '<h3>Purchase</h3><div class="w" style="margin-bottom:12px"><table><tr><th>Date</th><th>Supplier</th><th>LC / B/E no.</th><th class="r">Landed cost</th></tr>';
  foreach (S()['purchases'] as $x) if ((string)($x['item'] ?? '') === (string)$it['code']) $o .= '<tr><td>' . h($x['date'] ?? '') . '</td><td>' . h($x['supplier'] ?? '') . '</td><td>' . h($x['ref'] ?? '') . '</td><td class="r">' . m(pc($x)['landed']) . '</td></tr>';
  $o .= '</table></div><h3>Sale &amp; customer</h3><div class="w" style="margin-bottom:12px"><table><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Phone</th><th>NID</th><th>TIN / BIN</th><th class="r">Total</th><th class="r">Due</th></tr>';
  if ($sl) { $c = cust($sl['cust'] ?? ''); $o .= '<tr><td>' . h($sl['date'] ?? '') . '</td><td>' . h($sl['inv'] ?? '') . '</td><td>' . h($c['name'] ?? '') . '</td><td>' . h($c['phone'] ?? '') . '</td><td>' . h($c['nid'] ?? '') . '</td><td>' . h($c['tin'] ?? '') . '</td><td class="r">' . m(sc($sl)['total']) . '</td><td class="r">' . m(sc($sl)['due']) . '</td></tr>'; }
  $o .= '</table></div><h3>VAT / Mushak details</h3>';
  if ($wv) $o .= '<form method="post" class="g" style="margin-bottom:12px">' . tok() . '<input type="hidden" name="a" value="vatsave"><input type="hidden" name="id" value="' . $id . '">'
    . '<label>Mushak 6.3 (tax invoice) no.<input name="m63no" value="' . h($vv('m63no')) . '"></label><label>Mushak 6.3 date<input type="date" name="m63date" value="' . h($vv('m63date')) . '"></label>'
    . '<label>Mushak 4.3 done?<select name="m43"><option value=""></option><option' . ($vv('m43') === 'Yes' ? ' selected' : '') . '>Yes</option><option' . ($vv('m43') === 'No' ? ' selected' : '') . '>No</option></select></label>'
    . '<label>Mushak 4.3 reference / page no.<input name="m43ref" value="' . h($vv('m43ref')) . '"></label><label>Mushak 9.1 return month<input type="month" name="m91" value="' . h($vv('m91')) . '"></label>'
    . '<label>&nbsp;<button class="b p">Save VAT details</button></label></form>';
  else $o .= '<div class="w" style="margin-bottom:12px"><table>' . $kv('Mushak 6.3 no. / date', ($it['m63no'] ?? '') . ' ' . ($it['m63date'] ?? '')) . $kv('Mushak 4.3', ($it['m43'] ?? '') . ' ' . ($it['m43ref'] ?? '')) . $kv('Mushak 9.1 month', $it['m91'] ?? '') . '</table></div>';
  $fs = db()->prepare("SELECT f.id,f.kind,f.orig,f.size,f.uploaded_at,u.username FROM files f LEFT JOIN users u ON u.id=f.uploaded_by WHERE f.rec_id=? AND f.deleted_at IS NULL ORDER BY f.id"); $fs->execute([$id]);
  $by = []; foreach ($fs->fetchAll() as $r) $by[$r['kind']][] = $r;
  $o .= '<h3>Documents (PDF, JPG or PNG, max 5 MB each)</h3><div class="w"><table><tr><th>Document</th><th>Status</th><th>Files</th><th>' . ($wv ? 'Upload' : '') . '</th></tr>';
  foreach (KINDS as $k => $lab) { $o .= '<tr><td>' . h($lab) . '</td><td>' . tick(!empty($by[$k])) . '</td><td>';
    foreach ($by[$k] ?? [] as $f) { $o .= '<div><a class="lk" href="?p=file&id=' . $f['id'] . '">' . h($f['orig']) . '</a> <span class="sub">' . h(substr($f['uploaded_at'], 0, 10)) . ' · ' . h($f['username']) . '</span>';
      if (chair()) $o .= '<form method="post" style="display:inline">' . tok() . '<input type="hidden" name="a" value="fdel"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="fid" value="' . $f['id'] . '"><button class="lk">Remove</button></form>';
      $o .= '</div>'; }
    $o .= '</td><td>' . ($wv ? '<form method="post" enctype="multipart/form-data">' . tok() . '<input type="hidden" name="a" value="upload"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="kind" value="' . $k . '"><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" style="width:auto;max-width:220px"> <button class="b">Upload</button></form>' : '') . '</td></tr>'; }
  page('Vehicle file', $o . '</table></div>');
}
if ($p === 'vat') {
  if (!can('vat', 'r')) go('?p=home');
  $rows = [];
  foreach (S()['items'] as $i) { if (!sold($i)) continue; $s = SL($i['code']); $rows[] = ['i' => $i, 'inv' => $s['inv'] ?? '', 'cn' => custName($s['cust'] ?? ''), 'dt' => (string)($s['date'] ?? ''), 'miss' => vatMiss($i)]; }
  $all = count($rows); $inc = count(array_filter($rows, fn($r) => $r['miss']));
  $qq = trim((string)($_GET['q'] ?? '')); $show = $_GET['show'] ?? '';
  if ($show === 'missing') $rows = array_values(array_filter($rows, fn($r) => $r['miss']));
  if ($qq !== '') $rows = array_values(array_filter($rows, fn($r) => mb_stripos(itemText($r['i']), $qq) !== false));
  usort($rows, fn($a, $b) => strcmp($b['dt'], $a['dt']));
  if (isset($_GET['csv'])) { logA('export', 'vat'); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="vat_report.csv"'); echo "\xEF\xBB\xBF"; $out = fopen('php://output', 'w');
    fputcsv($out, ['Chassis', 'Car', 'Sale date', 'Invoice no', 'Customer', 'Mushak 6.3 no', 'Mushak 6.3 date', 'Mushak 4.3', 'Mushak 4.3 ref', 'Mushak 9.1 month', 'Invoice PDF', 'NID', 'TIN', 'Status']);
    foreach ($rows as $r) { $i = $r['i']; fputcsv($out, [$i['code'], $i['name'] ?? '', $r['dt'], $r['inv'], $r['cn'], $i['m63no'] ?? '', $i['m63date'] ?? '', $i['m43'] ?? '', $i['m43ref'] ?? '', $i['m91'] ?? '', FC($i['id'], 'invoice'), FC($i['id'], 'nid'), FC($i['id'], 'tin'), $r['miss'] ? 'Missing: ' . implode(', ', $r['miss']) : 'Complete']); } exit; }
  $o = '<div class="bar"><h2>VAT / Mushak file check <span class="sub">(sold cars)</span></h2><form method="get" class="bar" style="margin:0"><input type="hidden" name="p" value="vat"><input name="q" placeholder="Chassis, customer, NID, TIN, invoice…" value="' . h($qq) . '" style="min-width:240px"></form>'
     . '<a class="b' . ($show === 'missing' ? ' p' : '') . '" href="?p=vat' . ($show === 'missing' ? '' : '&show=missing') . '">' . ($show === 'missing' ? 'Showing: incomplete only' : 'Show incomplete only') . '</a><a class="b" href="?p=vat&csv=1' . ($show ? '&show=' . h($show) : '') . '">Export CSV</a></div>'
     . '<div class="cards">' . $cd('Cars sold', $all) . $cd('Complete', $all - $inc, 'pos') . $cd('Incomplete', $inc, $inc ? 'neg' : '') . '</div>'
     . '<div class="w"><table><tr><th>Chassis</th><th>Car</th><th>Sale date</th><th>Invoice</th><th>Customer</th><th>6.3 no.</th><th>4.3</th><th>9.1 month</th><th>Inv</th><th>NID</th><th>TIN</th><th>Status</th><th></th></tr>';
  foreach ($rows as $r) { $i = $r['i'];
    $o .= '<tr><td>' . h($i['code']) . '</td><td>' . h($i['name'] ?? '') . '</td><td>' . h($r['dt']) . '</td><td>' . h($r['inv']) . '</td><td>' . h($r['cn']) . '</td><td>' . h($i['m63no'] ?? '') . '</td><td>' . h($i['m43'] ?? '') . '</td><td>' . h($i['m91'] ?? '') . '</td><td>' . tick(FC($i['id'], 'invoice')) . '</td><td>' . tick(FC($i['id'], 'nid')) . '</td><td>' . tick(FC($i['id'], 'tin')) . '</td>'
       . '<td class="' . ($r['miss'] ? 'neg' : 'pos') . '">' . ($r['miss'] ? 'Missing ' . count($r['miss']) : 'Complete') . '</td><td><a class="lk" href="?p=car&id=' . $i['id'] . '">Open file</a></td></tr>'; }
  page('VAT / Mushak', $o . '</table>' . (!$rows ? '<div style="padding:24px;text-align:center" class="sub">No sold cars to show.</div>' : '') . '</div><p class="sub">A sold car is “Complete” when it has a Mushak 6.3 number, Mushak 4.3 marked Yes, a Mushak 9.1 return month, and the customer invoice, NID and TIN files uploaded.</p>');
}

/* ---------- generic list / form ---------- */
if (!isset($E[$p]) || !can($p, 'r')) go('?p=home');
$e = $E[$p]; $w = can($p, 'w'); $rows = S()[$p];
if (isset($_GET['f']) && $w) {
  $fid = $_GET['f'] === 'new' ? 0 : (int)$_GET['f']; $r = $formRow;
  if (!$r && $fid) foreach ($rows as $x) if ($x['id'] === $fid) $r = $x;
  $r = $r ?: [];
  $o = ($err ? '<div class="e">' . h($err) . '</div>' : '') . '<div class="bar"><h2>' . ($fid ? 'Edit' : 'Add') . ' – ' . h($e['t']) . '</h2></div><form method="post"><div class="g">' . tok() . '<input type="hidden" name="a" value="save"><input type="hidden" name="e" value="' . h($p) . '"><input type="hidden" name="id" value="' . $fid . '">';
  foreach ($e['f'] as $f) { $v = $r[$f[0]] ?? '';
    if ($f[2] === 's') { $o .= '<label>' . h($f[1]) . '<select name="' . $f[0] . '"><option value=""></option>'; foreach (opts($f) as $op) $o .= '<option value="' . h($op[0]) . '"' . ((string)$op[0] === (string)$v ? ' selected' : '') . '>' . h($op[1]) . '</option>'; $o .= '</select></label>'; }
    else $o .= '<label>' . h($f[1]) . '<input name="' . $f[0] . '" type="' . ($f[2] === 'n' ? 'number' : ($f[2] === 'd' ? 'date' : 'text')) . '"' . ($f[2] === 'n' ? ' step="any"' : '') . ' value="' . h($v) . '"></label>'; }
  page($e['t'], $o . '</div><div class="bar" style="margin-top:12px"><button class="b p">Save</button><a class="b" href="?p=' . h($p) . '">Cancel</a></div></form>');
}
usort($rows, fn($a, $b) => $e['s'] === 'date' ? strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')) : strcasecmp((string)($a[$e['s']] ?? ''), (string)($b[$e['s']] ?? '')));
$qq = trim((string)($_GET['q'] ?? ''));
if ($qq !== '') $rows = array_values(array_filter($rows, function ($r) use ($e, $qq, $p) { if ($p === 'items' && mb_stripos(itemText($r), $qq) !== false) return true; foreach ($e['f'] as $f) if (mb_stripos(disp($f, $r[$f[0]] ?? ''), $qq) !== false) return true; return false; }));
if (isset($_GET['csv'])) {
  logA('export', $p); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $p . '.csv"'); echo "\xEF\xBB\xBF"; $out = fopen('php://output', 'w');
  $safe = fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v;
  fputcsv($out, array_merge(array_map(fn($f) => $f[1], $e['f']), array_map(fn($c) => $c[0], $e['c'])));
  foreach ($rows as $r) fputcsv($out, array_map($safe, array_merge(array_map(fn($f) => ($f[3] ?? null) === 'cust' ? custName($r[$f[0]] ?? '') : ($r[$f[0]] ?? ''), $e['f']), array_map(fn($c) => $c[1]($r), $e['c'])))); exit;
}
$fs = array_filter($e['f'], fn($f) => !$f[4]);
$o = '<div class="bar"><h2>' . h($e['t']) . ' <span class="sub">(' . count($rows) . ')</span></h2><form method="get" class="bar" style="margin:0"><input type="hidden" name="p" value="' . h($p) . '"><input name="q" placeholder="' . ($p === 'items' ? 'Chassis, customer, NID, TIN, invoice…' : 'Search…') . '" value="' . h($qq) . '"></form>'
  . ($w ? '<a class="b p" href="?p=' . h($p) . '&f=new">+ Add</a>' : '') . '<a class="b" href="?p=' . h($p) . '&csv=1">Export CSV</a></div><div class="w"><table><tr>';
foreach ($fs as $f) $o .= '<th class="' . ($f[2] === 'n' ? 'r' : '') . '">' . h(preg_replace('/ \(.*\)/', '', $f[1])) . '</th>';
foreach ($e['c'] as $c) $o .= '<th class="' . ($c[2] === 't' ? '' : 'r') . '">' . h($c[0]) . '</th>'; if ($p === 'items') $o .= '<th></th>'; $o .= ($w ? '<th></th>' : '') . '</tr>';
foreach ($rows as $r) { $o .= '<tr>';
  foreach ($fs as $f) $o .= '<td class="' . ($f[2] === 'n' ? 'r' : '') . '">' . h(disp($f, $r[$f[0]] ?? '')) . '</td>';
  foreach ($e['c'] as $c) { $v = $c[1]($r); $o .= '<td class="' . ($c[2] === 't' ? '' : 'r') . ' ' . ((is_numeric($v) && $v < 0) || str_starts_with((string)$v, 'Missing') || str_starts_with((string)$v, '⚠') ? 'neg' : '') . '">' . h(fmt($v, $c[2])) . '</td>'; }
  if ($p === 'items') $o .= '<td><a class="lk" href="?p=car&id=' . $r['id'] . '">Vehicle file</a></td>';
  if ($w) { $o .= '<td><a class="lk" href="?p=' . h($p) . '&f=' . $r['id'] . '">Edit</a>';
    if (chair()) $o .= '<form method="post" style="display:inline">' . tok() . '<input type="hidden" name="a" value="del"><input type="hidden" name="e" value="' . h($p) . '"><input type="hidden" name="id" value="' . $r['id'] . '"><button class="lk">Delete</button></form>'; $o .= '</td>'; }
  $o .= '</tr>'; }
page($e['t'], $o . '</table>' . (!$rows ? '<div style="padding:24px;text-align:center" class="sub">Nothing here yet.' . ($p === 'trips' ? ' Add your trucks first, then record trips.' : '') . '</div>' : '') . '</div>');
