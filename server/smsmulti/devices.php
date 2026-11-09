<?php
require_once __DIR__.'/common.php';
gateway_require_admin();
$pdo = gateway_db();
$error = '';
$createdToken = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    gateway_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label === '' || gateway_utf8_length($label) > 80) {
            $error = 'Enter a device name of at most 80 characters.';
        } else {
            $createdToken = bin2hex(random_bytes(32));
            $statement = $pdo->prepare('INSERT INTO devices (label, token_hash, created_at) VALUES (?, ?, ?)');
            $statement->execute([$label, hash('sha256', $createdToken), time()]);
        }
    } elseif ($action === 'revoke') {
        $id = filter_var($_POST['device_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $error = 'Invalid device.';
        } else {
            $pdo->prepare('UPDATE devices SET active = 0 WHERE id = ?')->execute([$id]);
        }
    } else {
        $error = 'Unknown action.';
    }
}

$devices = $pdo->query('SELECT id, label, device_id, active, last_seen FROM devices ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Devices · SMS gateway</title>
<style>body{font:16px system-ui,sans-serif;max-width:58rem;margin:2rem auto;padding:0 1rem;color:#172033}nav{display:flex;gap:1rem}.card{border:1px solid #ccd3de;border-radius:.7rem;padding:1rem;margin:1rem 0}.error{color:#9b1c1c}.token{overflow-wrap:anywhere;background:#f1f4f8;padding:.7rem}label{display:block;margin-top:.8rem}input{padding:.6rem;font:inherit}button{padding:.55rem .8rem}</style></head>
<body><nav><a href="monitor.php">Device monitor</a><a href="sendsms.php">Queue SMS</a><form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?= gateway_escape(gateway_csrf_token()) ?>"><button type="submit">Sign out</button></form></nav>
<h1>Device credentials</h1><p>Create a separate token per phone. Copy a new token into that phone's app settings; it is shown only once.</p>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= gateway_escape($error) ?></p><?php endif ?>
<?php if ($createdToken !== ''): ?><section class="card" role="status"><strong>Copy this token now</strong><p class="token"><?= gateway_escape($createdToken) ?></p><p>The server stores only a hash. This token cannot be shown again.</p></section><?php endif ?>
<form method="post" action="devices.php"><input type="hidden" name="_csrf" value="<?= gateway_escape(gateway_csrf_token()) ?>"><input type="hidden" name="action" value="create"><label for="label">Device name</label><input id="label" name="label" maxlength="80" required><button type="submit">Create device token</button></form>
<h2>Registered devices</h2>
<?php foreach ($devices as $device): ?><section class="card"><strong><?= gateway_escape($device['label']) ?></strong><p><?= $device['device_id'] ? 'Paired: '.gateway_escape(substr($device['device_id'], 0, 8)).'…' : 'Not paired yet' ?> · <?= $device['active'] ? 'Active' : 'Revoked' ?></p>
<?php if ($device['active']): ?><form method="post" action="devices.php"><input type="hidden" name="_csrf" value="<?= gateway_escape(gateway_csrf_token()) ?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="device_id" value="<?= (int) $device['id'] ?>"><button type="submit">Revoke token</button></form><?php endif ?></section><?php endforeach ?>
</body></html>
