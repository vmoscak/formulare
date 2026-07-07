<?php
/**
 * Vstupná brána pre celú doménu — jedna zdieľaná bezpečnostná fráza.
 * Po správnom zadaní nastaví dlhodobú cookie (gate_auth), ktorú si
 * .htaccess overuje pri každej ďalšej požiadavke (viď RewriteCond).
 */
require_once __DIR__ . '/config.local.php';

function safeNextPath(?string $path): string {
    if ($path === null || $path === '') return '/';
    // Len bezpečná relatívna cesta — žiadny "//host" alebo "http://" open-redirect
    if (preg_match('#^/[A-Za-z0-9/_.\-?=&%]*$#', $path) && !str_starts_with($path, '//')) {
        return $path;
    }
    return '/';
}

$next = safeNextPath($_SERVER['REDIRECT_URL'] ?? ($_GET['next'] ?? null));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = (string)($_POST['passphrase'] ?? '');
    if (hash_equals(GATE_PASSPHRASE, $entered)) {
        setcookie('gate_auth', GATE_TOKEN, [
            'expires'  => time() + 365 * 86400,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: ' . safeNextPath($_POST['next'] ?? $next));
        exit;
    }
    $error = 'Nesprávna fráza, skús to znova.';
}
?>
<!DOCTYPE html><html lang="sk"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Vstup pre poradcov</title>
<style>
  :root{ --accent:#1f5fd1; --accent-soft:#eaf1fc; --ink:#1d2536; --muted:#8b94a3; --border:#e2e6ee; --bg:#eef1f6; --err:#d9362b; }
  *{box-sizing:border-box;}
  body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:var(--bg); color:var(--ink); font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; padding:24px; }
  .card{ width:100%; max-width:380px; background:#fff; border-radius:20px; padding:34px 30px;
    box-shadow:0 12px 44px rgba(30,38,56,.12); text-align:center; }
  .logo{ width:48px; height:48px; border-radius:14px; background:var(--accent); color:#fff; margin:0 auto 18px;
    display:flex; align-items:center; justify-content:center; font-weight:800; font-size:20px; }
  h1{ font-size:18px; margin:0 0 8px; }
  p.sub{ font-size:13px; color:var(--muted); margin:0 0 24px; }
  input{ width:100%; padding:13px 14px; border:1.5px solid var(--border); border-radius:12px; font-size:15px;
    text-align:center; margin-bottom:14px; }
  input:focus{ outline:none; border-color:var(--accent); }
  button{ width:100%; padding:13px; border:none; border-radius:12px; background:var(--accent); color:#fff;
    font-weight:700; font-size:14.5px; cursor:pointer; }
  .error{ color:var(--err); font-size:13px; font-weight:600; margin-bottom:14px; }
</style>
</head><body>
  <div class="card">
    <div class="logo">M</div>
    <h1>Vstup pre poradcov</h1>
    <p class="sub">Zadaj bezpečnostnú frázu</p>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="password" name="passphrase" placeholder="Bezpečnostná fráza" autofocus required>
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <button type="submit">Vstúpiť</button>
    </form>
  </div>
</body></html>
