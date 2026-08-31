-- Inicialización de tempo_objetivo tras migracion_mantenimiento.sql
-- Piano Tracker
--
-- Por defecto, el tempo objetivo de cada pieza se fija igual a su tempo
-- actual (piezas sin tempo objetivo asignado no generan sugerencias de
-- progresión, así que no pasa nada si de momento quedan así). Las piezas
-- que necesiten un objetivo distinto se ajustan luego a mano, desde
-- repertorio.php o directamente en la base de datos.
--
-- Solo toca las piezas que aún no tienen tempo_objetivo (no sobrescribe
-- nada si se ejecuta más de una vez).

UPDATE piezas
SET tempo_objetivo = tempo
WHERE tempo_objetivo IS NULL
  AND tempo IS NOT NULL;

-- Verificación: revisa el resultado antes de dar la migración por buena.
SELECT id, compositor, titulo, tempo, tempo_objetivo, estado
FROM piezas
ORDER BY compositor, titulo;
