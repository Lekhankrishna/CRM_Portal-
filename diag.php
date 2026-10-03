<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "PHP: " . PHP_VERSION . "\n";
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=crm_db;charset=utf8mb4", "root", "123456", [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $r = $pdo->query("SELECT VERSION() as v, @@port as p");
    $d = $r->fetch();
    echo "MySQL: " . $d['v'] . " Port: " . $d['p'] . "\n";
    $r2 = $pdo->query("SELECT COUNT(*) as c FROM customers_tamil_nadu");
    echo "Rows: " . $r2->fetch()['c'] . "\n";
    $r3 = $pdo->query("SHOW INDEX FROM customers_tamil_nadu WHERE Key_name='idx_mobile'");
    echo "idx_mobile: " . (count($r3->fetchAll()) > 0 ? "EXISTS" : "MISSING") . "\n";
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
