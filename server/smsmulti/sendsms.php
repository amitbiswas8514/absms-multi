<?php
require_once __DIR__.'/common.php';
gateway_require_admin();
$pdo = gateway_db();
$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    gateway_verify_csrf();
    $deviceId = filter_var($_POST['device_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $deviceQuery = $pdo->prepare('SELECT id FROM devices WHERE id = ? AND active = 1');
    $deviceQuery->execute([$deviceId]);

    if ($deviceId === false || ! $deviceQuery->fetch()) {
        $error = 'Choose an active registered device.';
    } elseif (! preg_match('/^\+?[1-9][0-9]{6,14}$/', $phone)) {
        $error = 'Enter a valid international phone number.';
    } elseif ($message === '' || gateway_utf8_length($message) > 1600) {
        $error = 'Enter a message of at most 1,600 characters.';
    } else {
        $insert = $pdo->prepare("INSERT INTO sms (target_device_id, phone, message, status, created_at) VALUES (?, ?, ?, 'unsent', ?)");
        $insert->execute([$deviceId, $phone, $message, time()]);
        $success = 'SMS added to the selected device queue.';
    }
}

$devices = $pdo->query('SELECT id, label, device_id FROM devices WHERE active = 1 ORDER BY label')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Queue SMS</title>
<style>body{font:16px system-ui,sans-serif;max-width:42rem;margin:3rem auto;padding:0 1rem;color:#172033}label{display:block;margin-top:1rem}input,textarea,select{box-sizing:border-box;width:100%;padding:.7rem;border:1px solid #9aa5b5;border-radius:.4rem;font:inherit}button{margin-top:1rem;padding:.7rem 1rem}nav{display:flex;gap:1rem}.error{color:#9b1c1c}.success{color:#12633b}</style></head>
<body><nav><a href="monitor.php">Device monitor</a><a href="devices.php">Devices</a><form method="post" action="logout.php"><input type="hidden" name="_csrf" value="<?= gateway_escape(gateway_csrf_token()) ?>"><button type="submit">Sign out</button></form></nav>
<h1>Queue an SMS</h1>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= gateway_escape($error) ?></p><?php endif ?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= gateway_escape($success) ?></p><?php endif ?>
<?php if ($devices === []): ?><p>Register a device before queuing a message.</p><?php else: ?>
<form method="post" action="sendsms.php">
<input type="hidden" name="_csrf" value="<?= gateway_escape(gateway_csrf_token()) ?>">
<label for="device">Device</label><select id="device" name="device_id" required><?php foreach ($devices as $device): ?><option value="<?= (int) $device['id'] ?>"><?= gateway_escape($device['label'].($device['device_id'] ? ' — '.substr($device['device_id'], 0, 8) : ' — not paired yet')) ?></option><?php endforeach ?></select>
<label for="phone">Phone number</label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="+14155552671" pattern="\+?[1-9][0-9]{6,14}" maxlength="16" required>
<label for="message">Message</label><textarea id="message" name="message" rows="5" maxlength="1600" required></textarea>
<button type="submit">Add to queue</button></form><?php endif ?>
</body></html>
