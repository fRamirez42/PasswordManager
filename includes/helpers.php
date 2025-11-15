<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/* ---------- Sites ---------- */

function get_sites(): array {
    $sql = "SELECT site_ID, url FROM sites ORDER BY url";
    return db()->query($sql)->fetchAll();
}

function add_site(string $url): int {
    $sql = "INSERT INTO sites (url) VALUES (:url)
            ON DUPLICATE KEY UPDATE site_ID = LAST_INSERT_ID(site_ID)";
    $stmt = db()->prepare($sql);
    $stmt->execute([':url' => $url]);
    return (int)db()->lastInsertId();
}

/* ---------- Accounts ---------- */

function add_account(int $siteId, string $email, string $username): int {
    $sql = "INSERT INTO accounts (site_ID, email, username)
            VALUES (:sid, :email, :username)";
    $stmt = db()->prepare($sql);
    $stmt->execute([':sid'=>$siteId, ':email'=>$email, ':username'=>$username]);
    return (int)db()->lastInsertId();
}

/* ---------- Passwords (AES, per-row IV) ---------- */

function add_password(int $accountId, string $plaintext, ?string $comment = null, bool $makeCurrent = true): int {
    $iv  = random_bytes(16); // unique per row
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($makeCurrent) {
            $pdo->prepare("UPDATE passwords SET is_current = 0 WHERE account_ID = :aid AND is_current = 1")
                ->execute([':aid' => $accountId]);
        }

        // Use distinct placeholders for the IV to avoid HY093 with native prepares
        $sql = "INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current)
                VALUES (
                  :aid,
                  AES_ENCRYPT(:pwd, UNHEX(SHA2(:key, 256)), :iv_enc),
                  :iv_store,
                  :comment,
                  :current
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':aid'      => $accountId,
            ':pwd'      => $plaintext,
            ':key'      => AES_PASSPHRASE,
            ':iv_enc'   => $iv,
            ':iv_store' => $iv,
            ':comment'  => $comment,
            ':current'  => $makeCurrent ? 1 : 0,
        ]);

        $pdo->commit();
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_current_passwords_decrypted(): array {
    $sql = "
      SELECT s.url, a.email, a.username,
             CAST(AES_DECRYPT(p.password_cipher, UNHEX(SHA2(:key, 256)), p.iv) AS CHAR) AS password_plain,
             p.time_of_creation, p.comment
      FROM passwords p
      JOIN accounts a ON p.account_ID = a.account_ID
      JOIN sites    s ON a.site_ID    = s.site_ID
      WHERE p.is_current = 1
      ORDER BY s.url, a.email";
    $stmt = db()->prepare($sql);
    $stmt->execute([':key' => AES_PASSPHRASE]);
    return $stmt->fetchAll();
}
