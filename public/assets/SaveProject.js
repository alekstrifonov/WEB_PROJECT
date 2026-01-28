document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("print-form");

  const modal = document.getElementById("saveProjectModal");
  const nameInput = document.getElementById("projectNameInput");
  const confirmBtn = document.getElementById("saveModalConfirm");
  const cancelBtn = document.getElementById("saveModalCancel");
  const msgBox = document.getElementById("saveModalMsg");

  const headerSaveBtn = document.querySelector(".btn-save");

  if (!form || !modal || !nameInput || !confirmBtn) return;

  function showMsg(type, text) {
    if (!msgBox) return;
    msgBox.className = `modal-msg ${type}`;
    msgBox.textContent = text;
    msgBox.style.display = "block";
  }

  function clearMsg() {
    if (!msgBox) return;
    msgBox.style.display = "none";
    msgBox.textContent = "";
    msgBox.className = "modal-msg";
  }

  // Add event listener for the checkbox
  const saveAsSeparateCheckbox = document.getElementById("saveAsSeparateProject");
  if (saveAsSeparateCheckbox) {
    saveAsSeparateCheckbox.addEventListener("change", (e) => {
      if (e.target.checked) {
        nameInput.style.display = "block";
        nameInput.placeholder = "Въведи име на новия проект";
        nameInput.value = "";
        nameInput.focus();
      } else {
        nameInput.style.display = "none";
        // Restore original project name if unchecked
        const params = new URLSearchParams(window.location.search);
        const existingName = params.get("project_name");
        if (existingName) {
          nameInput.value = existingName;
        }
      }
    });
  }

  if (headerSaveBtn) {
    headerSaveBtn.addEventListener("click", (e) => {
      e.preventDefault();
      clearMsg();

      const params = new URLSearchParams(window.location.search);
      const existingName = params.get("project_name");

      if (existingName) {
        nameInput.value = existingName;

        const separateProjectCheckbox = document.getElementById("separate_save");
        if (separateProjectCheckbox) {
          separateProjectCheckbox.style.display = "block";
          nameInput.style.display = "none";
        }

        // Reset checkbox state
        if (saveAsSeparateCheckbox) {
          saveAsSeparateCheckbox.checked = false;
        }
      }

      modal.classList.add("active");
      nameInput.focus();
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener("click", (e) => {
      e.preventDefault();
      modal.classList.remove("active");
    });
  }

  modal.addEventListener("click", (e) => {
    if (e.target === modal) modal.classList.remove("active");
  });

  confirmBtn.addEventListener("click", async (e) => {
    e.preventDefault();
    clearMsg();

    const params = new URLSearchParams(window.location.search);
    const existingId = params.get("project_id");
    const separateProjectCheckbox = document.getElementById("separate_save");

    let projectName;

    // Check if user wants to save as separate project
    if (existingId && saveAsSeparateCheckbox && saveAsSeparateCheckbox.checked) {
      projectName = nameInput.value.trim();
      if (!projectName) {
        showMsg("error", "Не сте въвели име за новия проект.");
        nameInput.focus();
        return;
      }
    } else {
      projectName = nameInput.value.trim();
      if (!projectName) {
        showMsg("error", "Не сте въвели име на проекта.");
        nameInput.focus();
        return;
      }
    }

    const fd = new FormData(form);
    fd.set("generate_preview", "1");
    fd.set("project_name", projectName);

    // Only set project_id if not saving as separate project
    if (existingId && (!saveAsSeparateCheckbox || !saveAsSeparateCheckbox.checked)) {
      fd.set("project_id", existingId);
    }

    try {
      const res = await fetch("./api/save-project.php", {
        method: "POST",
        body: fd
      });

      const text = await res.text();

      let json;
      try {
        json = JSON.parse(text);
      } catch {
        console.error("Non-JSON response:", text);
        showMsg("error", "Сървърът върна невалиден отговор. Виж конзолата.");
        return;
      }

      if (!json.ok) {
        showMsg("error", json.error || "Грешка при запазване.");
        return;
      }

      showMsg("success", "Проектът е запазен успешно.");

      window.location.href = "./dashboard.php";

    } catch (err) {
      console.error(err);
      showMsg("error", "Мрежова грешка при запазване.");
    }
  });
});
