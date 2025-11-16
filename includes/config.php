<?php
declare(strict_types=1);

const DB_DSN  = 'mysql:host=localhost;dbname=student_passwords;charset=utf8mb4';
const DB_USER = 'passwords_user';
const DB_PASS = '';

const AES_PASSPHRASE = 'class-demo-key';
const AES_MODE       = 'aes-256-cbc';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("SET block_encryption_mode = '" . AES_MODE . "'");

    return $pdo;
}
