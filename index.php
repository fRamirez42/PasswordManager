<?php
// index.php (project root)
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';

$errors = [];
$notices = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'add_site') {
            $url = trim($_POST['url'] ?? '');
            if ($url === '') throw new RuntimeException('URL required.');
            $id = add_site($url);
            $notices[] = "Added/loaded site #{$id}.";
        }

        if (isset($_POST['action']) && $_POST['action'] === 'add_account') {
            $sid = (int)($_POST['site_ID'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $user  = trim($_POST['username'] ?? '');
            if ($sid <= 0 || ($email === '' && $user === '')) {
                throw new RuntimeException('Site and (email or username) required.');
            }
            $aid = add_account($sid, $email, $user);
            $notices[] = "Added account #{$aid}.";
        }

        if (isset($_POST['action']) && $_POST['action'] === 'add_password') {
            $aid = (int)($_POST['account_ID'] ?? 0);
            $pwd = (string)($_POST['password_plain'] ?? '');
            $comment = trim($_POST['comment'] ?? '') ?: null;
            $makeCurrent = isset($_POST['is_current']) && $_POST['is_current'] === '1';
            if ($aid <= 0 || $pwd === '') {
                throw new RuntimeException('Account and password required.');
            }
            $pid = add_password($aid, $pwd, $comment, $makeCurrent);
            $notices[] = "Added password entry #{$pid}.";
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Data for forms/tables
$sites    = get_sites();
$accounts = [];
if (!empty($sites)) {
    $pdo = db();
    $accounts = $pdo->query("
        SELECT a.account_ID, a.site_ID, s.url, a.email, a.username
        FROM accounts a
        JOIN sites s ON s.site_ID = a.site_ID
        ORDER BY s.url, a.email, a.username
    ")->fetchAll();
}
$current = get_current_passwords_decrypted();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Student Passwords</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- stylesheet: keep path relative -->
  <link rel="stylesheet" href="./css/style.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Student Passwords</h1>
      <small class="muted">AES-256 (per-row IV) • normalized schema</small>
    </div>

    <div class="msgs">
      <?php foreach ($notices as $m): ?>
        <div class="msg ok"><?= htmlspecialchars($m) ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $m): ?>
        <div class="msg err"><?= htmlspecialchars($m) ?></div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <h2>Add Site</h2>
      <form method="post" class="grid grid-3">
        <input type="hidden" name="action" value="add_site">
        <div>
          <label for="url">URL</label>
          <input id="url" name="url" type="url" placeholder="https://example.com" required>
        </div>
        <div></div>
        <div style="align-self:end;justify-self:end;">
          <button class="btn btn-primary" type="submit">Add / Load</button>
        </div>
        <span class="muted">Duplicate URLs are ignored by the unique key.</span>
      </form>
    </div>

    <div class="card">
      <h2>Add Account</h2>
      <form method="post" class="grid grid-3">
        <input type="hidden" name="action" value="add_account">

        <div>
          <label for="site_ID">Site</label>
          <select id="site_ID" name="site_ID" required>
            <option value="">Choose…</option>
            <?php foreach ($sites as $s): ?>
              <option value="<?= (int)$s['site_ID'] ?>"><?= htmlspecialchars($s['url']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label for="email">Email</label>
          <input id="email" name="email" type="email" placeholder="you@example.com">
        </div>

        <div>
          <label for="username">Username</label>
          <input id="username" name="username" type="text" placeholder="username">
        </div>

        <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
          <button class="btn btn-secondary" type="submit">Add Account</button>
        </div>
        <span class="muted">DB blocks duplicate (site, email, username) and requires at least one of email/username.</span>
      </form>
    </div>

    <div class="card">
      <h2>Add Password</h2>
      <form method="post" class="grid grid-3">
        <input type="hidden" name="action" value="add_password">

        <div>
          <label for="account_ID">Account</label>
          <select id="account_ID" name="account_ID" required>
            <option value="">Choose…</option>
            <?php foreach ($accounts as $a): ?>
              <option value="<?= (int)$a['account_ID'] ?>">
                <?= htmlspecialchars($a['url']) ?> — <?= htmlspecialchars($a['email'] ?: $a['username']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label for="password_plain">Password (plaintext)</label>
          <input id="password_plain" name="password_plain" type="text" required>
        </div>

        <div>
          <label for="comment">Comment (optional)</label>
          <input id="comment" name="comment" type="text" placeholder="note…">
        </div>

        <label class="checkbox" style="grid-column: 1 / 3; align-self:center;">
          <input type="checkbox" name="is_current" value="1" checked>
          Make current (older current will be turned off)
        </label>

        <div style="justify-self:end;">
          <button class="btn btn-success" type="submit">Add Password</button>
        </div>

        <span class="muted">Encrypted via AES_ENCRYPT with a per-row IV (handled in helpers.php).</span>
      </form>
    </div>

    <div class="card table-wrap">
      <h2>Current Passwords</h2>
      <table>
        <thead>
          <tr>
            <th>Site</th>
            <th>Email</th>
            <th>Username</th>
            <th>Password (decrypted)</th>
            <th>Created</th>
            <th>Comment</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$current): ?>
            <tr><td colspan="6"><em>No current passwords yet.</em></td></tr>
          <?php else: ?>
            <?php foreach ($current as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['url']) ?></td>
                <td><?= htmlspecialchars($r['email']) ?></td>
                <td><?= htmlspecialchars($r['username']) ?></td>
                <td><?= htmlspecialchars($r['password_plain']) ?></td>
                <td><?= htmlspecialchars($r['time_of_creation']) ?></td>
                <td><?= htmlspecialchars($r['comment'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
