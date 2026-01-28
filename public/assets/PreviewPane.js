function renderPreviewHtml(htmlDoc) {
    const previewArea = document.querySelector(".preview-area");
    if (!previewArea) return;

    previewArea.style.overflowY = "auto";
    previewArea.style.alignItems = "stretch";
    previewArea.style.justifyContent = "stretch";
    previewArea.style.padding = "0";

    previewArea.innerHTML = "";

    const iframe = document.createElement("iframe");
    iframe.style.width = "100%";
    iframe.style.height = "100%";
    iframe.style.border = "0";
    iframe.style.display = "block";

    iframe.srcdoc = htmlDoc;

    previewArea.appendChild(iframe);
}

function renderPreviewError(message) {
    const previewArea = document.querySelector(".preview-area");
    if (!previewArea) return;

    previewArea.style.overflowY = "auto";
    previewArea.style.alignItems = "center";
    previewArea.style.justifyContent = "center";
    previewArea.style.padding = "40px 20px";

    previewArea.innerHTML = `
        <div style="
            color: var(--orange);
            font-size: 0.95rem;
            opacity: 0.95;
            background: rgba(0,0,0,0.25);
            border: 1px solid rgba(255,134,0,0.35);
            padding: 14px 16px;
            border-radius: 12px;
            max-width: 520px;
        ">
            ${message}
        </div>
    `;
}

async function generatePreview() {
    const form = document.getElementById("print-form");
    if (!form) {
        renderPreviewError("Не намирам form#print-form.");
        return;
    }

    const fd = new FormData(form);
    fd.set("generate_preview", "1");

    try {
        const res = await fetch("./api/process-html.php", {
            method: "POST",
            body: fd,
        });

        const json = await res.json();

        if (!json.ok) {
            renderPreviewError(json.error || "Грешка при генериране на преглед.");
            return;
        }

        renderPreviewHtml(json.html);
    } catch (e) {
        renderPreviewError("Грешка: " + (e?.message || e));
        console.error(e);
    }
}

function printPreview() {
    const iframe = document.querySelector(".preview-area iframe");
    if (!iframe || !iframe.contentWindow) {
        alert("Няма генериран преглед за принтиране.");
        return;
    }

    try {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    } catch (_) { }
}

// Auto-refresh functionality
function setupAutoRefresh() {
    const autoGenToggle = document.getElementById("auto-gen-toggle");
    const printForm = document.getElementById("print-form");

    if (!autoGenToggle || !printForm) return;

    let debounceTimer;

    function handleFormChange() {
        if (!autoGenToggle.checked) return;

        // Debounce to avoid too many requests
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            generatePreview();
        }, 500);
    }

    // Listen to all input, select, and checkbox changes in the form
    printForm.addEventListener("change", handleFormChange);
    printForm.addEventListener("input", handleFormChange);
}

// Initialize auto-refresh when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupAutoRefresh);
} else {
    setupAutoRefresh();
}

// Wire up the Generate and Print buttons
function setupButtons() {
    const generateBtn = document.getElementById("generate-btn");
    const printBtn = document.getElementById("print-btn");

    if (generateBtn) {
        generateBtn.addEventListener("click", () => {
            generatePreview();
        });
    }

    if (printBtn) {
        printBtn.addEventListener("click", () => {
            printPreview();
        });
    }
}

// Initialize buttons when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupButtons);
} else {
    setupButtons();
}

