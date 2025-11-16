<?php
declare(strict_types=1);

define('ADD_SITE', 'ADD_SITE');
define('ADD_ACCOUNT', 'ADD_ACCOUNT');
define('ADD_PASSWORD', 'ADD_PASSWORD');

require_once 'includes/helpers.php';

$errors = [];
$notices = [];

$option = $_POST['submitted'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_results'])) {
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }

    try {
        switch ($option) {
            case ADD_SITE:
                $url = trim($_POST['url'] ?? '');
                if ($url === '') {
                    $errors[] = 'URL cannot be empty.';
                } else {
                    $id = add_site($url);
                    $notices[] = "Added/loaded site #{$id}.";
                }
                break;

            case ADD_ACCOUNT:
                $sid   = (int)($_POST['site_ID'] ?? 0);
                $email = trim($_POST['email'] ?? '');
                $user  = trim($_POST['username'] ?? '');
                if ($sid <= 0 || ($email === '' && $user === '')) {
                    $errors[] = 'Site and (email or username) required.';
                } else {
                    $aid = add_account($sid, $email, $user);
                    $notices[] = "Added account #{$aid}.";
                }
                break;

            case ADD_PASSWORD:
                $aid         = (int)($_POST['account_ID'] ?? 0);
                $pwd         = (string)($_POST['password_plain'] ?? '');
                $commentRaw  = trim($_POST['comment'] ?? '');
                $comment     = ($commentRaw === '') ? null : $commentRaw;
                $makeCurrent = isset($_POST['is_current']) && $_POST['is_current'] === '1';

                if ($aid <= 0 || $pwd === '') {
                    $errors[] = 'Account and password required.';
                } else {
                    $pid = add_password($aid, $pwd, $comment, $makeCurrent);
                    $notices[] = "Added password entry #{$pid}.";
                }
                break;

            default:
                break;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

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
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Passwords</title>
    <link rel="stylesheet" href="css/style.css?v=4">
  </head>
  <body>
    <header class="header">
      <h1>Passwords</h1>
      <p class="muted">Student Passwords — AES-256 (per-row IV) • normalized schema</p>
    </header>

    <form id="clear-results" method="post" action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
      <input type="hidden" name="clear_results" value="1">
      <input id="clear-results__submit-button" class="btn" type="submit" value="Clear Results">
    </form>

    <div class="msgs">
      <?php foreach ($notices as $m): ?>
        <div class="msg ok"><?php echo htmlspecialchars($m); ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $m): ?>
        <div class="msg err"><?php echo htmlspecialchars($m); ?></div>
      <?php endforeach; ?>
    </div>

    <section class="card">
      <h2>Add Site</h2>
      <form method="post" class="grid grid-3" action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo ADD_SITE; ?>">
        <div>
          <label for="url">URL</label>
          <input id="url" name="url" type="url" placeholder="https://example.com" required>
        </div>
        <div></div>
        <div style="align-self:end;justify-self:end;">
          <input class="btn btn-primary" type="submit" value="Add / Load">
        </div>
        <span class="muted">Duplicate URLs are ignored by the unique key.</span>
      </form>
    </section>

    <section class="card">
      <h2>Add Account</h2>
      <form method="post" class="grid grid-3" action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo ADD_ACCOUNT; ?>">

        <div>
          <label for="site_ID">Site</label>
          <select id="site_ID" name="site_ID" required>
            <option value="">Choose…</option>
            <?php foreach ($sites as $s): ?>
              <option value="<?php echo (int)$s['site_ID']; ?>">
                <?php echo htmlspecialchars($s['url']); ?>
              </option>
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
          <input class="btn btn-secondary" type="submit" value="Add Account">
        </div>
        <span class="muted">DB blocks duplicate (site, email, username) and requires at least one of email/username.</span>
      </form>
    </section>

    <section class="card">
      <h2>Add Password</h2>
      <form method="post" class="grid grid-3" action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo ADD_PASSWORD; ?>">

        <div>
          <label for="account_ID">Account</label>
          <select id="account_ID" name="account_ID" required>
            <option value="">Choose…</option>
            <?php foreach ($accounts as $a): ?>
              <option value="<?php echo (int)$a['account_ID']; ?>">
                <?php
                  $label = $a['email'] !== '' ? $a['email'] : $a['username'];
                  echo htmlspecialchars($a['url'] . ' — ' . $label);
                ?>
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
          <input class="btn btn-success" type="submit" value="Add Password">
        </div>

        <span class="muted">Encrypted via AES_ENCRYPT with a per-row IV (handled in helpers.php).</span>
      </form>
    </section>

    <section class="card table-wrap">
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
                <td><?php echo htmlspecialchars($r['url']); ?></td>
                <td><?php echo htmlspecialchars($r['email']); ?></td>
                <td><?php echo htmlspecialchars($r['username']); ?></td>
                <td><?php echo htmlspecialchars($r['password_plain']); ?></td>
                <td><?php echo htmlspecialchars($r['time_of_creation']); ?></td>
                <td><?php echo htmlspecialchars($r['comment'] ?? ''); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </body>
</html>
