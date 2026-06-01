# Piano Tracker - Documentación Técnica v1.6

**Aplicación web para gestión de práctica de piano**  
**Autor:** Guillermo  
**Fecha de creación:** Enero 2025  
**Última actualización:** Junio 2026  
**Versión:** 1.6  
**Stack:** PHP 8.x + MySQL 8.x / MariaDB + Vanilla JavaScript

---

## 📋 Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Requisitos del Sistema](#requisitos-del-sistema)
3. [Instalación](#instalación)
4. [Estructura de Archivos](#estructura-de-archivos)
5. [Base de Datos](#base-de-datos)
6. [Funcionalidades por Módulo](#funcionalidades-por-módulo)
7. [Algoritmos Clave](#algoritmos-clave)
8. [API de Funciones y AJAX](#api-de-funciones-y-ajax)
9. [Guía de Desarrollo](#guía-de-desarrollo)

---

## 📖 Descripción General

Piano Tracker es una aplicación web para pianistas que permite:
- Gestionar un repertorio de piezas musicales con tono MIDI asignado
- Registrar sesiones de práctica con cronómetro y metrónomo integrado
- Controlar el sonido del piano vía MIDI (Web MIDI API)
- Llevar seguimiento de errores/fallos por pieza
- Obtener sugerencias inteligentes de piezas a practicar
- Visualizar estadísticas y tendencias de práctica

### Características principales

- **Gestión de repertorio:** CRUD de piezas con metadatos (compositor, título, grado, tempo, ponderación, tono MIDI GM)
- **Metrónomo integrado:** BPM ajustable, pulsos por compás configurables, acento en primer pulso, control de volumen; preferencias persistidas en `localStorage`
- **Soporte MIDI:** Envío automático de Program Change (Web MIDI API) al iniciar y cambiar pieza en Repertorio
- **Sesiones de práctica:** Cronómetro con flujo automático entre actividades
- **Edición en sesión:** Tempo y tono MIDI de una pieza editables durante la práctica sin salir de la página
- **Configuración de metrónomo:** BPM por defecto para Técnica y Práctica configurables desde Admin
- **Informes visuales:** Estadísticas con DataTables, gráficos y análisis temporal

---

## 💻 Requisitos del Sistema

### Servidor
- **PHP:** 8.0 o superior
- **MySQL:** 8.0+ o **MariaDB:** 10.3+
- **Apache/Nginx** con mod_rewrite / `try_files`
- **HTTPS** obligatorio para el uso del metrónomo MIDI (Web MIDI API)

### Cliente
- Navegador **Chromium-based** para MIDI (Chrome, Brave, Vivaldi, Edge)
- JavaScript habilitado
- Para MIDI en tablet Android: cable **USB-C a USB-B** + piano con puerto USB to Host

---

## 🚀 Instalación

### 1. Desplegar archivos

```bash
cd /var/www/html
git clone [repositorio] piano_tracker
```

### 2. Configurar base de datos

```sql
CREATE DATABASE piano_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'piano_user'@'localhost' IDENTIFIED BY 'tu_contraseña';
GRANT ALL PRIVILEGES ON piano_tracker.* TO 'piano_user'@'localhost';
FLUSH PRIVILEGES;
```

```bash
mysql -u piano_user -p piano_tracker < database/schema.sql
mysql -u piano_user -p piano_tracker < migracion_midi.sql
```

### 3. Configurar conexión

Editar `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'piano_tracker');
define('DB_USER', 'piano_user');
define('DB_PASS', 'tu_contraseña');
```

### 4. Permisos

```bash
chown www-data:www-data -R /var/www/html/piano_tracker
chmod 755 -R /var/www/html/piano_tracker
```

---

## 📁 Estructura de Archivos

```
piano_tracker/
├── config/
│   └── database.php           # Conexión DB + funciones globales
├── includes/
│   ├── header.php             # Cabecera HTML + navegación
│   └── footer.php             # Pie de página
├── assets/
│   ├── css/
│   │   └── style.css          # Estilos globales (incluye metrónomo)
│   └── js/
│       └── app.js             # JS auxiliar
├── database/
│   └── schema.sql             # Esquema completo de BD
├── index.php                  # Dashboard
├── repertorio.php             # Gestión de piezas + tono MIDI GM
├── sesion.php                 # Sesiones, timer, metrónomo, MIDI
├── informes.php               # Estadísticas
├── admin.php                  # Administración + config metrónomo
├── gestionar_sesiones.php     # CRUD de sesiones manuales
├── migracion_v1.3.sql         # Migración v1.3
└── migracion_midi.sql         # Migración: columna programa_midi
```

---

## 🗄️ Base de Datos

### Tabla: `piezas`

```sql
CREATE TABLE piezas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    compositor    VARCHAR(200) NOT NULL,
    titulo        VARCHAR(300) NOT NULL,
    libro         VARCHAR(200),
    grado         INT,
    tempo         INT,
    ponderacion   DECIMAL(5,2) DEFAULT 1.00,
    instrumento   VARCHAR(50)  DEFAULT 'Piano',   -- campo legado, no se usa en UI
    programa_midi INT          NOT NULL DEFAULT 0, -- programa GM (0-127)
    activa        BOOLEAN      DEFAULT TRUE,
    fecha_creacion TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);
```

**Campos clave:**
- `tempo`: Velocidad de la pieza; se carga automáticamente en el metrónomo al iniciar Repertorio
- `programa_midi`: Número de programa General MIDI (0 = Acoustic Grand Piano). Se envía como MIDI Program Change al piano
- `ponderacion`: Factor de prioridad en el algoritmo de sugerencia

### Tabla: `configuracion`

Almacena pares clave-valor de configuración global.

```sql
CREATE TABLE configuracion (
    clave              VARCHAR(100) PRIMARY KEY,
    valor              TEXT NOT NULL,
    descripcion        VARCHAR(500),
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Claves utilizadas:**

| Clave               | Valor por defecto | Descripción                              |
|---------------------|:-----------------:|------------------------------------------|
| `metro_bpm_tecnica` | 144               | BPM por defecto del metrónomo — Técnica  |
| `metro_bpm_practica`| 92                | BPM por defecto del metrónomo — Práctica |

### Tabla: `sesiones`

```sql
CREATE TABLE sesiones (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    fecha          DATE NOT NULL,
    estado         ENUM('planificada','en_curso','finalizada') DEFAULT 'planificada',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabla: `actividades`

```sql
CREATE TABLE actividades (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id       INT NOT NULL,
    orden           INT NOT NULL,
    tipo            ENUM('calentamiento','tecnica','practica','repertorio',
                         'improvisacion','composicion') NOT NULL,
    pieza_id        INT NULL,
    tiempo_segundos INT DEFAULT 0,
    notas           TEXT,
    estado          ENUM('pendiente','en_curso','completada') DEFAULT 'pendiente',
    fecha_inicio    DATETIME NULL,
    fecha_fin       DATETIME NULL,
    FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE CASCADE,
    FOREIGN KEY (pieza_id)  REFERENCES piezas(id)   ON DELETE SET NULL
);
```

### Tabla: `fallos`

```sql
CREATE TABLE fallos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id    INT NOT NULL,
    pieza_id        INT NOT NULL,
    cantidad        INT NOT NULL DEFAULT 0,
    fecha_registro  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (pieza_id)     REFERENCES piezas(id)      ON DELETE CASCADE
);
```

### Relaciones

```
sesiones (1) ──→ (N) actividades
piezas   (1) ──→ (N) actividades  [solo tipo='repertorio']
piezas   (1) ──→ (N) fallos
actividades (1) ──→ (N) fallos
```

---

## ⚙️ Funcionalidades por Módulo

### 1. Inicio (`index.php`)

Dashboard con resumen de actividad:
- Tiempo practicado hoy y este mes
- Piezas activas, racha de días, porcentaje de práctica
- Últimas 5 sesiones con media de fallos
- Auto-corrección de sesiones inconsistentes (marca finalizadas las que tienen todas las actividades completadas)

---

### 2. Repertorio (`repertorio.php`)

CRUD completo de piezas:
- **Campos del formulario:** Compositor, Título, Libro, Grado, Tempo, Tono MIDI (GM, desplegable con los 128 instrumentos GM), Ponderación
- **Campo eliminado:** "Instrumento" (texto libre) — reemplazado por el desplegable Tono MIDI
- **Tabla de piezas** con DataTables: muestra número de programa GM en columna "Tono GM"
- **Estadísticas por pieza** (últimos 30 días): días practicados, media de fallos, código de color

**Códigos de color (media fallos/día):**

| Color       | Rango        | Estado    |
|-------------|:------------:|-----------|
| Azul oscuro | < 0.5        | Excelente |
| Azul medio  | 0.5 – 1.5    | Muy bien  |
| Azul claro  | 1.5 – 2.5    | Bien      |
| Verde       | 2.5 – 3.5    | Aceptable |
| Gris        | 3.5 – 5.0    | Mejorable |
| Rojo        | > 5.0        | Atención  |

---

### 3. Sesión (`sesion.php`)

Módulo central. Gestiona la planificación, ejecución y seguimiento de sesiones.

#### 3.1 Planificación
- Precarga automática de la configuración de la última sesión
- Dos modos: iniciar ahora o preparar para después

#### 3.2 Timer con flujo automático
- Cronómetro JavaScript + AJAX (guardado cada 5 s)
- Primera actividad: inicio manual; siguientes: automático
- Botones contextuales según tipo y posición de la actividad
- Auto-finalización al completar la última actividad

#### 3.3 Metrónomo integrado

Panel visible durante toda la sesión activa. Implementado con **Web Audio API** (scheduler de lookahead, sin librerías externas).

**Controles:**

| Control              | Comportamiento                                           |
|----------------------|----------------------------------------------------------|
| BPM (-5/-1/+1/+5)   | Ajusta velocidad; si está en marcha, reinicia al instante|
| Pulsos/compás (−/+) | Cambia número de pulsos; mínimo 1, máximo 12             |
| Acento 1er pulso    | Toggle ON/OFF; primer pulso: 880 Hz; resto: 440 Hz       |
| Volumen             | Slider 0–100 %; se aplica al siguiente tick              |
| Iniciar/Parar       | Arranca o detiene el metrónomo                           |

**BPM por defecto según actividad:**

| Tipo de actividad | BPM por defecto    |
|-------------------|--------------------|
| Técnica           | `metro_bpm_tecnica` (Admin) |
| Práctica          | `metro_bpm_practica` (Admin)|
| Repertorio        | Tempo de la pieza activa (o `metro_bpm_practica` si no tiene) |
| Otras             | `metro_bpm_practica`       |

**Persistencia en `localStorage`:**

| Clave             | Contenido                   |
|-------------------|-----------------------------|
| `metro_volumen`   | Volumen (0.0 – 1.0)         |
| `metro_acento`    | Acento primer pulso (bool)  |
| `midi_output_id`  | ID del puerto MIDI elegido  |

#### 3.4 Edición de Tempo y Tono en sesión (solo Repertorio)

Junto al botón "Guardar notas" aparecen dos controles adicionales:

- **Tempo:** campo numérico con el tempo actual de la pieza. Al pulsar "Guardar tempo": actualiza `piezas.tempo` en BD y ajusta el metrónomo.
- **Tono:** desplegable GM con el programa actual. Al pulsar "Guardar tono": actualiza `piezas.programa_midi` en BD y envía Program Change MIDI.

Al avanzar a la siguiente pieza en Repertorio, ambos controles se actualizan automáticamente.

#### 3.5 MIDI (Web MIDI API)

**Requisito:** HTTPS + navegador Chromium-based.

**Selector de dispositivo:** en la parte superior del widget del metrónomo. Solo aparece si `navigator.requestMIDIAccess` está disponible. Detecta dispositivos en hot-plug.

**Envío automático de Program Change:**
- Al pulsar "Iniciar" en una actividad de Repertorio
- Al completar una pieza y cargar la siguiente

**Mensaje enviado:** `[0xC0, programa_midi]` (canal 1, program 0–127)

---

### 4. Admin (`admin.php`)

- **Configuración del metrónomo:** BPM por defecto para Técnica y Práctica, guardados en tabla `configuracion`
- **Gestión de sesiones:** enlace a `gestionar_sesiones.php`
- **Exportación:** CSV de sesiones, backup SQL completo
- **Importación:** restaurar backup SQL
- **Borrar datos:** eliminación completa con confirmación
- **Cambiar contraseña**

---

### 5. Gestionar Sesiones (`gestionar_sesiones.php`)

CRUD de sesiones históricas:
- Crear sesión manual con fecha, actividades, piezas y tiempos
- Editar y eliminar sesiones pasadas

---

## 🧮 Algoritmos Clave

### Algoritmo de Sugerencia de Piezas

**Ubicación:** `config/database.php` → `obtenerPiezaSugerida()`

```
Score = SUM((10 - Fallos_día_i) × Peso_día_i) × (1 / Ponderación)

Peso_día_i: hace 30 días → 1; ayer → 30
Fallos → puntos: 0 fallos = 10 pts, 10+ fallos = 0 pts
```

**Ordenamiento:** menor score = mayor prioridad.

**Casos especiales:**
- Sin práctica reciente → score 0 → máxima prioridad
- Alta ponderación → score reducido → más prioridad

**SQL:**
```sql
SELECT SUM(
    GREATEST(0, 10 - f.cantidad) *
    (31 - DATEDIFF(CURDATE(), DATE(f.fecha_registro)))
) as suma_ponderada
FROM fallos f
WHERE f.pieza_id = :pieza_id
  AND f.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND DATEDIFF(CURDATE(), DATE(f.fecha_registro)) < 30
```

---

## 📚 API de Funciones y AJAX

### Funciones globales (`config/database.php`)

#### `getDB() → PDO`
Devuelve la conexión PDO (singleton).

#### `formatearTiempo(int $segundos) → string`
Convierte segundos a `HH:MM:SS`.

#### `obtenerPiezaSugerida(PDO $db, array $excluidas) → array|null`
Devuelve la pieza con menor score excluyendo las ya practicadas en la sesión actual.

---

### Acciones AJAX (`sesion.php`)

Todas vía `POST sesion.php` con header `X-Requested-With: XMLHttpRequest` y body JSON.

| Acción                | Parámetros de entrada                                     | Respuesta                                        |
|-----------------------|-----------------------------------------------------------|--------------------------------------------------|
| `iniciar`             | `actividad_id`                                            | `{success}`                                      |
| `guardar`             | `actividad_id`, `tiempo`                                  | `{success}`                                      |
| `guardar_notas`       | `actividad_id`, `notas`                                   | `{success}`                                      |
| `guardar_tempo`       | `pieza_id`, `tempo` (20–300)                              | `{success}`                                      |
| `guardar_programa_midi`| `pieza_id`, `programa_midi` (0–127)                      | `{success}`                                      |
| `completar_pieza`     | `actividad_id`, `pieza_id`, `fallos`, `tiempo`            | `{success, siguiente_pieza: {id, compositor, titulo, tempo, programa_midi} \| null}` |
| `terminar_repertorio` | `actividad_id`, `tiempo`, `pieza_id`, `fallos`            | `{success, hay_siguiente}`                       |
| `siguiente`           | `actividad_id`, `tiempo`, `pieza_id`, `fallos`            | `{success, hay_siguiente}`                       |
| `finalizar`           | `sesion_id`, `actividad_id`, `tiempo`, `pieza_id`, `fallos`| `{success}`                                     |

---

## 🛠️ Guía de Desarrollo

### Añadir nueva página

```php
<?php
require_once 'config/database.php';
$pageTitle = 'Mi Módulo - Piano Tracker';
$db = getDB();
include 'includes/header.php';
?>
<!-- HTML aquí -->
<?php include 'includes/footer.php'; ?>
```

### Añadir campo a `piezas`

1. Crear migración SQL:
```sql
ALTER TABLE piezas ADD COLUMN mi_campo VARCHAR(100) DEFAULT NULL;
```

2. Actualizar INSERT/UPDATE en `repertorio.php`
3. Añadir campo al formulario y a la tabla

### Modificar BPM por defecto del metrónomo

Desde la interfaz: **Admin** → "Configuración del metrónomo".

Directamente en BD:
```sql
INSERT INTO configuracion (clave, valor, descripcion)
VALUES ('metro_bpm_tecnica', '120', 'BPM Técnica')
ON DUPLICATE KEY UPDATE valor = '120';
```

### Añadir nuevo instrumento GM personalizado

El array de 128 instrumentos GM está definido inline en `repertorio.php` (`$gmInstrumentos`) y en `sesion.php` (PHP array `$gmInstrumentosSession` + JS array `GM_INSTRUMENTS`). Para modificar nombres, actualizar los tres lugares.

---

## 🔒 Seguridad

- **Prepared Statements** en todas las consultas SQL (PDO)
- **htmlspecialchars()** en todos los outputs HTML
- **Validación de rangos** en AJAX: tempo (20–300), programa MIDI (0–127), BPM (20–300)
- **HTTPS requerido** para Web MIDI API

---

## 🐛 Troubleshooting

| Problema | Causa probable | Solución |
|----------|---------------|----------|
| MIDI no disponible | HTTP en lugar de HTTPS | Servir la app por HTTPS |
| MIDI no disponible | Firefox/DuckDuckGo | Usar Chrome, Brave o Vivaldi |
| Piano no aparece en lista | Cable no seleccionado como MIDI | En Android, elegir modo MIDI al conectar USB |
| Metrónomo sin sonido | Sin interacción previa del usuario | Pulsar cualquier botón antes de iniciar |
| BPM por defecto incorrecto | Sin registros en `configuracion` | Guardar desde Admin → Configuración metrónomo |
| Cronómetro no guarda | Error AJAX | Ver consola del navegador (F12) |

---

## 📝 Changelog

### v1.6 — Junio 2026

**Metrónomo:**
- ✅ Metrónomo integrado en la vista de sesión (Web Audio API, scheduler de lookahead)
- ✅ Controles BPM: -5 / -1 / +1 / +5
- ✅ Pulsos por compás configurables (1–12), indicadores visuales por pulso
- ✅ Acento en primer pulso (880 Hz vs 440 Hz), activable/desactivable
- ✅ Control de volumen con slider; persiste en `localStorage`
- ✅ BPM por defecto según tipo de actividad, configurable desde Admin
- ✅ En Repertorio: BPM se ajusta automáticamente al tempo de la pieza

**MIDI:**
- ✅ Soporte Web MIDI API (Chrome/Chromium en Android y escritorio)
- ✅ Columna `programa_midi` (INT, 0–127) añadida a tabla `piezas`
- ✅ Desplegable con los 128 instrumentos General MIDI en Repertorio
- ✅ Selector de puerto MIDI en el widget del metrónomo
- ✅ Program Change automático al iniciar actividad de Repertorio y al cambiar de pieza
- ✅ Tono MIDI editable durante la sesión sin salir de la página

**Sesión:**
- ✅ Tempo de la pieza editable durante práctica de Repertorio (guarda en BD + actualiza metrónomo)
- ✅ Tono MIDI editable durante práctica de Repertorio (guarda en BD + envía PC)
- ✅ Botones del metrónomo optimizados para uso táctil (tablet)

**Repertorio:**
- ✅ Campo "Instrumento" (texto libre) eliminado; sustituido por "Tono MIDI (GM)"
- ✅ Columna "Tono GM" en tabla de piezas

**Admin:**
- ✅ Nueva sección "Configuración del metrónomo" (BPM Técnica y Práctica)

### v1.5 — Febrero 2025

- ✅ Flujo automático entre actividades
- ✅ Precarga inteligente de la última sesión
- ✅ Botones contextuales (última actividad, tipo repertorio)
- ✅ Auto-finalización de sesión
- ✅ Visualización de tempo junto a cada pieza
- 🐛 Corregida duplicación de última pieza en repertorio
- 🐛 Corregida ordenación de fechas en gestionar_sesiones

---

**Piano Tracker v1.6 — Documentación Técnica**
