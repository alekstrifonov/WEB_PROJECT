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
  } catch (_) {}
}

document.addEventListener("DOMContentLoaded", () => {
    const generate_btn = document.getElementById("generate-btn");
    const print_btn = document.getElementById("print-btn");

    const autoToggle = document.getElementById("auto-gen-toggle");

    const formInputs = document.querySelectorAll("#print-form input, #print-form select, #print-form textarea");

    function smartGenerate() {
        if (!autoToggle || !autoToggle.checked) return;

        const scrollX = window.scrollX;
        const scrollY = window.scrollY;

        generatePreview(); 

        setTimeout(() => {
            window.scrollTo(scrollX, scrollY);
        }, 0);
    }

    formInputs.forEach(input => {
        if (input === autoToggle || input.type === 'file') return;

        if (input.type === 'checkbox' || input.type === 'radio' || input.tagName === 'SELECT') {
            input.addEventListener("change", smartGenerate);
        } 
        else {
            input.addEventListener("input", smartGenerate);
        }
    });

    // function smartGenerate() {
    //     const toggle = document.getElementById("auto-gen-toggle");
    //     if (toggle && !toggle.checked) return;

    //     const scrollX = window.scrollX;
    //     const scrollY = window.scrollY;

    //     generatePreview(); 

    //     setTimeout(() => {
    //         window.scrollTo(scrollX, scrollY);
    //     }, 0);
    // }

    // const inputs = document.querySelectorAll("input, select, textarea");

    // inputs.forEach(input => {
    //     if (input.type === "checkbox" || input.type === "radio") {
    //         input.addEventListener("change", smartGenerate);
    //     } 
    //     else {
    //         input.addEventListener("input", smartGenerate);
    //     }
    // });

    if (generate_btn) generate_btn.addEventListener("click", generatePreview);
    if (print_btn) print_btn.addEventListener("click", printPreview);
});

// document.addEventListener("DOMContentLoaded", () => {
//     const generate_btn = document.getElementById("generate-btn");
//     const print_btn = document.getElementById("print-btn");
//     const auto_toggle = document.getElementById("auto-gen-toggle");

//     function handleAutoUpdate() {
//         if (!auto_toggle || !auto_toggle.checked) return;
//         generatePreview();
//     }
    
//     const inputs = document.querySelectorAll("input, select, textarea");

//     inputs.forEach(input => {
//         if (input === auto_toggle) return;
//         input.addEventListener("change", handleAutoUpdate);
//     });
    
//     if (generate_btn) generate_btn.addEventListener("click", generatePreview);
//     if (print_btn) print_btn.addEventListener("click", printPreview);
// });
