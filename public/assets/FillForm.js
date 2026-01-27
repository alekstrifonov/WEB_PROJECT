function setValue(selector, value) {
  const el = document.querySelector(selector);
  if (!el) return;
  el.value = value ?? "";
}

function setChecked(selector, checked) {
  const el = document.querySelector(selector);
  if (!el) return;
  el.checked = !!checked;
}

function lineSpacingToFormValue(n) {
  if (n === 1) return "tight";
  if (n === 3) return "wide";
  return "normal";
}

function fillHeaderBreakLevels(payload) {
  const levels = payload?.settings?.sections?.header_page_break_levels;

  const boxes = document.querySelectorAll('input[name="header_page_break_levels[]"]');
  if (!boxes) return;

  const set = new Set(levels.map(v => String(parseInt(v, 10))).filter(v => v >= "1" && v <= "6"));

  boxes.forEach(cb => {
    cb.checked = set.has(cb.value);
  });
}

function fillFormFromPayload(payload) {
  const s = payload.settings || {};
  const page = s.page || {};
  const pag = s.pagination || {};
  const sec = s.sections || {};
  const meta = payload.meta || {};

  setValue('[name="page_size"]', page.page_size);
  const pageSize = document.getElementById("pageSize");
  if (pageSize) {
    pageSize.dispatchEvent(new Event("change", { bubbles: true }));
  }

  setValue('[name="orientation"]', page.orientation);
  setValue('[name="page_width"]', page.page_width);
  setValue('[name="page_height"]', page.page_height);
  setValue('[name="margin"]', page.margin);
  setValue('[name="font_size"]', page.font_size);
  setValue('[name="line_spacing"]', lineSpacingToFormValue(page.line_spacing));
  setValue('[name="words"]', page.words);
  setValue('[name="lines"]', page.lines);

  setChecked('[name="show_page_numbers"]', pag.show_page_numbers === 1);
  setChecked('[name="no_number_on_first"]', pag.no_number_on_first === 1);
  setValue('[name="page_number_pos"]', pag.page_number_pos);
  setValue('[name="page_number_format"]', pag.page_number_format);
  setValue('[name="page_number_template"]', pag.page_number_template);
  setValue('[name="line_numbers_mode"]', pag.line_numbers_mode);
  setValue('[name="line_numbers_placement"]', pag.line_numbers_placement);

  setChecked('[name="new_page_on_header"]', sec.new_page_on_header === 1);
  setChecked('[name="new_page_on_file"]', sec.new_page_on_file === 1);
  setChecked('[name="file_name_as_section"]', sec.file_name_as_section === 1);
  setValue('[name="wrap_lines"]', sec.wrap_lines);
  fillHeaderBreakLevels(payload);

  setValue('[name="title"]', meta.title);
  setValue('[name="author"]', meta.author);
  setValue('[name="course"]', meta.course);
  setValue('[name="citation_template"]', meta.citation_template);
  setValue('[name="metadata_placement"]', meta.metadata_placement);
  setValue('[name="statistics_placement"]', meta.statistics_placement);
}

function loadProjectFilesIntoUploader(payload) {
  uploader = window.fileUploader;

  files = payload.htmls;

  filesToAdd = []

  for(file of files) {
    filesToAdd.push(new File( [file.html], file.name, { type: 'text/' + file.type } ))
  }

  uploader.addFiles(filesToAdd);
}

document.addEventListener("DOMContentLoaded", async () => {
  const params = new URLSearchParams(window.location.search);
  const projectId = params.get("project_id");
  if (!projectId) return;

  try {
    const res = await fetch(`./api/get-project.php?project_id=${encodeURIComponent(projectId)}`);
    const json = await res.json();

    if (!json.ok) {
      console.error(json.error);
      return;
    }

    // попълваме формата
    fillFormFromPayload(json.project.payload);
    loadProjectFilesIntoUploader(json.project.payload);
  } catch (e) {
    console.error(e);
  }
});

