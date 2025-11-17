<?php
declare(strict_types=1);

define('DB_DSN',  'mysql:host=localhost;dbname=student_passwords;charset=utf8mb4');
define('DB_USER', 'passwords_user');
define('DB_PASS', '');

define('AES_PASSPHRASE', 'GoldensRule123');

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
