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
  /* Dizajnový jazyk „Atrament & Meď" — rovnaké tokeny ako assets/ui.css */
  :root{
    --chrome:#181b21; --chrome-line:#2e323c; --chrome-muted:#8b919d;
    --paper:#ffffff; --ink:#1c1f26; --muted:#8a8f98; --label:#5b616b;
    --border:#dcdfe4; --accent:#b45309; --err:#b3362a;
    --mono:ui-monospace,'SF Mono','Cascadia Mono','Roboto Mono',Consolas,'Courier New',monospace;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    color:var(--ink);
    font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
    -webkit-font-smoothing:antialiased; padding:24px;
    background:
      radial-gradient(rgba(255,255,255,.055) 1px, transparent 1.5px) 0 0/22px 22px,
      linear-gradient(180deg,#20242c 0%, var(--chrome) 100%);
    background-color:var(--chrome);
  }
  .card{
    width:100%; max-width:380px; background:var(--paper); border-radius:8px;
    border-top:3px solid var(--accent); padding:34px 30px; text-align:center;
    box-shadow:0 2px 6px rgba(0,0,0,.28), 0 24px 60px -18px rgba(0,0,0,.55);
  }
  .logo{
    width:46px; height:46px; border-radius:6px; background:var(--accent); color:#fff;
    margin:0 auto 16px; display:flex; align-items:center; justify-content:center;
  }
  .wordmark{
    font-family:var(--mono); font-size:11px; font-weight:700; letter-spacing:.22em;
    text-transform:uppercase; color:var(--label); margin-bottom:18px;
  }
  h1{font-size:18px; font-weight:800; letter-spacing:-.01em; margin:0 0 8px;}
  p.sub{font-size:13px; color:var(--muted); margin:0 0 24px;}
  input{
    width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:5px;
    font-size:15px; text-align:center; margin-bottom:14px; font-family:inherit;
    transition:border-color .15s, box-shadow .15s;
  }
  input:focus{outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(180,83,9,.14);}
  button{
    width:100%; padding:13px; border:none; border-radius:5px; background:var(--ink);
    color:#fff; font-weight:700; font-size:14px; cursor:pointer; font-family:inherit;
    transition:background .15s;
  }
  button:hover{background:var(--accent);}
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
