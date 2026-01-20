<?php
declare(strict_types=1);

session_start();

// --- MOCK DATA (замени с ProjectService) ---
$userName = $_SESSION['user_name'] ?? 'Потребител';

// Примерен формат, който ProjectService::listByUser() би върнал
$projects = [
  [
    'id' => 1,
    'title' => 'Реферат по Уеб технологии',
    'created_at' => '2026-01-03',
    'updated_at' => '2026-01-05'
  ],
  [
    'id' => 2,
    'title' => 'HTML код – лабораторно',
    'created_at' => '2026-01-02',
    'updated_at' => '2026-01-04'
  ]
];
// Ако искаш да тестваш празно състояние:
// $projects = [];
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
      color: var(--text);
      background:
        radial-gradient(1200px 700px at 15% 0%, rgba(117,139,253,.35), transparent 55%),
        radial-gradient(900px 600px at 85% 10%, rgba(255,134,0,.18), transparent 55%),
        radial-gradient(900px 700px at 50% 100%, rgba(174,184,254,.55), transparent 60%),
        var(--platinum);
      min-height: 100vh;
    }

    a{ text-decoration:none; color: inherit; }

    /* Topbar */
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
      align-items:center;
    }

    .btn{
      padding: 10px 14px;
      border-radius: 14px;
      font-size: 13px;
      font-weight: 900;
      border: 1px solid rgba(39,24,126,.18);
      cursor:pointer;
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
      padding: 28px 22px;
      max-width: 1100px;
      margin: 0 auto;
      display:grid;
      gap: 22px;
    }

    .welcome{
      background: linear-gradient(135deg, rgba(39,24,126,.92), rgba(117,139,253,.82));
      color:white;
      border-radius: var(--radius);
      padding: 26px;
      box-shadow: var(--shadow);
    }
    .welcome h1{
      margin: 0 0 6px;
      font-size: 26px;
    }
    .welcome p{
      margin:0;
      opacity:.95;
    }

    section.projects{
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 22px;
      border: 1px solid var(--border);
    }

    section.projects header{
      display:flex;
      justify-content: space-between;
      align-items:center;
      margin-bottom: 14px;
      flex-wrap: wrap;
      gap: 10px;
    }

    section.projects h2{
      margin:0;
      font-size: 20px;
      color: var(--indigo);
    }

    ul.project-list{
      list-style:none;
      padding:0;
      margin:0;
      display:grid;
      gap: 12px;
    }

    .project{
      border: 1px solid rgba(39,24,126,.14);
      border-radius: 16px;
      padding: 14px 16px;
      display:flex;
      justify-content: space-between;
      align-items:center;
      gap: 12px;
      background: rgba(255,255,255,.98);
    }

    .project h3{
      margin:0;
      font-size: 15px;
    }

    .project small{
      color: var(--muted);
      font-size: 12px;
    }

    .project .actions{
      display:flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .empty{
      text-align:center;
      padding: 28px;
      color: var(--muted);
      font-size: 14px;
      border: 1px dashed rgba(39,24,126,.3);
      border-radius: 16px;
    }

    footer.meta{
      font-size: 12px;
      color: rgba(15,23,42,.55);
      display:flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
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
      <a class="btn secondary" href="profile-settings.php">Настройки на профила</a>
      <a class="btn primary" href="welcome.php">Изход</a>
    </nav>
  </header>

  <main>
    <!-- Greeting -->
    <section class="welcome" aria-label="Поздрав">
      <h1>Здравей, <?= htmlspecialchars($userName) ?> 👋</h1>
      <p>Оттук можеш да управляваш своите проекти и настройки.</p>
    </section>

    <!-- Projects -->
    <section class="projects" aria-labelledby="projects-title">
      <header>
        <h2 id="projects-title">Моите проекти</h2>
        <a class="btn primary" href="editor.php">Нов проект</a>
      </header>

      <?php if (empty($projects)): ?>
        <div class="empty">
          Все още нямаш запазени проекти.<br>
          Създай първия си проект.
        </div>
      <?php else: ?>
        <ul class="project-list">
          <?php foreach ($projects as $p): ?>
            <li class="project">
              <div>
                <h3><?= htmlspecialchars($p['title']) ?></h3>
                <small>
                  Създаден: <?= htmlspecialchars($p['created_at']) ?> •
                  Последна промяна: <?= htmlspecialchars($p['updated_at']) ?>
                </small>
              </div>
              <div class="actions">
                <a class="btn secondary" href="editor.php?project_id=<?= (int)$p['id'] ?>">
                  Отвори
                </a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
