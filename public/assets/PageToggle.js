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
      w.disabled = false;
      h.disabled = false;
      w.removeAttribute('aria-disabled');
      h.removeAttribute('aria-disabled');
      return;
    }

    // Заключваме за всички предварително дефинирани размери
    // (ако искаш САМО за A3/A4/A5, смени условието на: if (v === 'a3' || v === 'a4' || v === 'a5')
    const preset = SIZES_MM[v];
    if (preset) {
      w.value = preset.w;
      h.value = preset.h;
    }

    w.disabled = true;
    h.disabled = true;
    w.setAttribute('aria-disabled', 'true');
    h.setAttribute('aria-disabled', 'true');
  }

  pageSize.addEventListener('change', applySizeLock);
  applySizeLock(); // при първо зареждане
});
