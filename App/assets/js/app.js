// Piano Tracker - utilidades globales

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[type="number"]').forEach(function (input) {
    input.addEventListener('input', function () {
      if (this.min !== '' && Number(this.value) < Number(this.min)) this.value = this.min;
    });
  });
});

// Respaldo del timer de sesión en localStorage por si la app se cierra sin querer
if (typeof Storage !== 'undefined') {
  window.addEventListener('beforeunload', function () {
    if (typeof timerActivo !== 'undefined' && timerActivo) {
      localStorage.setItem('timer_backup', JSON.stringify({
        tiempo: typeof tiempoActual !== 'undefined' ? tiempoActual : null,
        actividadId: document.getElementById('actividadId')?.value,
        fecha: new Date().toISOString(),
      }));
    }
  });
}
