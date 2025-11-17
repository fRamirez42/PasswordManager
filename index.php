<?php
declare(strict_types=1);

define('ADD_SITE',       'ADD_SITE');
define('ADD_ACCOUNT',    'ADD_ACCOUNT');
define('ADD_PASSWORD',   'ADD_PASSWORD');
define('UPDATE_PATTERN', 'UPDATE_PATTERN');
define('DELETE_PATTERN', 'DELETE_PATTERN');
define('SEARCH',         'SEARCH');

require_once 'includes/helpers.php';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$errors = [];
$notices = [];
$search_rows = [];
$option = $_POST['submitted'] ?? null;

/* Handle actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_results'])) {
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }

    try {
        switch ($option) {
            case SEARCH:
                $q = trim($_POST['q'] ?? '');
                if ($q === '') { $errors[] = 'Search query cannot be empty.'; break; }
                $search_rows = search_all($q);
                if (!$search_rows) $notices[] = 'No results.';
                break;

            case ADD_SITE:
                $url = trim($_POST['url'] ?? '');
                if ($url === '') { $errors[] = 'URL cannot be empty.'; break; }
                $id = add_site($url);
                $notices[] = "Added/loaded site #{$id}.";
                break;

            case ADD_ACCOUNT:
                $sid   = (int)($_POST['site_ID'] ?? 0);
                $email = trim($_POST['email'] ?? '');
                $user  = trim($_POST['username'] ?? '');
                if ($sid <= 0 || ($email === '' && $user === '')) { $errors[] = 'Site and (email or username) required.'; break; }
                $aid = add_account($sid, $email, $user);
                $notices[] = "Added account #{$aid}.";
                break;

            case ADD_PASSWORD:
                $aid         = (int)($_POST['account_ID'] ?? 0);
                $pwd         = (string)($_POST['password_plain'] ?? '');
                $commentRaw  = trim($_POST['comment'] ?? '');
                $comment     = ($commentRaw === '') ? null : $commentRaw;
                $makeCurrent = isset($_POST['is_current']) && $_POST['is_current'] === '1';
                if ($aid <= 0 || $pwd === '') { $errors[] = 'Account and password required.'; break; }
                $pid = add_password($aid, $pwd, $comment, $makeCurrent);
                $notices[] = "Added password entry #{$pid}.";
                break;

            case UPDATE_PATTERN:
                $target = $_POST['target'] ?? 'site';
                if ($target === 'site') {
                    $pat = trim($_POST['pat_site'] ?? '');
                    $new = trim($_POST['new_site_url'] ?? '');
                    if ($pat === '' || $new === '') { $errors[] = 'Provide a site URL pattern and a new URL.'; break; }
                    $n = update_site_url_by_pattern($pat, $new);
                    $notices[] = "Updated {$n} site row(s).";
                } else {
                    $ep = trim($_POST['pat_email'] ?? '');
                    $up = trim($_POST['pat_user'] ?? '');
                    $ne = trim($_POST['new_email'] ?? '');
                    $nu = trim($_POST['new_username'] ?? '');
                    if ($ep === '' && $up === '') { $errors[] = 'Provide an email and/or username pattern.'; break; }
                    if ($ne === '' && $nu === '') { $errors[] = 'Provide a new email and/or username.'; break; }
                    $n = update_account_by_pattern($ep, $up, $ne === '' ? null : $ne, $nu === '' ? null : $nu);
                    $notices[] = "Updated {$n} account row(s).";
                }
                break;

            case DELETE_PATTERN:
                $table = $_POST['table'] ?? 'sites';
                $field = $_POST['field'] ?? 'url';
                $pat   = trim($_POST['pattern'] ?? '');
                if ($pat === '') { $errors[] = 'Provide a pattern to delete by.'; break; }
                $n = delete_by_pattern($table, $field, $pat);
                $notices[] = "Deleted {$n} row(s) by pattern.";
                break;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

/* Data for selects and tables */
$sites = get_sites();

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
    <link rel="stylesheet" href="css/style.css?v=10">
  </head>
  <body>
    <header class="header">
      <h1>Passwords</h1>
      <p class="muted">Student Passwords — AES-256 (per-row IV) • normalized schema</p>
    </header>

    <form id="clear-results" method="post" action="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>">
      <input type="hidden" name="clear_results" value="1">
      <input id="clear-results__submit-button" class="btn" type="submit" value="Clear Results">
    </form>

    <div class="msgs">
      <?php foreach ($notices as $m): ?><div class="msg ok"><?php echo h($m); ?></div><?php endforeach; ?>
      <?php foreach ($errors as $m): ?><div class="msg err"><?php echo h($m); ?></div><?php endforeach; ?>
    </div>

    <section class="card">
      <h2>Search</h2>
      <form method="post" action="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo SEARCH; ?>">
        <label for="q">Query</label>
        <input id="q" name="q" type="text" placeholder="email, url, username, comment, password...">
        <button class="btn" type="submit">Search</button>
      </form>

      <?php if (!empty($search_rows)): ?>
        <div class="table-wrap" style="margin-top:12px;">
          <table>
            <thead>
              <tr><th>Site</th><th>Email</th><th>Username</th><th>Password</th><th>Created</th><th>Comment</th></tr>
            </thead>
            <tbody>
              <?php foreach ($search_rows as $r): ?>
                <tr>
                  <td><?php echo h($r['url']); ?></td>
                  <td><?php echo h($r['email']); ?></td>
                  <td><?php echo h($r['username']); ?></td>
                  <td><?php echo h($r['password_plain']); ?></td>
                  <td><?php echo h($r['time_of_creation']); ?></td>
                  <td><?php echo h($r['comment'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h2>Add Site</h2>
      <form method="post" class="grid grid-3" action="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>">
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

      <div class="table-wrap" style="margin-top:12px;">
        <table>
          <thead><tr><th>site_ID</th><th>URL</th></tr></thead>
          <tbody>
            <?php if (empty($sites)): ?>
              <tr><td colspan="2"><em>No sites yet.</em></td></tr>
            <?php else: foreach ($sites as $s): ?>
              <tr><td><?php echo h($s['site_ID']); ?></td><td><?php echo h($s['url']); ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <h2>Add Account</h2>
      <form method="post" class="grid grid-3" action="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo ADD_ACCOUNT; ?>">

        <div>
          <label for="site_ID">Site</label>
          <select id="site_ID" name="site_ID" required>
            <option value="">Choose…</option>
            <?php foreach ($sites as $s): ?>
              <option value="<?php echo (int)$s['site_ID']; ?>"><?php echo h($s['url']); ?></option>
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
        <span class="muted">Requires at least one of email/username.</span>
      </form>

      <div class="table-wrap" style="margin-top:12px;">
        <table>
          <thead>
            <tr><th>account_ID</th><th>site_ID</th><th>Site URL</th><th>Email</th><th>Username</th></tr>
          </thead>
          <tbody>
            <?php if (empty($accounts)): ?>
              <tr><td colspan="5"><em>No accounts yet.</em></td></tr>
            <?php else: foreach ($accounts as $a): ?>
              <tr>
                <td><?php echo h($a['account_ID']); ?></td>
                <td><?php echo h($a['site_ID']); ?></td>
                <td><?php echo h($a['url']); ?></td>
                <td><?php echo h($a['email']); ?></td>
                <td><?php echo h($a['username']); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card">
      <h2>Add Password</h2>
      <form method="post" class="grid grid-3" action="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>">
        <input type="hidden" name="submitted" value="<?php echo ADD_PASSWORD; ?>">

        <div>
          <label for="account_ID">Account</label>
          <select id="account_ID" name="account_ID" required>
            <option value="">Choose…</option>
            <?php foreach ($accounts as $a): ?>
              <option value="<?php echo (int)$a['account_ID']; ?>">
                <?php $label = $a['email'] !== '' ? $a['email'] : $a['username']; echo h($a['url'] . ' — ' . $label); ?>
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
          <textarea id="comment" name="comment" rows="2" placeholder="note…"></textarea>
        </div>

        <label class="checkbox" style="grid-column: 1 / 3; align-self:center;">
          <input type="checkbox" name="is_current" value="1" checked> Make current (older current will be turned off)
        </label>

        <div style="justify-self:end;">
          <input class="btn btn-success" type="submit" value="Add Password">
        </div>

        <span class="muted">Change passwords by adding a new one; mark it current if needed.</span>
      </form>
    </section>

    <section class="card table-wrap">
      <h2>Current Passwords</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
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
            <tr><td colspan="7"><em>No current passwords yet.</em></td></tr>
          <?php else: foreach ($current as $r): ?>
            <tr>
              <td><?php echo h($r['pass_ID']); ?></td>
              <td><?php echo h($r['url']); ?></td>
              <td><?php echo h($r['email']); ?></td>
              <td><?php echo h($r['username']); ?></td>
              <td><?php echo h($r['password_plain']); ?></td>
              <td><?php echo h($r['time_of_creation']); ?></td>
              <td><?php echo h($r['comment'] ?? ''); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </section>

    <section class="card">
      <h2>Update by Pattern</h2>
      <form method="post" action="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>" class="grid grid-3">
        <input type="hidden" name="submitted" value="<?php echo UPDATE_PATTERN; ?>">

        <div>
          <label for="target">Target</label>
          <select id="target" name="target">
            <option value="site">Sites (URL)</option>
            <option value="account">Accounts (email/username)</option>
          </select>
        </div>

        <div>
          <label for="pat_site">Site URL contains</label>
          <input id="pat_site" name="pat_site" type="text" placeholder="example.com">
        </div>

        <div>
          <label for="new_site_url">New URL</label>
          <input id="new_site_url" name="new_site_url" type="url" placeholder="https://new.example.com">
        </div>

        <div>
          <label for="pat_email">Account Email contains</label>
          <input id="pat_email" name="pat_email" type="text" placeholder="@school.edu">
        </div>

        <div>
          <label for="pat_user">Account Username contains</label>
          <input id="pat_user" name="pat_user" type="text" placeholder="john">
        </div>

        <div>
          <label for="new_email">New Email</label>
          <input id="new_email" name="new_email" type="email">
        </div>

        <div>
          <label for="new_username">New Username</label>
          <input id="new_username" name="new_username" type="text">
        </div>

        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
          <button class="btn" type="submit">Update by Pattern</button>
        </div>

        <span class="muted">For sites, use the URL fields. For accounts, use the email/username fields.</span>
      </form>
    </section>

    <section class="card">
      <h2>Delete by Pattern</h2>
      <form method="post" action="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>" class="grid grid-3" id="delete-form">
        <input type="hidden" name="submitted" value="<?php echo DELETE_PATTERN; ?>">

        <div>
          <label for="table">Table</label>
          <select id="table" name="table" required>
            <option value="sites">sites</option>
            <option value="accounts">accounts</option>
            <option value="passwords">passwords</option>
          </select>
        </div>

        <div>
          <label for="field">Field</label>
          <select id="field" name="field" required></select>
        </div>

        <div>
          <label for="pattern">Pattern</label>
          <input id="pattern" name="pattern" type="text" placeholder="example.com or @gmail.com" required>
        </div>

        <div style="grid-column:1/-1;display:flex;justify-content:flex-end;">
          <button class="btn" type="submit">Delete by Pattern</button>
        </div>

        <span class="muted">sites.url • accounts.email • accounts.username • passwords.comment</span>
      </form>

      <script>
        (function () {
          const tableSel = document.getElementById('table');
          const fieldSel = document.getElementById('field');
          const optionsByTable = {
            sites:     [{v:'url', t:'url'}],
            accounts:  [{v:'email', t:'email'}, {v:'username', t:'username'}],
            passwords: [{v:'comment', t:'comment'}],
          };
          function refreshFields() {
            const t = tableSel.value;
            const opts = optionsByTable[t] || [];
            fieldSel.innerHTML = '';
            for (const o of opts) {
              const el = document.createElement('option');
              el.value = o.v; el.textContent = o.t;
              fieldSel.appendChild(el);
            }
          }
          tableSel.addEventListener('change', refreshFields);
          refreshFields();
        })();
      </script>
    </section>
  </body>
</html>
