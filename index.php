<?php
declare(strict_types=1);

define('ADD_SITE', 'ADD_SITE');
define('ADD_ACCOUNT', 'ADD_ACCOUNT');
define('ADD_PASSWORD', 'ADD_PASSWORD');
define('SEARCH_ALL', 'SEARCH_ALL');
define('UPDATE_BY_PATTERN', 'UPDATE_BY_PATTERN');
define('INSERT_FULL', 'INSERT_FULL');
define('DELETE_BY_PATTERN', 'DELETE_BY_PATTERN');

require_once 'includes/helpers.php';

$errors = [];
$notices = [];
$search_results = [];

$option = $_POST['submitted'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_results'])) {
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }

    try {
        switch ($option) {
            case INSERT_FULL:
                $sn  = trim($_POST['site_name'] ?? '');
                $url = trim($_POST['url'] ?? '');
                $em  = trim($_POST['email'] ?? '');
                $un  = trim($_POST['username'] ?? '');
                $pw  = (string)($_POST['password_plain'] ?? '');
                $cm  = trim($_POST['comment'] ?? '') ?: null;
                if ($sn === '' || $url === '' || $pw === '' || ($em === '' && $un === '')) {
                    $errors[] = 'Site name, URL, password, and one of email/username are required.';
                } else {
                    $pid = insert_full_entry($sn, $url, $em, $un, $pw, $cm);
                    $notices[] = "Inserted entry; password row #{$pid}.";
                }
                break;

            case SEARCH_ALL:
                $q = trim($_POST['q'] ?? '');
                if ($q === '') {
                    $errors[] = 'Search query empty.';
                } else {
                    $search_results = search_all($q);
                    if (!$search_results) {
                        $errors[] = 'Nothing found.';
                    } else {
                        $notices[] = 'Search completed.';
                    }
                }
                break;

            case UPDATE_BY_PATTERN:
                [$tTable,$tField] = explode('.', $_POST['target'] ?? '');
                [$wTable,$wField] = explode('.', $_POST['where'] ?? '');
                $new = trim($_POST['new_value'] ?? '');
                $pat = trim($_POST['pattern'] ?? '');
                if (!$tTable || !$tField || !$wTable || !$wField || $new === '' || $pat === '') {
                    $errors[] = 'All update fields are required.';
                } else {
                    $n = update_by_pattern($tTable, $tField, $new, $wTable, $wField, $pat);
                    $notices[] = "Updated {$n} row(s).";
                }
                break;

            case DELETE_BY_PATTERN:
                [$dTable,$dField] = explode('.', $_POST['del'] ?? '');
                $pat = trim($_POST['pattern'] ?? '');
                if (!$dTable || !$dField || $pat === '') {
                    $errors[] = 'Delete table/field and pattern are required.';
                } else {
                    $n = delete_by_pattern($dTable, $dField, $pat);
                    $notices[] = "Deleted {$n} row(s).";
                }
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

    <!-- Insert Entry (one step) -->
    <section class="card">
      <h2>Insert Entry</h2>
      <form method="post" class="grid grid-3" action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo INSERT_FULL; ?>">
        <div>
          <label>Site/App Name</label>
          <input type="text" name="site_name" required>
        </div>
        <div>
          <label>URL</label>
          <input type="url" name="url" required placeholder="https://example.com">
        </div>
        <div>
          <label>Email</label>
          <input type="email" name="email">
        </div>
        <div>
          <label>Username</label>
          <input type="text" name="username">
        </div>
        <div>
          <label>Password</label>
          <input type="text" name="password_plain" required>
        </div>
        <div style="grid-column: 1 / -1;">
          <label>Comment</label>
          <textarea name="comment" rows="3" placeholder="note…"></textarea>
        </div>
        <div style="grid-column: 1 / -1; text-align:right;">
          <input class="btn btn-success" type="submit" value="Insert">
        </div>
      </form>
    </section>

    <!-- Search -->
    <section class="card">
      <h2>Search</h2>
      <form method="post" action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo SEARCH_ALL; ?>">
        <label for="q">Query</label>
        <input id="q" name="q" type="text" placeholder="email, username, site name, url, comment, or password">
        <input class="btn" type="submit" value="Search">
      </form>
    </section>

    <?php if (!empty($search_results)): ?>
    <section class="card table-wrap">
      <h2>Search Results</h2>
      <table>
        <thead>
          <tr>
            <th>Site</th>
            <th>URL</th>
            <th>Email</th>
            <th>Username</th>
            <th>Password</th>
            <th>Created</th>
            <th>Comment</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($search_results as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['site_name']); ?></td>
              <td><?php echo htmlspecialchars($r['url']); ?></td>
              <td><?php echo htmlspecialchars($r['email']); ?></td>
              <td><?php echo htmlspecialchars($r['username']); ?></td>
              <td><?php echo htmlspecialchars($r['password_plain']); ?></td>
              <td><?php echo htmlspecialchars($r['time_of_creation']); ?></td>
              <td><?php echo htmlspecialchars($r['comment'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php endif; ?>

    <!-- Update -->
    <section class="card">
      <h2>Update (by pattern)</h2>
      <form method="post" class="grid grid-3" action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo UPDATE_BY_PATTERN; ?>">
        <div>
          <label>Target Table/Field</label>
          <select name="target">
            <option value="sites.name">sites.name</option>
            <option value="sites.url">sites.url</option>
            <option value="accounts.email">accounts.email</option>
            <option value="accounts.username">accounts.username</option>
            <option value="passwords.comment">passwords.comment</option>
          </select>
        </div>
        <div>
          <label>New Value</label>
          <input type="text" name="new_value" required>
        </div>
        <div>
          <label>WHERE Table/Field</label>
          <select name="where">
            <option value="sites.name">sites.name</option>
            <option value="sites.url">sites.url</option>
            <option value="accounts.email">accounts.email</option>
            <option value="accounts.username">accounts.username</option>
            <option value="passwords.comment">passwords.comment</option>
          </select>
        </div>
        <div>
          <label>Pattern (LIKE)</label>
          <input type="text" name="pattern" placeholder="e.g., example.com" required>
        </div>
        <div style="grid-column: 1 / -1; text-align:right;">
          <input class="btn" type="submit" value="Update">
        </div>
      </form>
    </section>

    <!-- Delete -->
    <section class="card">
      <h2>Delete (by pattern)</h2>
      <form method="post" class="grid grid-3" action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo DELETE_BY_PATTERN; ?>">
        <div>
          <label>Table/Field</label>
          <select name="del" required>
            <option value="sites.name">sites.name</option>
            <option value="sites.url">sites.url</option>
            <option value="accounts.email">accounts.email</option>
            <option value="accounts.username">accounts.username</option>
            <option value="passwords.comment">passwords.comment</option>
          </select>
        </div>
        <div>
          <label>Pattern (LIKE)</label>
          <input type="text" name="pattern" placeholder="e.g., @oldmail.com" required>
        </div>
        <div style="grid-column: 1 / -1; text-align:right;">
          <input class="btn" type="submit" value="Delete">
        </div>
      </form>
    </section>

    <!-- Current Passwords -->
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
