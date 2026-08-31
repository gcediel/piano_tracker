-- Migración: Sistema de ejercicios de técnica
-- Piano Tracker

-- Nueva tabla de ejercicios de técnica
CREATE TABLE IF NOT EXISTS ejercicios_tecnica (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bloque TINYINT NOT NULL,
    numero TINYINT NOT NULL,
    nombre VARCHAR(300) NOT NULL,
    bpm INT NOT NULL DEFAULT 120,
    comentarios TEXT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bloque_numero (bloque, numero),
    INDEX idx_activo (activo),
    INDEX idx_bpm (bpm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nueva tabla de registro de ejercicios por sesión
CREATE TABLE IF NOT EXISTS sesion_tecnica_ejercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    ejercicio_id INT NOT NULL,
    resultado ENUM('bien', 'neutro', 'mal') NOT NULL,
    bpm_practicado INT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (ejercicio_id) REFERENCES ejercicios_tecnica(id) ON DELETE CASCADE,
    INDEX idx_actividad (actividad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ampliar ENUM de tipos de actividad
ALTER TABLE actividades
    MODIFY COLUMN tipo ENUM(
        'calentamiento',
        'tecnica',
        'practica',
        'repertorio',
        'improvisacion',
        'composicion',
        'tecnica_ejercicios',
        'practica_tecnica'
    ) NOT NULL;
