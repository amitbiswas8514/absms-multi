<?php
/* =========================================================
   SEND SMS API
   - POST  → JSON API (queue SMS)
   - GET ?view=form → HTML form
========================================================= */

$file = "sms.json";

/* ---------- INIT ---------- */
if (!file_exists($file)) {
    file_put_contents($file, "[]");
}

/* =========================================================
   POST → ADD SMS TO QUEUE (API)
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header("Content-Type: application/json");

    $data = json_decode(file_get_contents($file), true);

    $phone   = trim($_POST['phone'] ?? "");
    $message = trim($_POST['message'] ?? "");

    if ($phone === "" || $message === "") {
        echo json_encode([
            "success" => false,
            "error"   => "Phone or message empty"
        ]);
        exit;
    }

    /* ---------- SAFE ID GENERATION ---------- */
    $maxId = 0;
    foreach ($data as $row) {
        if ($row['id'] > $maxId) {
            $maxId = $row['id'];
        }
    }
    $newId = $maxId + 1;

    /* ---------- NEW SMS OBJECT ---------- */
    $newSMS = [
        "id"        => $newId,
        "phone"     => $phone,
        "message"   => $message,
        "status"    => "unsent",
        "locked_by" => "",
        "locked_at" => 0,
        "created_at"=> date("Y-m-d H:i:s")
    ];

    $data[] = $newSMS;

    file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT),
        LOCK_EX
    );

    echo json_encode([
        "success" => true,
        "message" => "SMS added to queue",
        "sms"     => $newSMS
    ]);
    exit;
}

/* =========================================================
   BROWSER FORM (ONLY IF ?view=form)
========================================================= */
if (isset($_GET['view']) && $_GET['view'] === 'form') {
?>
<!DOCTYPE html>
<html>
<head>
<title>Send SMS</title>
<style>
body {
    font-family: Arial;
    background: #f5f5f5;
    padding: 20px;
}
.box {
    background: #fff;
    max-width: 420px;
    margin: auto;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 0 10px #ccc;
}
input, textarea {
    width: 100%;
    padding: 10px;
    margin-top: 10px;
    border-radius: 5px;
    border: 1px solid #bbb;
}
button {
    width: 100%;
    padding: 12px;
    background: #007bff;
    border: none;
    color: white;
    font-size: 16px;
    border-radius: 5px;
    margin-top: 15px;
    cursor: pointer;
}
</style>
</head>
<body>

<div class="box">
    <h2>📩 Add SMS to Queue</h2>
    <form method="POST">
        <label>Phone Number</label>
        <input type="text" name="phone" placeholder="+91XXXXXXXXXX" required>

        <label>Message</label>
        <textarea name="message" rows="5" required></textarea>

        <button type="submit">➕ Add SMS</button>
    </form>
</div>

</body>
</html>
<?php
exit;
}

/* =========================================================
   DEFAULT FALLBACK
========================================================= */
header("Content-Type: application/json");
echo json_encode([
    "success" => false,
    "error"   => "Invalid request"
]);
exit;
