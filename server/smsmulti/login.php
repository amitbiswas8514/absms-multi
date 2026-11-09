<?php
require_once __DIR__.'/common.php';
gateway_admin_session();
if (! empty($_SESSION['admin_authenticated'])) {
    header('Location: monitor.php', true, 303);
    exit;
}

$error = '';
$passwordHash = (string) getenv('SMS_GATEWAY_ADMIN_PASSWORD_HASH');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    gateway_verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if ($passwordHash === '') {
        $error = 'Admin password is not configured on the server.';
    } elseif (gateway_login_throttled()) {
        http_response_code(429);
        $error = 'Too many failed attempts. Wait ten minutes and try again.';
    } elseif (password_verify($password, $passwordHash)) {
        gateway_clear_login_failures();
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: monitor.php', true, 303);
        exit;
    } else {
        gateway_record_login_failure();
        $error = 'Sign-in failed.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · SMS gateway</title>
<style>body{font:16px system-ui,sans-serif;max-width:28rem;margin:12vh auto;padding:0 1rem;color:#172033}label{display:block;margin:.8rem 0 .3rem}input{box-sizing:border-box;width:100%;padding:.7rem;border:1px solid #9aa5b5;border-radius:.4rem;font:inherit}button{margin-top:1rem;padding:.7rem 1rem}.error{color:#9b1c1c}</style></head>
<body><h1>SMS gateway admin</h1><?php if ($error !== ''): ?><p class="error" role="alert"><?= gateway_escape($error) ?></p><?php endif ?>
<form method="post" action="login.php"><input type="hidden" name="_csrf" value="<?= gateway_escape(gateway_csrf_token()) ?>"><label for="password">Admin password</label><input id="password" type="password" name="password" autocomplete="current-password" required autofocus><button type="submit">Sign in</button></form></body></html>
