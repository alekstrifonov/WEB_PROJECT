document.addEventListener('DOMContentLoaded', () => {
  const pageSize = document.getElementById('pageSize');
  const w = document.getElementById('pageWidth');
  const h = document.getElementById('pageHeight');

  if (!pageSize || !w || !h) return;

  const SIZES_MM = {
    a3: { w: 297, h: 420 },
    a4: { w: 210, h: 297 },
    a5: { w: 148, h: 210 },
  };

  function applySizeLock() {
    const v = String(pageSize.value || '').toLowerCase();

    if (v === 'custom') {
      w.readOnly = false;
      h.readOnly = false;

      w.classList.remove("is-locked");
      h.classList.remove("is-locked");
      return;
    }

    // Заключваме за всички предварително дефинирани размери
    // (ако искаш САМО за A3/A4/A5, смени условието на: if (v === 'a3' || v === 'a4' || v === 'a5')
    const preset = SIZES_MM[v];
    if (preset) {
      w.value = preset.w;
      h.value = preset.h;
    }

    w.readOnly = true;
    h.readOnly = true;

    w.classList.add("is-locked");
    h.classList.add("is-locked");
  }

  pageSize.addEventListener('change', applySizeLock);
  applySizeLock(); // при първо зареждане
});
