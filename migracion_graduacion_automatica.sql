-- Migración: la graduación de una pieza a repertorio de mantenimiento pasa a
-- aplicarse automáticamente al alcanzar el umbral (antes quedaba como una
-- sugerencia pendiente de confirmar en el dashboard, igual que la subida de
-- tempo). Se renombra el flag porque ya no representa una sugerencia por
-- decidir, sino el aviso de un cambio de estado que ya se ha aplicado.
-- Piano Tracker

-- Aplica ya las graduaciones que estuvieran pendientes de confirmar antes de
-- este cambio, para no dejarlas a medias con un aviso que no se corresponda
-- con el estado real de la pieza.
UPDATE piezas SET estado = 'mantenimiento', meses_objetivo_consecutivos = 0
WHERE sugerencia_graduacion_pendiente = 1 AND estado = 'aprendizaje';

ALTER TABLE piezas
    CHANGE COLUMN sugerencia_graduacion_pendiente aviso_graduacion_pendiente BOOLEAN NOT NULL DEFAULT FALSE;
