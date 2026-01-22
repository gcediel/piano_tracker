// Piano Tracker - JavaScript principal

// Funciones auxiliares globales
function confirmarAccion(mensaje) {
    return confirm(mensaje || '¿Estás seguro de realizar esta acción?');
}

// Formatear tiempo desde segundos
function formatearTiempo(segundos) {
    const horas = Math.floor(segundos / 3600);
    const minutos = Math.floor((segundos % 3600) / 60);
    const segs = segundos % 60;
    
    return String(horas).padStart(2, '0') + ':' + 
           String(minutos).padStart(2, '0') + ':' + 
           String(segs).padStart(2, '0');
}

// Validación de formularios
document.addEventListener('DOMContentLoaded', function() {
    // Validar números positivos
    const numerosPositivos = document.querySelectorAll('input[type="number"]');
    numerosPositivos.forEach(input => {
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });
    });
    
    // Confirmación en eliminaciones
    const botonesEliminar = document.querySelectorAll('.btn-danger');
    botonesEliminar.forEach(boton => {
        if (boton.textContent.includes('Eliminar') || boton.textContent.includes('Desactivar')) {
            boton.addEventListener('click', function(e) {
                if (!confirmarAccion('¿Estás seguro de realizar esta acción?')) {
                    e.preventDefault();
                }
            });
        }
    });
});

// Guardar estado del timer en localStorage para prevenir pérdidas
if (typeof(Storage) !== "undefined") {
    window.addEventListener('beforeunload', function() {
        if (typeof timerActivo !== 'undefined' && timerActivo) {
            localStorage.setItem('timer_backup', JSON.stringify({
                tiempo: tiempoActual,
                actividadId: document.getElementById('actividadId')?.value,
                fecha: new Date().toISOString()
            }));
        }
    });
}
