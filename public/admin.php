<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

App\Auth::requireUser();
if (!App\Auth::isAdmin() && empty(App\Auth::moderatorRights())) {
    http_response_code(403);
    exit('Forbidden');
}

$prefix = $config['db']['prefix'];
$pdo = $db->pdo();
$message = null;
$error = null;

function exportSqlDump(PDO $pdo, string $prefix): string
{
    $tablesStmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($prefix . '%'));
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    $dump = "-- Banner Exchange SQL Backup\n-- Generated at " . gmdate('Y-m-d H:i:s') . " UTC\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n";

        $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            continue;
        }

        foreach ($rows as $row) {
            $cols = array_map(static fn($c) => '`' . $c . '`', array_keys($row));
            $vals = array_map(static function ($v) use ($pdo) {
                return $v === null ? 'NULL' : $pdo->quote((string) $v);
            }, array_values($row));

            $dump .= 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n";
        }

        $dump .= "\n";
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_settings') {
            App\Auth::requireRight('settings.manage');
            $stmt = $pdo->prepare('UPDATE ' . $prefix . 'settings SET value_text = ? WHERE key_name = ?');
            foreach (['global_exchange_ratio', 'exchange_mode', 'max_banners_per_user', 'bonus_per_impression'] as $key) {
                if (isset($_POST[$key])) {
                    $stmt->execute([trim((string) $_POST[$key]), $key]);
                }
            }
            $message = 'Settings saved.';
        }

        if ($action === 'save_moderator') {
            App\Auth::requireRight('moderators.manage');
            $userId = (int) ($_POST['moderator_user_id'] ?? 0);
            $rights = $_POST['rights'] ?? [];
            if ($userId > 0) {
                $exchange->upsertModerator($userId, is_array($rights) ? $rights : []);
                $message = 'Moderator permissions updated.';
            }
        }

        if ($action === 'create_campaign') {
            App\Auth::requireRight('campaigns.manage');
            $subject = trim((string) ($_POST['subject_line'] ?? ''));
            $body = trim((string) ($_POST['body_text'] ?? ''));
            if ($subject !== '' && $body !== '') {
                $id = $exchange->createCampaign($subject, $body, !empty($_POST['send_now']));
                $message = 'Campaign #' . $id . ' created.';
            }
        }

        if ($action === 'send_campaign') {
            App\Auth::requireRight('campaigns.manage');
            $campaignId = (int) ($_POST['campaign_id'] ?? 0);
            if ($campaignId > 0) {
                $exchange->sendCampaign($campaignId);
                $message = 'Campaign sent.';
            }
        }

        if ($action === 'backup_download') {
            App\Auth::requireRight('backup.manage');
            $dump = exportSqlDump($pdo, $prefix);
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="banner-exchange-backup-' . gmdate('Ymd-His') . '.sql"');
            echo $dump;
            exit;
        }

        if ($action === 'restore_upload') {
            App\Auth::requireRight('backup.manage');
            if (isset($_FILES['sql_file']['tmp_name']) && is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
                $content = file_get_contents($_FILES['sql_file']['tmp_name']);
                foreach (array_filter(array_map('trim', explode(';', (string) $content))) as $query) {
                    $pdo->exec($query);
                }
                $message = 'Backup restored successfully.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stats = $exchange->publicStats();
$settings = $exchange->settings();
$period = $_GET['period'] ?? 'day';
$detailed = $exchange->detailedStats($period);
$users = $pdo->query('SELECT id, username, email FROM ' . $prefix . 'users ORDER BY username ASC')->fetchAll();
$moderators = $exchange->listModerators();
$campaigns = $exchange->listCampaigns();
$rightsCatalog = ['settings.manage', 'moderators.manage', 'campaigns.manage', 'backup.manage', 'stats.view'];
?>
<!doctype html>
<html><body style="font-family: Arial; max-width: 1100px; margin: 20px auto;">
<h1>Admin / Moderator panel</h1>
<p><a href="/logout.php">Logout</a></p>
<?php if ($message): ?><p style="color: green"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p style="color: red"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<h2>Core stats</h2>
<ul>
<li>Users: <?= $stats['users'] ?></li>
<li>Banners: <?= $stats['banners'] ?></li>
<li>Impressions: <?= $stats['impressions'] ?></li>
<li>Clicks: <?= $stats['clicks'] ?></li>
</ul>

<?php if (App\Auth::hasRight('stats.view')): ?>
<h2>Detailed charts (hour/day/month)</h2>
<p>
    <a href="?period=hour">Hour</a> |
    <a href="?period=day">Day</a> |
    <a href="?period=month">Month</a>
</p>
<canvas id="statsChart" width="1000" height="320" style="border:1px solid #ddd"></canvas>
<script>
const chartData = <?= json_encode($detailed, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const labels = chartData.impressions.map(i => i.bucket);
const impMap = Object.fromEntries(chartData.impressions.map(i => [i.bucket, Number(i.total)]));
const clkMap = Object.fromEntries(chartData.clicks.map(i => [i.bucket, Number(i.total)]));
const imp = labels.map(label => impMap[label] ?? 0);
const clk = labels.map(label => clkMap[label] ?? 0);
const maxVal = Math.max(1, ...imp, ...clk);
const c = document.getElementById('statsChart');
const ctx = c.getContext('2d');
ctx.clearRect(0, 0, c.width, c.height);
ctx.font = '12px Arial';
ctx.fillText('Detailed stats (' + chartData.period + ')', 10, 20);
const left = 50, top = 40, w = c.width - 80, h = c.height - 80;
ctx.strokeRect(left, top, w, h);
labels.forEach((label, i) => {
  const x = left + (i * (w / Math.max(1, labels.length - 1)));
  const yI = top + h - ((imp[i] / maxVal) * h);
  const yC = top + h - ((clk[i] / maxVal) * h);
  if (i === 0) { ctx.beginPath(); ctx.moveTo(x, yI); } else { ctx.lineTo(x, yI); }
});
ctx.strokeStyle = '#0b72ff'; ctx.stroke(); ctx.fillStyle = '#0b72ff'; ctx.fillText('Impressions', left + 10, top + 12);
labels.forEach((label, i) => {
  const x = left + (i * (w / Math.max(1, labels.length - 1)));
  const yC = top + h - ((clk[i] / maxVal) * h);
  if (i === 0) { ctx.beginPath(); ctx.moveTo(x, yC); } else { ctx.lineTo(x, yC); }
});
ctx.strokeStyle = '#ff6a00'; ctx.stroke(); ctx.fillStyle = '#ff6a00'; ctx.fillText('Clicks', left + 120, top + 12);
</script>
<?php endif; ?>

<?php if (App\Auth::hasRight('settings.manage')): ?>
<h2>Global settings</h2>
<form method="post">
    <input type="hidden" name="action" value="save_settings">
    <input name="global_exchange_ratio" value="<?= htmlspecialchars($settings['global_exchange_ratio'] ?? '1.00') ?>" placeholder="Ratio">
    <select name="exchange_mode">
        <?php $mode = $settings['exchange_mode'] ?? 'both'; ?>
        <option value="impressions" <?= $mode === 'impressions' ? 'selected' : '' ?>>Impressions only</option>
        <option value="clicks" <?= $mode === 'clicks' ? 'selected' : '' ?>>Clicks only</option>
        <option value="both" <?= $mode === 'both' ? 'selected' : '' ?>>Both</option>
    </select>
    <input name="max_banners_per_user" value="<?= htmlspecialchars($settings['max_banners_per_user'] ?? '10') ?>">
    <input name="bonus_per_impression" value="<?= htmlspecialchars($settings['bonus_per_impression'] ?? '1') ?>">
    <button type="submit">Save</button>
</form>
<?php endif; ?>

<?php if (App\Auth::hasRight('moderators.manage')): ?>
<h2>Moderator ACL</h2>
<form method="post">
    <input type="hidden" name="action" value="save_moderator">
    <select name="moderator_user_id" required>
        <option value="">Select user</option>
        <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>"><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
        <?php endforeach; ?>
    </select>
    <?php foreach ($rightsCatalog as $right): ?>
        <label><input type="checkbox" name="rights[]" value="<?= htmlspecialchars($right) ?>"> <?= htmlspecialchars($right) ?></label>
    <?php endforeach; ?>
    <button type="submit">Save moderator rights</button>
</form>
<table border="1" cellpadding="4" cellspacing="0">
<tr><th>User</th><th>Email</th><th>Active</th><th>Rights</th></tr>
<?php foreach ($moderators as $m): ?>
<tr>
<td><?= htmlspecialchars($m['username']) ?></td>
<td><?= htmlspecialchars($m['email']) ?></td>
<td><?= (int) $m['is_active'] ?></td>
<td><?= htmlspecialchars($m['rights_json']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (App\Auth::hasRight('campaigns.manage')): ?>
<h2>Email campaigns</h2>
<form method="post">
    <input type="hidden" name="action" value="create_campaign">
    <input name="subject_line" placeholder="Subject" required style="width: 420px">
    <br>
    <textarea name="body_text" placeholder="Message body" required style="width: 420px; height: 120px"></textarea>
    <br>
    <label><input type="checkbox" name="send_now" value="1"> Send immediately</label>
    <button type="submit">Create campaign</button>
</form>
<table border="1" cellpadding="4" cellspacing="0">
<tr><th>ID</th><th>Subject</th><th>Status</th><th>Sent count</th><th>Action</th></tr>
<?php foreach ($campaigns as $c): ?>
<tr>
<td><?= (int) $c['id'] ?></td>
<td><?= htmlspecialchars($c['subject_line']) ?></td>
<td><?= htmlspecialchars($c['status']) ?></td>
<td><?= (int) $c['sent_count'] ?></td>
<td>
<form method="post" style="display:inline">
    <input type="hidden" name="action" value="send_campaign">
    <input type="hidden" name="campaign_id" value="<?= (int) $c['id'] ?>">
    <button type="submit">Send</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (App\Auth::hasRight('backup.manage')): ?>
<h2>Backup / Restore</h2>
<form method="post">
    <input type="hidden" name="action" value="backup_download">
    <button type="submit">Download SQL backup</button>
</form>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="restore_upload">
    <input type="file" name="sql_file" accept=".sql,text/plain" required>
    <button type="submit">Restore uploaded SQL</button>
</form>
<?php endif; ?>

<h2>Toplist</h2>
<ol><?php foreach ($stats['top'] as $row): ?><li><?= htmlspecialchars($row['username']) ?> (<?= (int) $row['credits'] ?>)</li><?php endforeach; ?></ol>
</body></html>
