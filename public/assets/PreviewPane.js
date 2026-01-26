/**
 * Hardcoded JSON data
 */
const mockJsonData = {
    "title": "Техническа Документация",
    "author": "Георги Петров",
    "htmlContent": `
        <div class="document-wrapper">
            <h1 style="text-align: center; color: #27187E;">ПРОТОКОЛ ЗА ПРИЕМАНЕ</h1>
            <p style="text-align: right;"><strong>Дата:</strong> 04.01.2026г.</p>
            <hr>
            <p>Този документ е генериран автоматично за целите на преглед преди печат. 
            Системата преобразува суров HTML в JSON формат и го визуализира върху симулиран А4 лист.</p>
            
            <h3>Основни параметри:</h3>
            <ul>
                <li><strong>Шрифт:</strong> Times New Roman (12pt)</li>
                <li><strong>Междуредие:</strong> 1.5 (Машинописно)</li>
                <li><strong>Маржове:</strong> 18мм</li>
            </ul>

            <p style="margin-top: 40px;">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor 
                incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis 
                nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
            </p>
            
            <div style="margin-top: 100px; display: flex; justify-content: space-between;">
                <span>Изготвил: ....................</span>
                <span>Проверил: ....................</span>
            </div>
        </div>
    `
};

/**
 * Renders the JSON content into the new UI
 */
function renderPreview(data) {
    const previewArea = document.querySelector('.preview-area');
    
    if (!previewArea) return;

    // 1. Setup the preview area for scrolling
    // We override the flex alignment to 'flex-start' so the paper starts at the top
    previewArea.style.overflowY = "auto";
    previewArea.style.alignItems = "flex-start";
    previewArea.style.padding = "40px 0";

    // 2. Inject the paper sheet
    previewArea.innerHTML = `
        <div id="paper-sheet">
            ${data.htmlContent}
        </div>
    `;

    // 3. Apply the Paper Styles
    const paper = document.getElementById('paper-sheet');
    Object.assign(paper.style, {
        backgroundColor: "white",
        color: "black",
        width: "210mm",
        minHeight: "297mm",
        padding: "20mm",
        margin: "0 auto", // Center horizontally
        boxShadow: "0 10px 30px rgba(0,0,0,0.5)",
        boxSizing: "border-box",
        fontFamily: "'Times New Roman', serif",
        lineHeight: "1.5"
    });
}

async function displaySettings() {
    const form = document.getElementById("print-form"); // сложи реалното id
    console.log(form)
    const fd = new FormData(form);

    // НЕ е задължително, защото endpoint-ът го добавя,
    // но е ок да го има:
    fd.set("generate_preview", "1");

    const res = await fetch("./api/process-html.php", {
        method: "POST",
        body: fd,
    });

    const json = await res.json();

    if (!json.ok) {
        console.error(json.error);
        return;
    }

    console.log("Preview data:", json.data);
    return json.data;
}


// Run when the page loads
document.addEventListener('DOMContentLoaded', () => {
    renderPreview(mockJsonData);

    //======================================================

    const btn = document.getElementById("generate-btn");
    if (btn) btn.addEventListener("click", displaySettings);

    //=======================================================

    const headerSaveBtn = document.querySelector(".btn-save");
    
    const modal = document.getElementById("saveProjectModal");
    const cancelBtn = document.getElementById("saveModalCancel");

    if (headerSaveBtn) {
        headerSaveBtn.addEventListener("click", () => {
            modal.classList.add("active");
        });
    }

    if (cancelBtn) {
        cancelBtn.addEventListener("click", () => {
            modal.classList.remove("active");
        });
    }

    modal.addEventListener("click", (e) => {
        if (e.target === modal) {
            modal.classList.remove("active");
        }
    });
});

