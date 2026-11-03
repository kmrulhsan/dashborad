<?php
// Run from cron only (never from the browser).  See README.txt.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('APP', 1);
require __DIR__ . '/config.php';
$dir = DATA_DIR . '/backups';
if (!is_dir($dir) && !mkdir($dir, 0700, true)) { fwrite(STDERR, "Cannot create $dir\n"); exit(1); }
$stamp = date('Y-m-d-His');
$file = "$dir/db-$stamp.sql.gz";
putenv('MYSQL_PWD=' . DB_PASS);
exec('mysqldump --single-transaction -h ' . escapeshellarg(DB_HOST) . ' -u ' . escapeshellarg(DB_USER) . ' ' . escapeshellarg(DB_NAME) . ' | gzip > ' . escapeshellarg($file), $o, $rc);
if ($rc !== 0 || !file_exists($file) || filesize($file) < 200) { fwrite(STDERR, "DATABASE BACKUP FAILED\n"); exit(1); }
if (is_dir(DATA_DIR . '/files')) {   // uploaded documents
  exec('tar -czf ' . escapeshellarg("$dir/files-$stamp.tar.gz") . ' -C ' . escapeshellarg(DATA_DIR) . ' files', $o2, $rc2);
  if ($rc2 !== 0) { fwrite(STDERR, "FILES BACKUP FAILED\n"); exit(1); }
}
foreach (glob("$dir/*") as $f) if (is_file($f) && filemtime($f) < time() - 45 * 86400) unlink($f);   // keep 45 days
echo "OK $stamp\n";
