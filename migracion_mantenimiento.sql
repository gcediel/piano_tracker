-- Migración: tempo objetivo y categoría de mantenimiento para piezas de repertorio,
-- y distinción de fallos con/sin metrónomo.
-- Piano Tracker

ALTER TABLE piezas
    ADD COLUMN tempo_objetivo INT NULL AFTER tempo,
    ADD COLUMN estado ENUM('aprendizaje', 'mantenimiento') NOT NULL DEFAULT 'aprendizaje',
    ADD COLUMN mes_evaluado DATE NULL,
    ADD COLUMN meses_objetivo_consecutivos INT NOT NULL DEFAULT 0,
    ADD COLUMN sugerencia_tempo_pendiente INT NULL,
    ADD COLUMN sugerencia_graduacion_pendiente BOOLEAN NOT NULL DEFAULT FALSE,
    ADD INDEX idx_estado (estado);

ALTER TABLE fallos
    ADD COLUMN tipo_pasada ENUM('libre', 'metronomo') NOT NULL DEFAULT 'metronomo' AFTER cantidad;
