<?php
require_once __DIR__.'/common.php';

$device = gateway_require_device();
$pdo = gateway_db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && ($_GET['sms'] ?? '') === 'unsent') {
    $now = time();
    try {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        $select = $pdo->prepare("SELECT id, phone, message FROM sms
            WHERE target_device_id = ? AND (status = 'unsent' OR (status = 'processing' AND locked_at < ?))
            ORDER BY id LIMIT 1");
        $select->execute([$device['id'], $now - 60]);
        $row = $select->fetch();
        if (! $row) {
            $pdo->exec('COMMIT');
            gateway_json([]);
        }
        $claim = $pdo->prepare("UPDATE sms SET status = 'processing', locked_by = ?, locked_at = ? WHERE id = ?");
        $claim->execute([$device['device_id'], $now, $row['id']]);
        $pdo->exec('COMMIT');
        gateway_json([$row]);
    } catch (Throwable $error) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        error_log('SMS queue claim failed: '.$error::class);
        gateway_json(['error' => 'Queue temporarily unavailable.'], 503);
    }
}

if ($method === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (! in_array($action, ['mark_sent', 'mark_failed'], true) || $id === false) {
        gateway_json(['error' => 'Invalid status update.'], 400);
    }
    $status = $action === 'mark_sent' ? 'sent' : 'failed';
    $update = $pdo->prepare("UPDATE sms SET status = ?, locked_by = NULL, locked_at = NULL
        WHERE id = ? AND target_device_id = ? AND status = 'processing' AND locked_by = ?");
    $update->execute([$status, $id, $device['id'], $device['device_id']]);
    if ($update->rowCount() !== 1) {
        gateway_json(['error' => 'Message was not claimed by this device.'], 409);
    }
    gateway_json(['success' => true]);
}

gateway_json(['error' => 'Method not allowed.'], 405);
