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

  // Отваряне на модала от header бутона
  if (headerSaveBtn) {
    headerSaveBtn.addEventListener("click", (e) => {
        e.preventDefault();
        clearMsg();

        const params = new URLSearchParams(window.location.search);
        const existingName = params.get("project_name");

        if (existingName) {
            nameInput.value = existingName;
        }


        modal.classList.add("active");
        nameInput.focus();
    });
  }

  // Cancel
  if (cancelBtn) {
    cancelBtn.addEventListener("click", (e) => {
      e.preventDefault();
      modal.classList.remove("active");
    });
  }

  // Click on backdrop closes
  modal.addEventListener("click", (e) => {
    if (e.target === modal) modal.classList.remove("active");
  });

  // ✅ Save (fetch)
  confirmBtn.addEventListener("click", async (e) => {
    e.preventDefault();
    clearMsg();

    const projectName = nameInput.value.trim();
    if (!projectName) {
      showMsg("error", "Не сте въвели име на проекта.");
      nameInput.focus();
      return;
    }

    const fd = new FormData(form);

    // aggregateInput() изисква този флаг
    fd.set("generate_preview", "1");

    // име от модала
    fd.set("project_name", projectName);

    // ако имаш project_id (за update)
    const params = new URLSearchParams(window.location.search);
    const existingId = params.get("project_id");

    // const existingId = form.querySelector('input[name="project_id"]');
    // if (existingId && existingId.value) {
    //   fd.set("project_id", existingId.value);
    // }
    if (existingId) {
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
