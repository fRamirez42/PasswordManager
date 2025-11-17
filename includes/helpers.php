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
function allowed_fields(): array {
    return [
        'sites' => ['name','url'],
        'accounts' => ['email','username'],
        'passwords' => ['comment'] // we do not update cipher directly here
    ];
}

function search_all(string $q): array {
    $pdo = db();
    $sql = "
      SELECT s.name AS site_name, s.url, a.email, a.username,
             CAST(AES_DECRYPT(p.password_cipher, UNHEX(SHA2(:k,512)), p.iv) AS CHAR) AS password_plain,
             p.time_of_creation, p.comment
      FROM passwords p
      JOIN accounts a ON a.account_ID = p.account_ID
      JOIN sites s    ON s.site_ID = a.site_ID
      WHERE s.name LIKE :qq OR s.url LIKE :qq
         OR a.email LIKE :qq OR a.username LIKE :qq
         OR p.comment LIKE :qq
         OR CAST(AES_DECRYPT(p.password_cipher, UNHEX(SHA2(:k2,512)), p.iv) AS CHAR) LIKE :qq
      ORDER BY p.time_of_creation DESC
    ";
    $stmt = $pdo->prepare($sql);
    $like = '%'.$q.'%';
    $stmt->execute([':k'=>AES_PASSPHRASE, ':k2'=>AES_PASSPHRASE, ':qq'=>$like]);
    return $stmt->fetchAll();
}

function update_by_pattern(string $targetTable, string $targetField, string $newValue,
                           string $whereTable, string $whereField, string $pattern): int {
    $allowed = allowed_fields();
    if (!isset($allowed[$targetTable]) || !in_array($targetField, $allowed[$targetTable], true)) {
        throw new RuntimeException('Invalid target column.');
    }
    if (!isset($allowed[$whereTable]) || !in_array($whereField, $allowed[$whereTable], true)) {
        throw new RuntimeException('Invalid WHERE column.');
    }

    $joins = "
      FROM sites s
      JOIN accounts a ON a.site_ID = s.site_ID
      JOIN passwords p ON p.account_ID = a.account_ID
    ";

    $map = ['sites'=>'s','accounts'=>'a','passwords'=>'p'];
    $tAlias = $map[$targetTable];
    $wAlias = $map[$whereTable];

    $sql = "UPDATE {$targetTable} {$tAlias}
            {$joins}
            SET {$tAlias}.{$targetField} = :newv
            WHERE {$wAlias}.{$whereField} LIKE :pat";
    $pdo = db();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':newv'=>$newValue, ':pat'=>'%'.$pattern.'%']);
    return $stmt->rowCount();
}

function delete_by_pattern(string $table, string $field, string $pattern): int {
    $allowed = allowed_fields();
    if (!isset($allowed[$table]) || !in_array($field, $allowed[$table], true)) {
        throw new RuntimeException('Invalid delete column.');
    }
    // Cascade rules will clean dependent rows if you target parent rows
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$field} LIKE :pat");
    $stmt->execute([':pat'=>'%'.$pattern.'%']);
    return $stmt->rowCount();
}

function insert_full_entry(string $siteName, string $url, string $email, string $username,
                           string $passwordPlain, ?string $comment): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // upsert site by unique(name) / unique(url)
        $sid = add_site_named($siteName, $url); // helper just like add_site() but with name
        $aid = add_account($sid, $email, $username);
        $pid = add_password($aid, $passwordPlain, $comment, true);
        $pdo->commit();
        return $pid;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function add_site_named(string $name, string $url): int {
    $pdo = db();
    // try existing by name first
    $id = $pdo->prepare("SELECT site_ID FROM sites WHERE name = :n");
    $id->execute([':n'=>$name]);
    $sid = $id->fetchColumn();
    if ($sid) return (int)$sid;

    $stmt = $pdo->prepare("INSERT INTO sites (name, url) VALUES (:n, :u)
                           ON DUPLICATE KEY UPDATE url = VALUES(url)");
    $stmt->execute([':n'=>$name, ':u'=>$url]);
    return (int)$pdo->lastInsertId();
}
