<?php
header("Content-Type: application/json");

$file = "devices.json";
if (!file_exists($file)) file_put_contents($file, "[]");

$data = json_decode(file_get_contents($file), true);

$device = $_GET['device'] ?? "";
$action = $_GET['action'] ?? "";
$now = time();

if ($action === "ping" && $device !== "") {

    $found = false;

    foreach ($data as &$d) {
        if ($d['device_id'] === $device) {
            $d['last_seen'] = $now;
            $d['status'] = "online";
            $d['sending'] = true;
            $found = true;
        }
    }

    if (!$found) {
        $data[] = [
            "device_id" => $device,
            "last_seen" => $now,
            "status" => "online",
            "sending" => true
        ];
    }

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(["online" => true]);
    exit;
}

// Auto mark offline
foreach ($data as &$d) {
    if (($now - $d['last_seen']) > 30) {
        $d['status'] = "offline";
        $d['sending'] = false;
    }
}
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

echo json_encode($data);
