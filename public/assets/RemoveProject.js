document.addEventListener("DOMContentLoaded", () => {

  document.querySelectorAll(".btn.danger").forEach(btn => {

    btn.addEventListener("click", async () => {

      const projectId = btn.dataset.projectId;

      if (!projectId) return;

      const fd = new FormData();
      fd.set("project_id", projectId);

      try {
        const res = await fetch("./api/remove-project.php", {
          method: "POST",
          body: fd
        });

        const text = await res.text();

        let json;
        try {
          json = JSON.parse(text);
        } catch {
          console.error("Non-JSON response:", text);
          alert("Сървърна грешка.");
          return;
        }

        if (!json.ok) {
          alert(json.error || "Грешка при изтриване.");
          return;
        }

        const projectItem = btn.closest(".project");
        if (projectItem) {
            projectItem.remove();

            const list = document.querySelector(".project-list");
            const emptyState = document.querySelector(".empty");

            if (list && emptyState) {
                const remainingProjects = list.querySelectorAll(".project");

                if (remainingProjects.length === 0) {
                    list.classList.add("is-hidden");
                    emptyState.classList.remove("is-hidden");
                }
            }
        }

      } catch (err) {
        console.error(err);
        alert("Мрежова грешка.");
      }

    });

  });

});