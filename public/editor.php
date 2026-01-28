<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/require_login.php';
?>
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
                <button type="button" class="btn-reset" id="print-btn">Принтиране</button>
                <button type="button" class="btn-save">Запази</button>
                <div class="account-area">

                    <a href="dashboard.php" class="account-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>   
                    </a>
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
                        <button type="button" id="generate-btn" class="btn-generate" style="width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                            ГЕНЕРИРАЙ ПРЕГЛЕД
                        </button>
                    </div>

                    <label>
                        <input type="checkbox" id="auto-gen-toggle"> 
                        Презареждай прегледа при промяна
                    </label>
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
                            <label>Допълнителна информация</label>
                            <input type="text" name="course" placeholder="Напр. версия 2.0">
                        </div>
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
                            <input type="number" id="pageWidth" name="page_width" value="210" min="0">
                        </div>
                        <div class="input-group">
                            <label>Височина (мм)</label>
                            <input type="number" id="pageHeight" name="page_height" value="297" min="0">
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Марж (мм)</label>
                            <input type="number" name="margin" value="18" min="0">
                        </div>
                        <div class="input-group">
                            <label>Шрифт (pt)</label>
                            <input type="number" name="font_size" value="12" min="0">
                        </div>
                        <div class="input-group">
                            <label>Междуредие</label>
                            <select name="line_spacing">
                                <option value="normal">Нормална машинописна</option>
                                <option value="wide">Разредена</option>
                                <option value="tight">Сгъстена</option>
                            </select>
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Лимит редове на страница (0=без лимит)</label>
                            <input type="number" name="lines" value="0" min="0">
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
                        <small>Плейсхолдъри: {page}, {total}, {title}, {author}, {file}, {date}</small>
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
                    </div>
                </section>

                <section class="form-section">
                    <h2>СЕКЦИИ</h2>
                    <div class="input-group" style="margin-bottom: 10px;">
                        <label class="custom-checkbox" style="margin: 0;">Започвай нова страница при заглавия</label>
                        <div class="checkbox-row" style="margin-top: 8px; flex-wrap: wrap; gap: 8px;">
                            <label class="custom-checkbox" style="margin: 0;">
                                <input type="checkbox" name="header_page_break_levels[]" value="1">
                                <span>H1</span>
                            </label>
                            <label class="custom-checkbox" style="margin: 0;">
                                <input type="checkbox" name="header_page_break_levels[]" value="2">
                                <span>H2</span>
                            </label>
                            <label class="custom-checkbox" style="margin: 0;">
                                <input type="checkbox" name="header_page_break_levels[]" value="3">
                                <span>H3</span>
                            </label>
                            <label class="custom-checkbox" style="margin: 0;">
                                <input type="checkbox" name="header_page_break_levels[]" value="4">
                                <span>H4</span>
                            </label>
                            <label class="custom-checkbox" style="margin: 0;">
                                <input type="checkbox" name="header_page_break_levels[]" value="5">
                                <span>H5</span>
                            </label>
                            <label class="custom-checkbox" style="margin: 0;">
                                <input type="checkbox" name="header_page_break_levels[]" value="6">
                                <span>H6</span>
                            </label>
                        </div>
                    </div>
                    <label class="custom-checkbox" style="margin-top: 8px;">
                        <input type="checkbox" name="new_page_on_file" value="1" checked>
                        <span>Всеки файл започва на нова страница</span>
                    </label>
                    <label class="custom-checkbox" style="margin-top: 8px;">
                        <input type="checkbox" name="file_name_as_section" value="1" checked>
                        <span>Покажи име на файл като секция</span>
                    </label>
                </section>
            </form>
        </aside>

        <section class="preview-area">
            <div class="empty-state">
                Избери HTML файл/ове, за да видиш странирания резултат.
            </div>
        </section>
    </main>

    <div id="saveProjectModal" class="modal-overlay">
        <div class="modal-window">
            <h2>Запази проект</h2>
            
            <div id="saveModalMsg" class="modal-msg" style="display:none;"></div>

            <div class="input-group">
                <!-- <label for="projectNameInput">Име</label> -->
                <input type="text" id="projectNameInput" placeholder="Въведи име на проекта">
            </div>

            <div class="modal-actions">
                <button id="saveModalCancel" type="button" class="btn-reset">Отказ</button>
                <button id="saveModalConfirm" type="button" class="btn-save">Запази</button>
            </div>
        </div>
    </div>

    <script src="assets/PreviewPane.js"></script>
    <script src="assets/FileUploader.js"></script>
    <script src="assets/FillForm.js"></script>
    <script src="assets/SaveProject.js"></script>
    <script src="assets/PageToggle.js"></script>
</body>
</html>