<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* -------------------- Sites -------------------- */

function get_sites(): array {
    $sql = "SELECT site_ID, url FROM sites ORDER BY url";
    return db()->query($sql)->fetchAll();
}

function add_site(string $url): int {
    $sql = "INSERT INTO sites (url)
            VALUES (:url)
            ON DUPLICATE KEY UPDATE site_ID = LAST_INSERT_ID(site_ID)";
    $st = db()->prepare($sql);
    $st->execute([':url' => $url]);
    return (int)db()->lastInsertId();
}

/** Update sites.url WHERE url LIKE '%pattern%' */
function update_site_url_by_pattern(string $pattern, string $newUrl): int {
    if ($pattern === '' || $newUrl === '') return 0;
    $st = db()->prepare("UPDATE sites SET url = :u WHERE url LIKE :p");
    $st->execute([':u' => $newUrl, ':p' => '%'.$pattern.'%']);
    return $st->rowCount();
}

/* -------------------- Accounts -------------------- */

function add_account(int $siteId, string $email, string $username): int {
    $sql = "INSERT INTO accounts (site_ID, email, username)
            VALUES (:sid, :email, :username)";
    $st = db()->prepare($sql);
    $st->execute([':sid' => $siteId, ':email' => $email, ':username' => $username]);
    return (int)db()->lastInsertId();
}

/** Update accounts (email/username) using LIKE filters on email/username */
function update_account_by_pattern(?string $emailPat, ?string $userPat, ?string $newEmail, ?string $newUser): int {
    $conds = []; $params = [];
    if ($emailPat !== null && $emailPat !== '') { $conds[] = "email LIKE :ep"; $params[':ep'] = '%'.$emailPat.'%'; }
    if ($userPat  !== null && $userPat  !== '')  { $conds[] = "username LIKE :up"; $params[':up'] = '%'.$userPat.'%'; }
    if (!$conds) return 0;

    $sets = [];
    if ($newEmail !== null && $newEmail !== '') { $sets[] = "email = :ne"; $params[':ne'] = $newEmail; }
    if ($newUser  !== null && $newUser  !== '') { $sets[] = "username = :nu"; $params[':nu'] = $newUser; }
    if (!$sets) return 0;

    $sql = "UPDATE accounts SET ".implode(', ', $sets)." WHERE ".implode(' AND ', $conds);
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/* -------------------- Passwords (AES per-row IV) -------------------- */

function add_password(int $accountId, string $plaintext, ?string $comment = null, bool $makeCurrent = true): int {
    $pdo = db();
    $iv  = random_bytes(16);
    $pdo->beginTransaction();
    try {
        if ($makeCurrent) {
            $pdo->prepare("UPDATE passwords SET is_current = 0 WHERE account_ID = :aid AND is_current = 1")
                ->execute([':aid' => $accountId]);
        }

        $sql = "INSERT INTO passwords (account_ID, password_cipher, iv, comment, is_current)
                VALUES (:aid,
                        AES_ENCRYPT(:pwd, UNHEX(SHA2(:k,256)), :iv_enc),
                        :iv_store,
                        :comment,
                        :cur)";
        $st = $pdo->prepare($sql);
        $st->execute([
            ':aid'      => $accountId,
            ':pwd'      => $plaintext,
            ':k'        => AES_PASSPHRASE,
            ':iv_enc'   => $iv,
            ':iv_store' => $iv,
            ':comment'  => $comment,
            ':cur'      => $makeCurrent ? 1 : 0,
        ]);

        $pdo->commit();
        return (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_current_passwords_decrypted(): array {
    $sql = "SELECT
              p.pass_ID,
              s.url,
              a.email,
              a.username,
              CAST(AES_DECRYPT(p.password_cipher, UNHEX(SHA2(:k,256)), p.iv) AS CHAR) AS password_plain,
              p.time_of_creation,
              p.comment
            FROM passwords p
            JOIN accounts a ON a.account_ID = p.account_ID
            JOIN sites    s ON s.site_ID    = a.site_ID
            WHERE p.is_current = 1
            ORDER BY s.url, a.email, a.username, p.time_of_creation DESC";
    $st = db()->prepare($sql);
    $st->execute([':k' => AES_PASSPHRASE]);
    return $st->fetchAll();
}

/* -------------------- Search (LIKE + decrypted) -------------------- */

function search_all(string $q): array {
    $like = '%'.$q.'%';

    $sql = "SELECT
              s.url,
              a.email,
              a.username,
              CAST(AES_DECRYPT(p.password_cipher, UNHEX(SHA2(:k1,256)), p.iv) AS CHAR) AS password_plain,
              p.time_of_creation,
              p.comment
            FROM passwords p
            JOIN accounts a ON a.account_ID = p.account_ID
            JOIN sites    s ON s.site_ID    = a.site_ID
            WHERE s.url LIKE :q1
               OR a.email LIKE :q2
               OR a.username LIKE :q3
               OR p.comment LIKE :q4
               OR CAST(AES_DECRYPT(p.password_cipher, UNHEX(SHA2(:k2,256)), p.iv) AS CHAR) LIKE :q5
            ORDER BY p.time_of_creation DESC";

    $st = db()->prepare($sql);
    $st->execute([
        ':k1' => AES_PASSPHRASE,
        ':k2' => AES_PASSPHRASE,
        ':q1' => $like,
        ':q2' => $like,
        ':q3' => $like,
        ':q4' => $like,
        ':q5' => $like,
    ]);
    return $st->fetchAll();
}

/* -------------------- Delete by Pattern (allowlist) -------------------- */

function delete_by_pattern(string $table, string $field, string $pattern): int {
    $allow = [
        'sites'     => ['url'],
        'accounts'  => ['email','username'],
        'passwords' => ['comment'],
    ];
    if (!isset($allow[$table])) {
        throw new RuntimeException('Invalid table for delete.');
    }
    if (!in_array($field, $allow[$table], true)) {
        // make it obvious why 0 rows were affected
        throw new RuntimeException("Invalid field '{$field}' for table '{$table}'.");
    }
    $pattern = trim($pattern);
    if ($pattern === '') {
        throw new RuntimeException('Pattern cannot be empty.');
    }

    $sql = "DELETE FROM {$table} WHERE {$field} LIKE :p";
    $st  = db()->prepare($sql);
    $st->execute([':p' => '%'.$pattern.'%']);
    return $st->rowCount();
}
