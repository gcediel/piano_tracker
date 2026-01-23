-- Script de reseteo de contraseña a piano2026
-- Ejecutar si no puedes acceder al sistema

USE piano_tracker;

-- Borrar la contraseña existente y crear una nueva
DELETE FROM configuracion WHERE clave = 'password_hash';

-- El hash correcto lo generaremos desde setup.php
-- Por ahora solo preparamos la estructura
SELECT 'Ejecuta setup.php desde el navegador para establecer la contraseña' as mensaje;
