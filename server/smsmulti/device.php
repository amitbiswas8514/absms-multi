<?php
require_once __DIR__.'/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'ping') {
    $device = gateway_require_device();
    $statement = gateway_db()->prepare('UPDATE devices SET last_seen = ? WHERE id = ? AND active = 1');
    $statement->execute([time(), $device['id']]);
    gateway_json(['online' => true]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    gateway_json(['error' => 'Method not allowed.'], 405);
}

gateway_require_admin();
$devices = gateway_db()->query('SELECT id, label, device_id, active, last_seen FROM devices ORDER BY created_at DESC')->fetchAll();
gateway_json($devices);
