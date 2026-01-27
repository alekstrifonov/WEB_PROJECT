<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = $_SESSION['login_error'] ?? '';
$oldEmail = $_SESSION['login_old']['email'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_old']);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HTMLPrint</title>

  <style>
    :root{
      --indigo: #27187E;
      --blue: #758BFD;
      --periwinkle: #AEB8FE;
      --platinum: #F1F2F6;
      --orange: #FF8600;

      --text: #0f172a;
      --muted: rgba(15,23,42,.72);
      --card: rgba(255,255,255,.92);
      --border: rgba(39,24,126,.16);
      --shadow: 0 18px 46px rgba(15,23,42,.18);
      --radius: 20px;
    }

    *{ box-sizing: border-box; }
    html, body { height: 100%; }
    body{
      margin:0;
      font: 15px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color: var(--text);
      background:
        radial-gradient(1200px 700px at 15% 0%, rgba(117,139,253,.35), transparent 55%),
        radial-gradient(900px 600px at 85% 10%, rgba(255,134,0,.18), transparent 55%),
        radial-gradient(900px 700px at 50% 100%, rgba(174,184,254,.55), transparent 60%),
        var(--platinum);
    }

    a{ color: var(--indigo); text-decoration: none; }
    a:hover{ text-decoration: underline; }

    header.topbar{
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      padding: 16px 22px;
      display:flex;
      align-items:center;
      justify-content: space-between;
      z-index: 10;
      background: linear-gradient(
        to bottom,
        rgba(241,242,246,.95),
        rgba(241,242,246,.65),
        transparent
      );
      backdrop-filter: blur(8px);
    }

    .brand{
      display:flex;
      align-items:center;
      gap: 12px;
    }
    .logo{
      width: 42px;
      height: 42px;
      border-radius: 16px;
      display:grid;
      place-items:center;
      background: linear-gradient(135deg, rgba(39,24,126,.95), rgba(117,139,253,.85));
      color: white;
      font-weight: 900;
    }
    .brand strong{
      display:block;
      font-size: 14px;
      font-weight: 900;
      color: var(--indigo);
      letter-spacing: .2px;
    }
    .brand small{
      display:block;
      font-size: 12px;
      color: rgba(15,23,42,.65);
    }

    nav.actions{
      display:flex;
      gap: 10px;
      align-items:center;
    }
    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding: 10px 14px;
      border-radius: 14px;
      border: 1px solid rgba(39,24,126,.18);
      font-weight: 900;
      font-size: 13px;
      cursor:pointer;
      user-select:none;
    }
    .btn.secondary{
      background: rgba(117,139,253,.14);
      color: var(--indigo);
    }
    .btn.primary{
      background: linear-gradient(135deg, var(--orange), #ff9b2f);
      border-color: rgba(255,134,0,.45);
      color: white;
    }

    main{
      min-height: 100%;
      display:grid;
      place-items:center;
      padding: 92px 22px 28px;
    }

    .card{
      width: 100%;
      max-width: 520px;
      border-radius: var(--radius);
      background: var(--card);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      padding: 22px;
    }

    h1{
      margin: 0 0 6px;
      font-size: 24px;
      letter-spacing: .2px;
      color: var(--indigo);
    }
    .sub{
      margin: 0 0 14px;
      color: var(--muted);
      font-size: 13px;
    }

    .alert{
      border-radius: 16px;
      padding: 12px 12px;
      border: 1px solid rgba(39,24,126,.20);
      background: rgba(174,184,254,.22);
      color: rgba(15,23,42,.9);
      margin-bottom: 14px;
    }
    .alert.error{
      border-color: rgba(255,134,0,.45);
      background: rgba(255,134,0,.12);
    }

    .error{
      font-size: 12px;
      color: rgba(255,134,0,1);
    }

    form{
      display:grid;
      gap: 10px;
    }
    fieldset{
      border:0;
      padding:0;
      margin:0;
      display:grid;
      gap: 10px;
    }
    label{
      display:flex;
      flex-direction: column;
      gap: 6px;
      font-size: 12px;
      font-weight: 900;
      color: rgba(15,23,42,.82);
    }
    input{
      padding: 11px 12px;
      border-radius: 14px;
      border: 1px solid rgba(39,24,126,.18);
      background: rgba(255,255,255,.92);
      outline:none;
      font-size: 14px;
    }
    input:focus{
      border-color: rgba(117,139,253,.9);
      box-shadow: 0 0 0 4px rgba(117,139,253,.18);
    }

    .actions-row{
      display:flex;
      gap: 10px;
      align-items:center;
      justify-content: space-between;
      flex-wrap: wrap;
      margin-top: 6px;
    }

    button.submit{
      width: 100%;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid rgba(255,134,0,.45);
      background: linear-gradient(135deg, var(--orange), #ff9b2f);
      color: white;
      font-weight: 1000;
      font-size: 14px;
      cursor:pointer;
    }
    button.submit:hover{ filter: brightness(1.03); }
    button.submit:active{ transform: translateY(1px); }

    .meta{
      display:flex;
      justify-content: space-between;
      gap: 10px;
      margin-top: 12px;
      color: rgba(15,23,42,.58);
      font-size: 12px;
      flex-wrap: wrap;
    }

    .hint{
      margin-top: 10px;
      font-size: 12px;
      color: rgba(15,23,42,.68);
    }

    @media (max-width: 480px){
      .card{ padding: 18px; }
      h1{ font-size: 22px; }
    }
  </style>
</head>

<body>
  <header class="topbar" role="banner">
    <a class="brand" href="welcome.php" aria-label="Към началната страница">
      <span class="logo" aria-hidden="true">⎙</span>
      <span>
        <strong>HTMLPrint</strong>
      </span>
    </a>
  </header>

  <main role="main">
    <article class="card" aria-labelledby="login-title">
      <header>
        <h1 id="login-title">Вход</h1>
      </header>

      <?php if (!empty($error)): ?>
          <div class="error"><?php echo h($error); ?></div>
      <?php endif; ?>

      <form method="post" action="./api/login.php" autocomplete="on" novalidate>
        <fieldset>
          <label for="email">Имейл</label>
          <input id="email" name="email" type="email"
            value="<?php echo h($oldEmail); ?>" placeholder="name@example.com"/>

          <label for="password">Парола</label>
          <input id="password" name="password" type="password"/>
        </fieldset>

        <button class="submit" type="submit">Вход</button>

        <div class="actions-row">
          <span class="hint">Нямаш профил? <a href="register.php"><strong>Регистрирай се</strong></a></span>
        </div>

      </form>
    </article>
  </main>
</body>
</html>
