<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTML - Печатен вид</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>

    <header class="app-header">
        <div class="header-content">
            <div class="logo-area">
                <h1>HTML — Печатен вид</h1>
            </div>
        
            <div class="header-actions">
                <button type="button" class="btn-reset">Нулиране</button>
                <button type="button" class="btn-save">Запази</button>
                <div class="account-area">
                    <div class="account-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
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
                        <input type="file" id="file-explorer" name="html_files[]" hidden multiple>
                        <button type="button" class="btn-browse" onclick="document.getElementById('file-explorer').click()">Избери файлове</button>
    
                        <div id="error-display" style="color: var(--orange); font-size: 0.8rem; margin-top: 10px; display: none;"></div>
    
                        <div id="file-list"></div>
                        <span id="upload-status">или ги пусни тук</span>
                    </div>

                    <div class="form-actions" style="margin-top: 20px; padding: 10px;">
                        <button type="submit" class="btn-generate" style="width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                            ГЕНЕРИРАЙ ПРЕГЛЕД
                        </button>
                    </div>
                </section>

                <section class="form-section">
                    <h2>МЕТАДАННИ</h2>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Заглавие</label>
                            <input type="text" name="title" placeholder="Напр. Реферат по...">
                        </div>
                        <div class="input-group">
                            <label>Автор</label>
                            <input type="text" name="author" placeholder="Име Фамилия">
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Курс / група</label>
                            <input type="text" name="course" placeholder="Напр. 3 курс">
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Цитиране (шаблон)</label>
                        <textarea name="citation_template">{author}. {title}. Източник: {source}. Достъп: {access}</textarea>
                        <small>Поддържани плейсхолдъри: {author}, {title}, {source}, {access}</small>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Метаданни страница</label>
                            <select name="metadata_placement">
                                <option value="none">Без</option>
                                <option value="start">Като първа страница</option>
                                <option value="end">Като последна страница</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Статистики</label>
                            <select name="statistics_placement">
                                <option value="none">Без</option>
                                <option value="start">В началото</option>
                                <option value="end">В края</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h2>СТРАНИЦА</h2>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Размер</label>
                            <select id="pageSize" name="page_size">
                                <option value="a3">A3 (297x420 мм)</option>
                                <option value="a4" selected>A4 (210x297 мм)</option>
                                <option value="a5">A5 (148x210 мм)</option>
                                <option value="letter">Letter (8.5 x 11")</option>
                                <option value="legal">Legal (8.5 x 14")</option>
                                <option value="custom">Персонализиран</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Ориентация</label>
                            <select name="orientation">
                                <option value="portrait">Портрет</option>
                                <option value="landscape">Пейзаж</option>
                            </select>
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Ширина (мм)</label>
                            <input type="number" name="page_width" value="210">
                        </div>
                        <div class="input-group">
                            <label>Височина (мм)</label>
                            <input type="number" name="page_height" value="297">
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Марж (мм)</label>
                            <input type="number" name="margin" value="18">
                        </div>
                        <div class="input-group">
                            <label>Шрифт (pt)</label>
                            <input type="number" name="font_size" value="12">
                        </div>
                        <div class="input-group">
                            <label>Междуредие</label>
                            <select name="line_spacing">
                                <option value="normal">Нормална машинописна</option>
                                <option value="wide">Разредена</option>
                                <option value="tight">Сгъстена</option>
                                <option value="custom">Персонализирана</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h2>НОМЕРАЦИЯ</h2>
                    <div class="checkbox-row">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="show_page_numbers" value="1" checked>
                            <span>Покажи номера на страниците</span>
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="no_number_on_first" value="1">
                            <span>Без номер на първата</span>
                        </label>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Позиция</label>
                            <select name="page_number_pos">
                                <option value="footer">Долу (footer)</option>
                                <option value="header">Горе (header)</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Формат</label>
                            <select name="page_number_format">
                                <option value="123">1, 2, 3...</option>
                                <option value="roman-upper">I, II, III...</option>
                                <option value="roman-lower">i, ii, iii...</option>
                                <option value="latin-upper">A, B, C...</option>
                                <option value="latin-lower">a, b, c...</option>
                                <option value="bg-upper">А, Б, В...</option>
                                <option value="bg-lower">а, б, в...</option>
                            </select>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Шаблон за номер (footer/header)</label>
                        <input type="text" name="page_number_template" value="{author} • {title} • стр. {page}/{total}">
                        <small>Плейсхолдъри: {page}, {total}, {title}, {author}, {fn}, {file}, {date}</small>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Номера на редове</label>
                            <select name="line_numbers_mode">
                                <option value="none">Без</option>
                                <option value="page">Само за текущата страница</option>
                                <option value="doc">За целия документ</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Къде</label>
                            <select name="line_numbers_placement">
                                <option value="margin">Вляво</option>
                                <option value="inline">В началото на реда</option>
                            </select>
                        </div>
                    </div>
                    <label class="custom-checkbox">
                        <input type="checkbox" name="show_line_numbers_print" value="1" checked>
                        <span>Покажи номерата на редове при печат</span>
                    </label>
                </section>

                <section class="form-section">
                    <h2>СЕКЦИИ</h2>
                    <label class="custom-checkbox">
                        <input type="checkbox" name="new_page_on_header" value="1" checked>
                        <span>Започвай нова страница при заглавие (H1-H6)</span>
                    </label>
                    <label class="custom-checkbox" style="margin-top: 8px;">
                        <input type="checkbox" name="new_page_on_file" value="1" checked>
                        <span>Всеки файл започва на нова страница</span>
                    </label>
                    <label class="custom-checkbox" style="margin-top: 8px;">
                        <input type="checkbox" name="file_name_as_section" value="1" checked>
                        <span>Покажи име на файл като секция</span>
                    </label>
                    <div class="input-row" style="margin-top: 15px;">
                        <div class="input-group">
                            <label>Норматив (за статистики)</label>
                            <select name="stats_normative">
                                <option value="normal">Нормална машинописна</option>
                                <option value="wide">Разредена</option>
                                <option value="tight">Сгъстена</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Думи за стр.</label>
                            <input type="number" name="words_per_page" value="250">
                        </div>
                    </div>
                    <div class="input-group" style="margin-top: 10px;">
                        <label>Пренасяй дълги редове</label>
                        <select name="wrap_lines">
                            <option value="yes">Да</option>
                            <option value="no">Не</option>
                        </select>
                    </div>
                </section>
            </form>
        </aside>

        <section class="preview-area">
            <div class="empty-state">
                Избери HTML файл/ове, за да видиш странирания резултат.
            </div>
        </section>
    </main>

    <script src="assets/PreviewPane.js"></script>
    <script src="assets/SubmitForm.js"></script>
    <script src="assets/FileUploader.js"></script>
</body>
</html>