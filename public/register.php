<?php
declare(strict_types=1);
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
    html, body{ height:100%; }

    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color: var(--text);
      background:
        radial-gradient(1200px 700px at 15% 0%, rgba(117,139,253,.35), transparent 55%),
        radial-gradient(900px 600px at 85% 10%, rgba(255,134,0,.18), transparent 55%),
        radial-gradient(900px 700px at 50% 100%, rgba(174,184,254,.55), transparent 60%),
        var(--platinum);
    }

    a{ color: var(--indigo); text-decoration:none; }
    a:hover{ text-decoration: underline; }

    /* Top bar */
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
    }
    .brand small{
      display:block;
      font-size: 12px;
      color: rgba(15,23,42,.65);
    }

    nav.actions{
      display:flex;
      gap: 10px;
    }

    .btn{
      padding: 10px 14px;
      border-radius: 14px;
      border: 1px solid rgba(39,24,126,.18);
      font-weight: 900;
      font-size: 13px;
    }
    .btn.secondary{
      background: rgba(117,139,253,.14);
      color: var(--indigo);
    }
    .btn.primary{
      background: linear-gradient(135deg, var(--orange), #ff9b2f);
      color: white;
      border-color: rgba(255,134,0,.45);
    }

    main{
      min-height: 100%;
      display:grid;
      place-items:center;
      padding: 92px 22px 28px;
    }

    .card{
      width: 100%;
      max-width: 560px;
      background: var(--card);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      padding: 22px;
    }

    h1{
      margin: 0 0 6px;
      font-size: 24px;
      color: var(--indigo);
    }
    .sub{
      margin: 0 0 14px;
      font-size: 13px;
      color: var(--muted);
    }

    .alert{
      border-radius: 16px;
      padding: 12px;
      margin-bottom: 14px;
      background: rgba(174,184,254,.22);
      border: 1px solid rgba(39,24,126,.2);
    }
    .alert.error{
      background: rgba(255,134,0,.12);
      border-color: rgba(255,134,0,.45);
    }

    form{
      display:grid;
      gap: 12px;
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
      font-size: 14px;
      background: rgba(255,255,255,.95);
    }
    input:focus{
      outline:none;
      border-color: rgba(117,139,253,.9);
      box-shadow: 0 0 0 4px rgba(117,139,253,.18);
    }

    button.submit{
      margin-top: 8px;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid rgba(255,134,0,.45);
      background: linear-gradient(135deg, var(--orange), #ff9b2f);
      color: white;
      font-weight: 1000;
      font-size: 14px;
      cursor:pointer;
    }

    .hint{
      margin-top: 10px;
      font-size: 12px;
      color: rgba(15,23,42,.68);
    }

    footer.meta{
      margin-top: 14px;
      font-size: 12px;
      color: rgba(15,23,42,.6);
      display:flex;
      justify-content: space-between;
      flex-wrap: wrap;
    }

    @media (max-width: 480px){
      .card{ padding: 18px; }
      h1{ font-size: 22px; }
    }
  </style>
</head>

<body>
  <header class="topbar">
    <a class="brand" href="welcome.php">
      <span class="logo">⎙</span>
      <span>
        <strong>HTMLPrint</strong>
      </span>
    </a>
  </header>

  <main>
    <article class="card" aria-labelledby="register-title">
      <header>
        <h1 id="register-title">Регистрация</h1>
      </header>

      <form method="post" action="/api/auth/register.php" autocomplete="on">
        <label for="name">Име</label>
        <input id="name" name="name" type="text" required>

        <label for="email">Имейл</label>
        <input id="email" name="email" type="email" placeholder="name@example.com" required>

        <label for="password">Парола</label>
        <input id="password" name="password" type="password" minlength="8" required>

        <label for="password2">Потвърди парола</label>
        <input id="password2" name="password2" type="password" minlength="8" required>

        <button class="submit" type="submit">Създай профил</button>

        <p class="hint">
          Вече имаш профил?
          <a href="login.php"><strong>Влез</strong></a>
        </p>
      </form>
    </article>
  </main>
</body>
</html>
