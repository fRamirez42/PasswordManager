<?php
declare(strict_types=1);

// All paths are relative per the rubric
define('DB_DSN',  'mysql:host=localhost;dbname=student_passwords;charset=utf8mb4');
define('DB_USER', 'passwords_user');
define('DB_PASS', '');

// Use the same passphrase you use in mysql/setup.sql if you reference it there.
// Keep it in code only (no UI exposure).
define('AES_PASSPHRASE', 'your-course-passphrase-here');

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function get_sites(): array {
    $pdo = db();
    return $pdo->query("SELECT site_ID, url FROM sites ORDER BY url")->fetchAll();
}

function add_site(string $url): int {
    $pdo = db();
    $pdo->prepare("INSERT INTO sites (name, url) VALUES (:n, :u)
                   ON DUPLICATE KEY UPDATE url = VALUES(url)")
        ->execute([':n' => $url, ':u' => $url]); // fallback: name=url if using 2-field schema
    return (int)$pdo->lastInsertId();
}

function add_site_named(string $name, string $url): int {
    $pdo = db();
    // Try by name
    $q = $pdo->prepare("SELECT site_ID FROM sites WHERE name = :n");
    $q->execute([':n' => $name]);
    $sid = $q->fetchColumn();
    if ($sid) return (int)$sid;

    $stmt = $pdo->prepare("INSERT INTO sites (name, url) VALUES (:n, :u)
                           ON DUPLICATE KEY UPDATE url = VALUES(url)");
    $stmt->execute([':n' => $name, ':u' => $url]);
    return (int)$pdo->lastInsertId();
}

function add_account(int $site_ID, string $email, string $username): int {
    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO accounts (site_ID, email, username)
        VALUES (:s, :e, :u)
    ");
    $stmt->execute([':s' => $site_ID, ':e' => $email, ':u' => $username]);
    return (int)$pdo->lastInsertId();
}

function add_password(int $account_ID, string $password_plain, ?string $comment, bool $makeCurrent): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($makeCurrent) {
            $pdo->prepare("UPDATE passwords SET is_current = 0 WHERE account_ID = :a AND is_current = 1")
                ->execute([':a' => $account_ID]);
        }
        $iv = random_bytes(16);
        $stmt = $pdo->prepare("
            INSERT INTO passwords (account_ID, password_cipher, iv, key_version, time_of_creation, comment, is_current)
            VALUES (:a,
                    AES_ENCRYPT(:p, UNHEX(SHA2(:k,512)), :iv_enc),
                    :iv_store,
                    1,
                    CURRENT_TIMESTAMP,
                    :c,
                    :cur)
        ");
        $stmt->bindValue(':a', $account_ID, PDO::PARAM_INT);
        $stmt->bindValue(':p', $password_plain, PDO::PARAM_STR);
        $stmt->bindValue(':k', AES_PASSPHRASE, PDO::PARAM_STR);
        $stmt->bindValue(':iv_enc', $iv, PDO::PARAM_STR);
        $stmt->bindValue(':iv_store', $iv, PDO::PARAM_STR);
        $stmt->bindValue(':c', $comment, $comment === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':cur', $makeCurrent ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        $pid = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $pid;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_current_passwords_decrypted(): array {
    $pdo = db();
    $sql = "
      SELECT s.url, a.email, a.username,
             CAST(AES_DECRYPT(p.password_cipher, UNHEX(SHA2(:k,512)), p.iv) AS CHAR) AS password_plain,
             p.time_of_creation, p.comment
      FROM passwords p
      JOIN accounts a ON a.account_ID = p.account_ID
      JOIN sites s    ON s.site_ID = a.site_ID
      WHERE p.is_current = 1
      ORDER BY s.url, a.email, a.username, p.time_of_creation DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':k' => AES_PASSPHRASE]);
    return $stmt->fetchAll();
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
      WHERE s.name LIKE :qq
         OR s.url LIKE :qq
         OR a.email LIKE :qq
         OR a.username LIKE :qq
         OR p.comment LIKE :qq
         OR CAST(AES_DECRYPT(p.password_cipher, UNHEX(SHA2(:k2,512)), p.iv) AS CHAR) LIKE :qq
      ORDER BY p.time_of_creation DESC
    ";
    $stmt = $pdo->prepare($sql);
    $like = '%'.$q.'%';
    $stmt->execute([':k'=>AES_PASSPHRASE, ':k2'=>AES_PASSPHRASE, ':qq'=>$like]);
    return $stmt->fetchAll();
}

function allowed_fields(): array {
    return [
        'sites'     => ['name','url'],
        'accounts'  => ['email','username'],
        'passwords' => ['comment'],
    ];
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

    $aliases = ['sites' => 's', 'accounts' => 'a', 'passwords' => 'p'];
    $tAlias = $aliases[$targetTable];
    $wAlias = $aliases[$whereTable];

    $sql = "
      UPDATE sites s
      JOIN accounts a ON a.site_ID = s.site_ID
      JOIN passwords p ON p.account_ID = a.account_ID
      SET {$tAlias}.{$targetField} = :newv
      WHERE {$wAlias}.{$whereField} LIKE :pat
    ";

    $pdo = db();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':newv' => $newValue, ':pat' => '%'.$pattern.'%']);
    return $stmt->rowCount();
}

function delete_by_pattern(string $table, string $field, string $pattern): int {
    $allowed = allowed_fields();
    if (!isset($allowed[$table]) || !in_array($field, $allowed[$table], true)) {
        throw new RuntimeException('Invalid delete column.');
    }
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$field} LIKE :pat");
    $stmt->execute([':pat' => '%'.$pattern.'%']);
    return $stmt->rowCount();
}

function insert_full_entry(string $siteName, string $url, string $email, string $username,
                           string $passwordPlain, ?string $comment): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sid = add_site_named($siteName, $url);
        $aid = add_account($sid, $email, $username);
        $pid = add_password($aid, $passwordPlain, $comment, true);
        $pdo->commit();
        return $pid;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
