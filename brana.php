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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  /* Svetlý „Clean SaaS" vzhľad — zhodné tokeny s assets/ui.css */
  :root{
    --bg:#f5f6f8; --paper:#fff; --border:#eef0f3; --line-strong:#e2e6ec;
    --ink:#111827; --muted:#6b7280; --label:#98a2b3;
    --accent:#4f46e5; --accent-ink:#4338ca; --err:#e11d48;
    --sans:'Inter',-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    color:var(--ink); font-family:var(--sans); -webkit-font-smoothing:antialiased; padding:24px;
    background:var(--bg);
  }
  .card{
    width:100%; max-width:390px; background:var(--paper);
    border:1px solid var(--border); border-radius:16px;
    padding:36px 32px; text-align:center;
    box-shadow:0 1px 3px rgba(16,24,40,.05), 0 20px 44px -20px rgba(16,24,40,.24);
  }
  .logo{
    width:48px; height:48px; border-radius:14px; background:var(--accent); color:#fff;
    margin:0 auto 16px; display:flex; align-items:center; justify-content:center;
    box-shadow:0 8px 18px -6px rgba(79,70,229,.55);
  }
  .wordmark{
    font-size:12px; font-weight:600; letter-spacing:.02em;
    text-transform:uppercase; color:var(--label); margin-bottom:18px;
  }
  h1{font-size:19px; font-weight:700; letter-spacing:-.01em; margin:0 0 8px; color:var(--ink);}
  p.sub{font-size:13px; color:var(--muted); margin:0 0 24px;}
  input{
    width:100%; padding:12px 14px; border:1px solid var(--line-strong); border-radius:10px;
    font-size:15px; text-align:center; margin-bottom:14px; font-family:inherit; background:#f8fafc;
    transition:border-color .15s, box-shadow .15s, background .15s;
  }
  input:focus{outline:none; border-color:var(--accent); background:#fff; box-shadow:0 0 0 3px rgba(79,70,229,.14);}
  button{
    width:100%; padding:13px; border:none; border-radius:10px; background:var(--accent);
    color:#fff; font-weight:600; font-size:14px; cursor:pointer; font-family:inherit;
    box-shadow:0 8px 18px -8px rgba(79,70,229,.6); transition:background .15s;
  }
  button:hover{background:var(--accent-ink);}
  .error{color:var(--err); font-size:13px; font-weight:600; margin-bottom:14px;}
</style>
</head><body>
  <div class="card">
    <div class="logo">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
      </svg>
    </div>
    <div class="wordmark">Formuláre</div>
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
