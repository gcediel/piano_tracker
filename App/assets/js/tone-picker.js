// Selector de tono Roland, reutilizable.
// Requiere que assets/js/roland_tones.js se haya cargado antes (define window.ROLAND_SECTIONS).
//
// UI: dos selectores en cascada (Grupo → Tono: elegir un grupo repuebla el
// selector de tono con sus componentes) más un campo de búsqueda global
// independiente que filtra sobre los ~1200 tonos y permite elegir directamente.
// Elegir un tono (por el selector o por la búsqueda) sincroniza ambos
// selectores y dispara onChange; elegir solo un grupo no cambia el tono actual.

function initTonePicker(containerId, initial, onChange) {
  const root = document.getElementById(containerId);
  if (!root) return;

  const groupSelect  = root.querySelector('.tone-picker-group');
  const toneSelect   = root.querySelector('.tone-picker-tone');
  const searchInput  = root.querySelector('.tone-picker-search');
  const resultsEl    = root.querySelector('.tone-picker-search-results');
  const currentText  = root.querySelector('.tone-picker-current-text');

  const sections = window.ROLAND_SECTIONS || [];

  // Grupos = sección + categoría (p.ej. "Piano · Ac.Piano"), con sus tonos.
  const groups = [];
  const flat = []; // lista plana para la búsqueda global
  sections.forEach(section => {
    section.categories.forEach(cat => {
      const tones = cat.tones.map(t => ({ name: t[0], msb: t[1], lsb: t[2], pc: t[3] }));
      groups.push({ id: groups.length, sectionLabel: section.label, categoryLabel: cat.label, tones });
      tones.forEach(t => flat.push({ sectionLabel: section.label, categoryLabel: cat.label, name: t.name, msb: t.msb, lsb: t.lsb, pc: t.pc }));
    });
  });

  // Poblar el selector de grupo, agrupado visualmente por sección (optgroup).
  let groupOptionsHtml = '';
  let lastSection = null;
  groups.forEach(g => {
    if (g.sectionLabel !== lastSection) {
      if (lastSection !== null) groupOptionsHtml += '</optgroup>';
      groupOptionsHtml += '<optgroup label="' + g.sectionLabel + '">';
      lastSection = g.sectionLabel;
    }
    groupOptionsHtml += '<option value="' + g.id + '">' + g.categoryLabel + ' (' + g.tones.length + ')</option>';
  });
  if (lastSection !== null) groupOptionsHtml += '</optgroup>';
  groupSelect.innerHTML = '<option value="">— Elige un grupo (' + flat.length + ' tonos en total) —</option>' + groupOptionsHtml;

  let selected = initial && initial.msb != null ? { msb: initial.msb, lsb: initial.lsb, pc: initial.pc, name: initial.nombre } : null;

  function toneLabel(t) { return t.name + ' (' + t.msb + '/' + t.lsb + '/' + t.pc + ')'; }

  function updateCurrentText() {
    if (selected && selected.msb != null) {
      currentText.textContent = 'Tono actual: ' + selected.name + ' (' + selected.msb + '/' + selected.lsb + '/' + selected.pc + ')';
      currentText.classList.remove('tone-picker-placeholder');
    } else {
      currentText.textContent = 'Sin tono asignado — usará el instrumento GM';
      currentText.classList.add('tone-picker-placeholder');
    }
  }

  function populateToneSelect(groupId, selectToneIndex) {
    const g = groups[groupId];
    toneSelect.innerHTML = '<option value="">— Elige un tono —</option>';
    toneSelect.disabled = !g;
    if (!g) return;
    g.tones.forEach((t, i) => {
      const opt = document.createElement('option');
      opt.value = i;
      opt.textContent = toneLabel(t);
      if (selectToneIndex === i) opt.selected = true;
      toneSelect.appendChild(opt);
    });
  }

  // Sincroniza los selectores de grupo/tono para reflejar `selected`
  // (se usa tras elegir por búsqueda global o cargar un preset externo).
  function syncSelectsToSelected() {
    if (!selected || selected.msb == null) {
      groupSelect.value = '';
      populateToneSelect(-1, -1);
      return;
    }
    const gi = groups.findIndex(g => g.tones.some(t => t.msb === selected.msb && t.lsb === selected.lsb && t.pc === selected.pc));
    if (gi === -1) {
      groupSelect.value = '';
      populateToneSelect(-1, -1);
      return;
    }
    const ti = groups[gi].tones.findIndex(t => t.msb === selected.msb && t.lsb === selected.lsb && t.pc === selected.pc);
    groupSelect.value = gi;
    populateToneSelect(gi, ti);
  }

  function applySelection(t, fireChange) {
    selected = { msb: t.msb, lsb: t.lsb, pc: t.pc, name: t.name };
    updateCurrentText();
    syncSelectsToSelected();
    closeResults();
    searchInput.value = '';
    if (fireChange !== false && onChange) onChange(selected);
  }

  groupSelect.addEventListener('change', () => {
    const gi = groupSelect.value === '' ? -1 : parseInt(groupSelect.value, 10);
    populateToneSelect(gi, -1); // elegir solo el grupo no cambia el tono actual
  });

  toneSelect.addEventListener('change', () => {
    if (toneSelect.value === '') return;
    const g = groups[parseInt(groupSelect.value, 10)];
    const t = g && g.tones[parseInt(toneSelect.value, 10)];
    if (t) applySelection(t, true);
  });

  function openResults() { resultsEl.classList.add('open'); }
  function closeResults() { resultsEl.classList.remove('open'); }

  function renderResults() {
    const q = (searchInput.value || '').trim().toLowerCase();
    resultsEl.innerHTML = '';
    if (!q) { closeResults(); return; }

    const matches = flat.filter(t =>
      t.name.toLowerCase().includes(q) || t.categoryLabel.toLowerCase().includes(q) || t.sectionLabel.toLowerCase().includes(q)
    ).slice(0, 60);

    if (matches.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'tone-picker-empty';
      empty.textContent = 'Sin resultados para "' + searchInput.value + '"';
      resultsEl.appendChild(empty);
    } else {
      let lastCat = null;
      matches.forEach(t => {
        const groupKey = t.sectionLabel + ' · ' + t.categoryLabel;
        if (groupKey !== lastCat) {
          const g = document.createElement('div');
          g.className = 'tone-picker-group-label';
          g.textContent = groupKey;
          resultsEl.appendChild(g);
          lastCat = groupKey;
        }
        const item = document.createElement('div');
        const isSel = selected && selected.msb === t.msb && selected.lsb === t.lsb && selected.pc === t.pc;
        item.className = 'tone-picker-item' + (isSel ? ' selected' : '');
        item.innerHTML = '<span>' + t.name + '</span><span class="tone-addr">' + t.msb + '/' + t.lsb + '/' + t.pc + '</span>';
        item.addEventListener('mousedown', (e) => {
          e.preventDefault(); // dispara antes del blur del input de búsqueda
          applySelection(t, true);
        });
        resultsEl.appendChild(item);
      });
      if (matches.length === 60) {
        const more = document.createElement('div');
        more.className = 'tone-picker-empty';
        more.textContent = 'Hay más resultados — afina la búsqueda para verlos todos';
        resultsEl.appendChild(more);
      }
    }
    openResults();
  }

  searchInput.addEventListener('input', renderResults);
  searchInput.addEventListener('focus', () => { if (searchInput.value.trim()) renderResults(); });
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeResults(); searchInput.blur(); }
  });

  document.addEventListener('click', (e) => {
    if (!root.contains(e.target)) closeResults();
  });

  populateToneSelect(-1, -1);
  syncSelectsToSelected();
  updateCurrentText();

  return {
    getSelected: () => selected,
    setSelected: (t) => { selected = t; updateCurrentText(); syncSelectsToSelected(); },
    selectTone: (t) => applySelection(t, true),
  };
}
