<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/require_login.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userName  = $_SESSION['user_name'] ?? 'Потребител';
$userEmail = $_SESSION['user_email'] ?? 'user@example.com';

$profileError = $_SESSION['profile_error'] ?? [];
$fieldErrors = $_SESSION['profile_field_errors'] ?? [];
$oldField = $_SESSION['profile_field_old'] ?? [];

unset($_SESSION['profile_error'], $_SESSION['profile_field_errors'], $_SESSION['profile_field_old']);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$messages = [
  'name'     => 'Името е обновено успешно.',
  'email'    => 'Имейлът е обновен успешно.',
  'password' => 'Паролата е сменена успешно.',
];

$errors = [
  'invalid'  => 'Невалидни входни данни.',
  'exists'   => 'Този имейл вече се използва.',
  'weak'     => 'Паролата трябва да е поне 8 символа.',
  'mismatch' => 'Паролите не съвпадат.',
  'auth'     => 'Нямате права за това действие.',
  'server'   => 'Възникна грешка. Опитайте отново.',
];
?>
<!doctype html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Настройки на профила • HTML → Печатен вид</title>

  <style>
    :root{
      --indigo: #27187E;
      --blue: #758BFD;
      --periwinkle: #AEB8FE;
      --platinum: #F1F2F6;
      --orange: #FF8600;

      --text: #0f172a;
      --muted: rgba(15,23,42,.7);
      --card: rgba(255,255,255,.95);
      --border: rgba(39,24,126,.16);
      --shadow: 0 18px 46px rgba(15,23,42,.18);
      --radius: 20px;
    }

    *{ box-sizing: border-box; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background:
        radial-gradient(1200px 700px at 15% 0%, rgba(117,139,253,.35), transparent 55%),
        radial-gradient(900px 600px at 85% 10%, rgba(255,134,0,.18), transparent 55%),
        radial-gradient(900px 700px at 50% 100%, rgba(174,184,254,.55), transparent 60%),
        var(--platinum);
      color: var(--text);
      min-height: 100vh;
    }

    a{ text-decoration:none; color: inherit; }

    header.topbar{
      position: sticky;
      top: 0;
      z-index: 10;
      padding: 16px 22px;
      display:flex;
      align-items:center;
      justify-content: space-between;
      background: rgba(241,242,246,.9);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid rgba(39,24,126,.12);
    }

    .brand{
      display:flex;
      align-items:center;
      gap: 12px;
    }
    .logo{
      width: 40px;
      height: 40px;
      border-radius: 14px;
      display:grid;
      place-items:center;
      background: linear-gradient(135deg, rgba(39,24,126,.95), rgba(117,139,253,.85));
      color:white;
      font-weight: 900;
    }
    .brand strong{
      font-size: 14px;
      font-weight: 900;
      color: var(--indigo);
    }
    .brand small{
      display:block;
      font-size: 12px;
      color: rgba(15,23,42,.6);
    }

    nav.actions{
      display:flex;
      gap: 10px;
    }
    .btn{
      padding: 10px 14px;
      border-radius: 14px;
      font-size: 13px;
      font-weight: 900;
      border: 1px solid rgba(39,24,126,.18);
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
      max-width: 900px;
      margin: 0 auto;
      padding: 28px 22px;
      display:grid;
      gap: 22px;

      justify-items: center;
    }

    h1{
      margin: 0;
      font-size: 26px;
      color: var(--indigo);
    }

    section.card{
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
      padding: 22px;

      margin: 0 auto;
      max-width: 520px;
      width: 100%;
    }

    section.card header{
      margin-bottom: 12px;
    }

    section.card h2{
      margin:0 0 4px;
      font-size: 18px;
    }

    section.card p{
      margin:0;
      font-size: 13px;
      color: var(--muted);
    }

    .field-error{
      font-size: 12px;
      color: rgba(255,134,0,1);
    }

    .success{
      font-size: 12px;
      color: rgb(16, 171, 39);
    }

    form{
      margin-top: 14px;
      display:grid;
      gap: 12px;
    }

    label{
      display:flex;
      flex-direction: column;
      gap: 6px;
      font-size: 12px;
      font-weight: 900;
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
      margin-top: 6px;
      align-self: flex-start;
      padding: 10px 18px;
      border-radius: 14px;
      border: 1px solid rgba(39,24,126,.18);
      font-weight: 900;
      font-size: 13px;
      cursor:pointer;
    }

    .submit.primary{
      background: linear-gradient(135deg, var(--orange), #ff9b2f);
      color: white;
      border-color: rgba(255,134,0,.45);
    }

    .alert{
      border-radius: 16px;
      padding: 12px;
      background: rgba(174,184,254,.22);
      border: 1px solid rgba(39,24,126,.2);
    }
    .alert.error{
      background: rgba(255,134,0,.12);
      border-color: rgba(255,134,0,.45);
    }
  </style>
</head>

<body>
  <header class="topbar">
    <a class="brand" href="dashboard.php">
      <span class="logo">⎙</span>
      <span>
        <strong>HTMLPrint</strong>
      </span>
    </a>

    <nav class="actions">
      <a class="btn secondary" href="dashboard.php">Профил</a>
    </nav>
  </header>

  <main>
    <h1>Настройки на профила</h1>

    <section class="card">
      <header>
        <h2>Име</h2>
      </header>

      <form method="post" action="./api/update-name.php" novalidate>
        <input id="name" name="name" type="text" value="<?= h($oldField['name'] ?? $userName) ?>">

        <?php if (!empty($fieldErrors['name'])): ?>
          <div class="field-error"><?php echo htmlspecialchars($fieldErrors['name']); ?></div>
        <?php endif; ?>

        <?php if (!empty($profileError['name']) && $profileError['name'] === 'success'): ?>
          <div class="success"><?php echo htmlspecialchars('Успешно променихте името.'); ?></div>
        <?php endif; ?>

        <button class="submit primary" type="submit">Запази</button>
      </form>
    </section>

    <section class="card">
      <header>
        <h2>Имейл</h2>
      </header>

      <form method="post" action="./api/update-email.php" novalidate>
        <input id="email" name="email" type="email" value="<?= h($oldField['email'] ?? $userEmail) ?>">

        <?php if (!empty($fieldErrors['email'])): ?>
          <div class="field-error"><?php echo htmlspecialchars($fieldErrors['email']); ?></div>
        <?php endif; ?>

        <?php if (!empty($profileError['email']) && $profileError['email'] === 'success'): ?>
          <div class="success"><?php echo htmlspecialchars('Успешно променихте имейла.'); ?></div>
        <?php endif; ?>

        <button class="submit primary" type="submit">Запази</button>
      </form>
    </section>

    <section class="card">
      <header>
        <h2>Парола</h2>
      </header>

      <form method="post" action="./api/update-password.php" novalidate>
        <label for="password">Нова парола</label>
        <input id="password" name="password" type="password">

        <?php if (!empty($fieldErrors['password'])): ?>
          <div class="field-error"><?php echo htmlspecialchars($fieldErrors['password']); ?></div>
        <?php endif; ?>

        <label for="password2">Потвърди парола</label>
        <input id="password2" name="password2" type="password">

        <?php if (!empty($fieldErrors['password2'])): ?>
          <div class="field-error"><?php echo htmlspecialchars($fieldErrors['password2']); ?></div>
        <?php endif; ?>

        <?php if (!empty($profileError['password2']) && $profileError['password2'] === 'success'): ?>
          <div class="success"><?php echo htmlspecialchars('Успешно променихте паролата.'); ?></div>
        <?php endif; ?>

        <button class="submit primary" type="submit">Смени паролата</button>
      </form>
    </section>
  </main>
</body>
</html>