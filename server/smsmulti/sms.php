<?php
header("Content-Type: application/json");

$file = "sms.json";
if (!file_exists($file)) {
    file_put_contents($file, "[]");
}

$data = json_decode(file_get_contents($file), true);

$sms    = $_GET['sms']    ?? "";
$action = $_GET['action'] ?? "";
$id     = intval($_GET['id'] ?? 0);
$device = $_GET['device'] ?? "unknown";

$now = time();
$LOCK_TIMEOUT = 30; // seconds

// ------------------------------------------------------
// 1️⃣ FETCH & LOCK ONE UNSENT SMS (ATOMIC)
// ------------------------------------------------------
if ($sms === "unsent") {

    foreach ($data as &$row) {

        // Release stale locks
        if ($row['status'] === "processing" &&
            ($now - ($row['locked_at'] ?? 0)) > $LOCK_TIMEOUT) {

            $row['status'] = "unsent";
            $row['locked_by'] = "";
            $row['locked_at'] = 0;
        }

        if ($row['status'] === "unsent") {

            // LOCK IT
            $row['status'] = "processing";
            $row['locked_by'] = $device;
            $row['locked_at'] = $now;

            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

            echo json_encode([$row]);
            exit;
        }
    }

    echo json_encode([]);
    exit;
}

// ------------------------------------------------------
// 2️⃣ MARK SENT
// ------------------------------------------------------
if ($action === "mark_sent" && $id > 0) {

    foreach ($data as &$row) {
        if ($row['id'] == $id) {
            $row['status'] = "sent";
            $row['locked_by'] = "";
            $row['locked_at'] = 0;
        }
    }

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(["success" => true]);
    exit;
}

// ------------------------------------------------------
// 3️⃣ MARK FAILED
// ------------------------------------------------------
if ($action === "mark_failed" && $id > 0) {

    foreach ($data as &$row) {
        if ($row['id'] == $id) {
            $row['status'] = "failed";
            $row['locked_by'] = "";
            $row['locked_at'] = 0;
        }
    }

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    echo json_encode(["success" => true]);
    exit;
}

// ------------------------------------------------------
echo json_encode(["error" => "Invalid request"]);
exit;
