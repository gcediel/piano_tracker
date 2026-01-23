-- Script de migración de v1.2 a v1.3
-- Ejecutar este archivo si ya tienes Piano Tracker instalado

USE piano_tracker;

-- Crear tabla de configuración si no existe
CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(100) PRIMARY KEY,
    valor TEXT NOT NULL,
    descripcion VARCHAR(500),
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NO insertar contraseña si ya existe una configurada
-- Si no hay contraseña, acceder a setup.php para establecerla

-- Añadir columna instrumento a la tabla piezas si no existe
ALTER TABLE piezas 
ADD COLUMN IF NOT EXISTS instrumento VARCHAR(50) DEFAULT 'Piano' AFTER ponderacion;

-- Actualizar piezas existentes sin instrumento
UPDATE piezas SET instrumento = 'Piano' WHERE instrumento IS NULL OR instrumento = '';

-- Verificar cambios
SELECT 'Migración completada correctamente' as resultado;
SELECT COUNT(*) as total_piezas, 
       COUNT(CASE WHEN instrumento IS NOT NULL THEN 1 END) as piezas_con_instrumento 
FROM piezas;
