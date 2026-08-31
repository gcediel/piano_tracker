// Presets de sonido para el Roland GO:KEYS: 10 casillas guardadas en localStorage
// para recuperar tonos favoritos con un clic, sin tener que rebuscarlos en la lista
// cada vez. Requiere el modal de confirmación global `confirmar` (partials/header.ejs).

function initRolandPresets(gridId, storageKey, getCurrentTone, onLoad) {
  const grid = document.getElementById(gridId);
  if (!grid) return;
  const SLOTS = 10;

  function load() {
    let raw = [];
    try { raw = JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch (e) { raw = []; }
    const arr = new Array(SLOTS).fill(null);
    for (let i = 0; i < SLOTS && i < raw.length; i++) arr[i] = raw[i] || null;
    return arr;
  }
  function save(presets) { localStorage.setItem(storageKey, JSON.stringify(presets)); }

  let presets = load();

  function render() {
    grid.innerHTML = '';
    presets.forEach((p, i) => {
      const slot = document.createElement('div');
      slot.className = 'roland-preset-slot' + (p ? ' filled' : '');

      const loadBtn = document.createElement('button');
      loadBtn.type = 'button';
      loadBtn.className = 'roland-preset-load';
      loadBtn.disabled = !p;
      loadBtn.title = p ? ('Cargar "' + p.name + '"') : 'Casilla vacía — guarda un tono con 💾';
      loadBtn.innerHTML = '<span class="roland-preset-num">' + (i + 1) + '</span>' +
        '<span class="roland-preset-name">' + (p ? p.name : 'Vacío') + '</span>';
      loadBtn.addEventListener('click', () => { if (p && onLoad) onLoad(p); });
      slot.appendChild(loadBtn);

      const actions = document.createElement('div');
      actions.className = 'roland-preset-actions';

      const saveBtn = document.createElement('button');
      saveBtn.type = 'button';
      saveBtn.className = 'btn btn-small';
      saveBtn.title = 'Guardar el tono seleccionado arriba en esta casilla';
      saveBtn.textContent = '💾';
      saveBtn.addEventListener('click', () => {
        const t = getCurrentTone && getCurrentTone();
        if (!t || t.msb == null) return; // no hay tono seleccionado arriba: nada que guardar
        const doSave = () => {
          presets[i] = { name: t.name, msb: t.msb, lsb: t.lsb, pc: t.pc };
          save(presets);
          render();
        };
        if (p) confirmar('¿Sustituir el preset ' + (i + 1) + ' ("' + p.name + '") por "' + t.name + '"?', doSave);
        else doSave();
      });
      actions.appendChild(saveBtn);

      if (p) {
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-small btn-warning';
        clearBtn.title = 'Vaciar esta casilla';
        clearBtn.textContent = '✕';
        clearBtn.addEventListener('click', () => {
          confirmar('¿Vaciar el preset ' + (i + 1) + ' ("' + p.name + '")?', () => {
            presets[i] = null;
            save(presets);
            render();
          });
        });
        actions.appendChild(clearBtn);
      }

      slot.appendChild(actions);
      grid.appendChild(slot);
    });
  }

  render();
}
