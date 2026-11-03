<?php
// RUN ONCE in the browser, copy the passwords shown, then DELETE this file.
define('APP', 1);
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
$db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE IF NOT EXISTS users(
  id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(40) NOT NULL UNIQUE, name VARCHAR(80) NOT NULL,
  role VARCHAR(20) NOT NULL, pass_hash VARCHAR(255) NOT NULL, fails INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL, active TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS records(
  id INT AUTO_INCREMENT PRIMARY KEY, entity VARCHAR(20) NOT NULL, data LONGTEXT NOT NULL,
  created_by INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME NULL,
  KEY(entity, deleted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS audit(
  id INT AUTO_INCREMENT PRIMARY KEY, at DATETIME NOT NULL, user_id INT NULL, username VARCHAR(40),
  action VARCHAR(20) NOT NULL, entity VARCHAR(20), rec_id INT NULL, detail TEXT, ip VARCHAR(45)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS files(
  id INT AUTO_INCREMENT PRIMARY KEY, rec_id INT NOT NULL, kind VARCHAR(12) NOT NULL, orig VARCHAR(120) NOT NULL,
  stored VARCHAR(60) NOT NULL, mime VARCHAR(40) NOT NULL, size INT NOT NULL, uploaded_by INT NOT NULL,
  uploaded_at DATETIME NOT NULL, deleted_at DATETIME NULL, KEY(rec_id, deleted_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if ($db->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) { exit("Setup already done. DELETE setup.php now.\n"); }
$alpha = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$ins = $db->prepare("INSERT INTO users(username,name,role,pass_hash) VALUES(?,?,?,?)");
echo "SAVE THESE PASSWORDS NOW (shown only once). Change them after first login.\n\n";
foreach ([['chairman', 'Chairman', 'chairman'], ['manager', 'Manager', 'manager'], ['accounts', 'Accounts', 'accounts']] as [$u, $n, $r]) {
  $pw = ''; for ($i = 0; $i < 14; $i++) $pw .= $alpha[random_int(0, strlen($alpha) - 1)];
  $ins->execute([$u, $n, $r, password_hash($pw, PASSWORD_DEFAULT)]);
  echo str_pad($u, 10) . " : $pw\n";
}
echo "\nTables created. NOW DELETE setup.php from the server.\n";
