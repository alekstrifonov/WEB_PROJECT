<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTML - Печатен вид</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <header class="app-header">
        <div class="header-content">
            <div class="logo-area">
                <h1>HTML — Печатен вид</h1>
            </div>
        
            <div class="account-area">
                <div class="account-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <aside class="sidebar">
            <form id="print-form">
                
                <section class="form-section">
                    <h2>ФАЙЛ</h2>
                    <div class="upload-zone">
                        <p>Избери файлове</p>
                        <span>или ги пусни тук</span>
                    </div>
                </section>

                <section class="form-section">
                    <h2>МЕТАДАННИ</h2>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Заглавие</label>
                            <input type="text" placeholder="Напр. Реферат по...">
                        </div>
                        <div class="input-group">
                            <label>Автор</label>
                            <input type="text" placeholder="Име Фамилия">
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Фак. № / ID</label>
                            <input type="text" placeholder="Напр. 12345">
                        </div>
                        <div class="input-group">
                            <label>Курс / група</label>
                            <input type="text" placeholder="Напр. 3 курс">
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Цитиране (шаблон)</label>
                        <textarea>{author}. {title}. Източник: {source}. Достъп: {access}</textarea>
                        <small>Поддържани плейсхолдъри: {author}, {title}, {source}, {access}</small>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Метаданни страница</label>
                            <select><option>Без</option></select>
                        </div>
                        <div class="input-group">
                            <label>Статистики</label>
                            <select><option>Без</option></select>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h2>СТРАНИЦА</h2>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Размер</label>
                            <select><option>A4 (210x297 мм)</option></select>
                        </div>
                        <div class="input-group">
                            <label>Ориентация</label>
                            <select><option>Портрет</option></select>
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Ширина (мм)</label>
                            <input type="number" value="210">
                        </div>
                        <div class="input-group">
                            <label>Височина (мм)</label>
                            <input type="number" value="297">
                        </div>
                        <div class="input-group">
                            <label>Марж (мм)</label>
                            <input type="number" value="18">
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Марж (мм)</label>
                            <input type="number" value="18">
                        </div>
                        <div class="input-group">
                            <label>Шрифт (pt)</label>
                            <input type="number" value="12">
                        </div>
                        <div class="input-group">
                            <label>Междуредие</label>
                            <select><option>Нормална машинописна</option></select>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h2>НОМЕРАЦИЯ</h2>
                    <div class="checkbox-row">
                        <label class="custom-checkbox">
                            <input type="checkbox" checked>
                            <span>Покажи номера на страниците</span>
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox">
                            <span>Без номер на първата</span>
                        </label>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Позиция</label>
                            <select><option>Долу (footer)</option></select>
                        </div>
                        <div class="input-group">
                            <label>Формат</label>
                            <select><option>1, 2, 3...</option></select>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Шаблон за номер (footer/header)</label>
                        <input type="text" value="{author} • {title} • стр. {page}/{total}">
                        <small>Плейсхолдъри: {page}, {total}, {title}, {author}, {fn}, {file}, {date}</small>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Номера на редове</label>
                            <select><option>Без</option></select>
                        </div>
                        <div class="input-group">
                            <label>Къде</label>
                            <select><option>Вляво (марж)</option></select>
                        </div>
                    </div>
                    <label class="custom-checkbox">
                        <input type="checkbox" checked>
                        <span>Покажи номерата на редове при печат</span>
                    </label>
                </section>

                <section class="form-section">
                    <h2>СЕКЦИИ</h2>
                    <label class="custom-checkbox">
                        <input type="checkbox" checked>
                        <span>Започвай нова страница при заглавие (H1-H6)</span>
                    </label>
                    <label class="custom-checkbox" style="margin-top: 8px;">
                        <input type="checkbox" checked>
                        <span>Всеки файл започва на нова страница</span>
                    </label>
                    <label class="custom-checkbox" style="margin-top: 8px;">
                        <input type="checkbox" checked>
                        <span>Покажи име на файл като секция</span>
                    </label>
                    <div class="input-row" style="margin-top: 15px;">
                        <div class="input-group" style="flex: 2;">
                            <label>Норматив (за статистики)</label>
                            <select><option>Нормална машинописна</option></select>
                        </div>
                        <div class="input-group">
                            <label>Думи за стр.</label>
                            <input type="number" value="250">
                        </div>
                    </div>
                </section>

                <div class="notes">
                    <h3>БЕЛЕЖКИ</h3>
                    <ul>
                        <li>Прототипът преобразува съдържанието към текстово печатен вид (удобен за реферати/код).</li>
                        <li>За сложни HTML оформления са нужни по-дълбоки алгоритми.</li>
                    </ul>
                </div>
            </form>
        </aside>

        <section class="preview-area">
            <div class="empty-state">
                Избери HTML файл/ове, за да видиш странирания резултат.
            </div>
        </section>
    </main>

    <script src="script.js"></script>
</body>
</html>