<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HTML → Печатен вид</title>

  <style>
    :root{
      --indigo: #27187E;
      --blue: #758BFD;
      --periwinkle: #AEB8FE;
      --platinum: #F1F2F6;
      --orange: #FF8600;

      --text-light: #ffffff;
      --text-muted: rgba(255,255,255,.85);
      --shadow: 0 18px 46px rgba(15,23,42,.25);
      --radius: 18px;
    }

    *{ box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }

    body{
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--indigo);
      color: var(--text-light);
    }

    a{ text-decoration: none; }

    /* TOP BAR */
    header{
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      padding: 18px 28px;
      display: flex;
      justify-content: flex-end;
      align-items: center;
      z-index: 10;
      background: linear-gradient(
        to bottom,
        rgba(39,24,126,.85),
        rgba(39,24,126,.25),
        transparent
      );
    }

    nav.actions{
      display: flex;
      gap: 12px;
    }

    .btn{
      padding: 10px 18px;
      border-radius: var(--radius);
      font-weight: 800;
      font-size: 14px;
      border: 1px solid rgba(255,255,255,.35);
      color: white;
      backdrop-filter: blur(6px);
    }

    .btn.login{
      background: rgba(255,255,255,.14);
    }

    .btn.register{
      background: linear-gradient(135deg, var(--orange), #ff9b2f);
      border-color: rgba(255,134,0,.8);
    }

    .btn:hover{ filter: brightness(1.05); }

    /* HERO FULLSCREEN */
    main{
      height: 100vh;
    }

    section.hero{
      height: 100%;
      padding: 48px 32px;
      display: grid; /* flex */
      place-items: center;
      align-items: center;
      position: relative;
      overflow: hidden;

      background:
        radial-gradient(1200px 800px at 20% 0%, rgba(117,139,253,.55), transparent 60%),
        radial-gradient(900px 600px at 85% 20%, rgba(255,134,0,.30), transparent 60%),
        radial-gradient(900px 700px at 50% 100%, rgba(174,184,254,.55), transparent 65%),
        linear-gradient(135deg, rgba(39,24,126,1), rgba(39,24,126,.92));
    }

    header.topbar{
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;

        padding: 18px 28px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        z-index: 10;

        background: linear-gradient(
            to bottom,
            rgba(39,24,126,.85),
            rgba(39,24,126,.25),
            transparent
        );
    }

    section.hero::after{
      content:"";
      position:absolute;
      bottom:-180px;
      left:-180px;
      width: 520px;
      height: 520px;
      background: radial-gradient(circle, rgba(174,184,254,.95), rgba(174,184,254,0) 65%);
      opacity:.9;
    }

    .hero-content{
      max-width: 760px;
      position: relative;
      z-index: 1;
    }

    .brand{
      display:flex;
      align-items:center;
      gap: 14px;
      margin-bottom: 28px;
    }

    .logo{
      width: 56px;
      height: 56px;
      border-radius: 18px;
      display: grid;
      place-items: center;
      background: rgba(255,255,255,.18);
      font-size: 22px;
      font-weight: 900;
    }

    .brand h1{
      margin: 0;
      font-size: 24px;
      font-weight: 900;
    }

    .brand p{
      margin: 2px 0 0;
      font-size: 13px;
      opacity: .9;
    }

    .hero-content h2{
      font-size: 44px;
      line-height: 1.1;
      margin: 0 0 16px;
      font-weight: 900;
    }

    .hero-content > p{
      max-width: 65ch;
      font-size: 17px;
      margin: 0;
      color: var(--text-muted);
    }

    ul.features{
      margin: 26px 0 0;
      padding: 0;
      list-style: none;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
      max-width: 640px;
    }

    ul.features li{
      background: rgba(255,255,255,.16);
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 16px;
      padding: 14px;
      font-size: 14px;
    }

    footer.note{
      position: absolute;
      bottom: 18px;
      left: 32px;
      font-size: 12px;
      opacity: .7;
    }

    @media (max-width: 900px){
      .hero-content h2{ font-size: 34px; }
      ul.features{ grid-template-columns: 1fr; }
    }
  </style>
</head>

<body>
  <!-- TOP RIGHT ACTIONS -->
  <header class="topbar">
    <div class="brand">
        <div class="logo" aria-hidden="true">⎙</div>
            <div>
              <h1 id="site-title">HTMLPrint</h1>
            </div>
    </div>

    <nav class="actions" aria-label="Потребителски действия">
      <a href="login.php" class="btn login">Вход</a>
      <a href="register.php" class="btn register">Регистрация</a>
    </nav>

  </header>

  <!-- FULLSCREEN HERO -->
  <main>
    <section class="hero" aria-labelledby="site-title">
      <div class="hero-content">

        <h2>Превърнете HTML документ в удобен за печат формат</h2>
        <p>
          Уеб приложение за автоматично преобразуване на HTML файлове
          в структуриран, стандартизиран и удобен за печат документ –
          подходящ за реферати, курсови работи и програмен код.
        </p>

        <ul class="features">
          <li><strong>Гъвкаво странициране</strong></li>
          <li><strong>Номерация</strong></li>
          <li><strong>Генериране на метаданни</strong></li>
          <li><strong>Генериране на статистики</strong></li>
        </ul>
      </div>
    </section>
  </main>
</body>
</html>
