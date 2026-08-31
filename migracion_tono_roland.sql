-- Migración: tono Roland GO:KEYS por pieza de repertorio
-- Piano Tracker
--
-- Añade el almacenamiento del tono específico del Roland GO:KEYS (Bank Select
-- MSB/LSB + Program Change) para cada pieza, además del instrumento General
-- MIDI (programa_midi) que ya existía. Si una pieza no tiene tono Roland
-- asignado, la app sigue usando programa_midi como en la versión web.

ALTER TABLE piezas
    ADD COLUMN tono_nombre VARCHAR(60)      NULL AFTER programa_midi,
    ADD COLUMN tono_msb    TINYINT UNSIGNED NULL AFTER tono_nombre,
    ADD COLUMN tono_lsb    TINYINT UNSIGNED NULL AFTER tono_msb,
    ADD COLUMN tono_pc     SMALLINT UNSIGNED NULL AFTER tono_lsb;
