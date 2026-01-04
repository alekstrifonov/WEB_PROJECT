/**
 * Hardcoded JSON data
 */
const uploadedFileData = {
    "fileName": "Screenshot_Data.json",
    "htmlContent": `
        <div style="font-family: 'Times New Roman', serif;">
            <h1 style="text-align: center;">ДОКУМЕНТ</h1>
            <p>Това съдържание идва от вашия <strong>JSON</strong> файл.</p>
            <p>Тъй като използваме XAMPP, този скрипт успешно инжектира HTML в 
            централната секция на вашето приложение.</p>
            <br>
            <p>Списък с параметри за печат:</p>
            <ul>
                <li>Размер: A4</li>
                <li>Ориентация: Портрет</li>
                <li>Шрифт: 12pt</li>
            </ul>
        </div>
    `
};

/**
 * Renders the content into the .preview-area section
 */
function renderPreviewPane(data) {
    // FIX: Target the class '.preview-area' instead of an ID
    const displayArea = document.querySelector('.preview-area');

    if (!displayArea) {
        console.error("Error: Element with class 'preview-area' not found.");
        return;
    }

    // Replace the 'empty-state' div with the paper container
    displayArea.innerHTML = `
        <div id="paper-sheet">
            ${data.htmlContent}
        </div>
    `;

    // Apply the "Paper" styling via JavaScript
    const paper = document.getElementById('paper-sheet');
    Object.assign(paper.style, {
        backgroundColor: "white",
        color: "black",
        width: "210mm",
        minHeight: "297mm",
        margin: "0 auto",
        padding: "20mm",
        boxShadow: "0 0 20px rgba(0,0,0,0.5)",
        boxSizing: "border-box",
        textAlign: "left"
    });
}

// Execute on load
document.addEventListener('DOMContentLoaded', () => {
    renderPreviewPane(uploadedFileData);
});
