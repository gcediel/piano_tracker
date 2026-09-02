# Piano Tracker - Documentación Técnica v1.9

**Aplicación web (+ app de escritorio Electron) para gestión de práctica de piano**  
**Autor:** Guillermo  
**Fecha de creación:** Enero 2025  
**Última actualización:** Agosto 2026  
**Versión:** 1.9  
**Stack:** PHP 8.x + MySQL 8.x / MariaDB + Vanilla JavaScript, con una segunda implementación en Node/Express/EJS (Electron) en `App/` que comparte la misma base de datos

---

## 📋 Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Requisitos del Sistema](#requisitos-del-sistema)
3. [Instalación](#instalación)
4. [Estructura de Archivos](#estructura-de-archivos)
5. [Base de Datos](#base-de-datos)
6. [Funcionalidades por Módulo](#funcionalidades-por-módulo) (incluye [App Electron](#8-app-electron-app))
7. [Algoritmos Clave](#algoritmos-clave)
8. [API de Funciones y AJAX](#api-de-funciones-y-ajax)
9. [Guía de Desarrollo](#guía-de-desarrollo)
10. [Seguridad](#-seguridad)
11. [Changelog](#-changelog)

---

## 📖 Descripción General

Piano Tracker es una aplicación para pianistas que permite:
- Gestionar un repertorio de piezas musicales con tono MIDI asignado y progresión automática de tempo
- Practicar ejercicios de técnica con rotación y escalera de BPM adaptativa
- Registrar sesiones de práctica con cronómetro y metrónomo integrado
- Controlar el sonido del piano vía MIDI (Web MIDI API en la web; MIDI nativo en la app Electron)
- Llevar seguimiento de errores/fallos por pieza, distinguiendo pase libre y pase con metrónomo
- Obtener sugerencias inteligentes de piezas a practicar
- Visualizar estadísticas y tendencias de práctica
- (Solo app Electron) Controlar en vivo un sintetizador Roland GO:KEYS: ~1200 tonos propios, presets y mezcla

Existen **dos implementaciones que comparten la misma base de datos MySQL/MariaDB**: la aplicación web PHP (raíz del repositorio, para navegador/tablet) y una app de escritorio Electron/Node en `App/` (para PC, con integración MIDI nativa vía Node en vez de Web MIDI API). Ambas se mantienen en paralelo con el mismo algoritmo y las mismas reglas pedagógicas — ver [App Electron (`App/`)](#8-app-electron-app).

### Características principales

- **Gestión de repertorio:** CRUD de piezas con metadatos (compositor, título, grado, tempo, tempo objetivo, ponderación, tono MIDI GM)
- **Mantenimiento de repertorio:** progresión automática mensual de tempo y graduación automática a "mantenimiento" según media de fallos con metrónomo; démoción automática de vuelta a aprendizaje si baja el rendimiento
- **Técnica por ejercicios (`tecnica.php`):** CRUD de ejercicios con rotación round-robin y escalera de BPM (sube/baja/mantiene según valoración Bien/Neutro/Mal), con tope de reintentos consecutivos en "Mal"
- **Metrónomo integrado:** BPM ajustable, pulsos por compás configurables, acento en primer pulso, control de volumen; preferencias persistidas en `localStorage`
- **Soporte MIDI:** Envío automático de Program Change (Web MIDI API) al iniciar y cambiar pieza en Repertorio
- **Piano MIDI (`midi.php`):** Página dedicada para seleccionar instrumento (128 voces GM, 16 categorías) y controlar parámetros MIDI (CC7, CC91, CC93, CC10); incluye metrónomo independiente
- **Sesiones de práctica:** Cronómetro con flujo automático entre actividades; registro de fallos distinguiendo pasada "libre" y "con metrónomo"; aviso emergente al subir/bajar de nivel de fallos durante la sesión
- **Edición en sesión:** Tempo y tono MIDI de una pieza editables durante la práctica sin salir de la página
- **Configuración de metrónomo:** BPM por defecto para Técnica y Práctica configurables desde Admin
- **Informes visuales:** Estadísticas con DataTables, gráficos y análisis temporal
- **App de escritorio (Electron, `App/`):** misma funcionalidad que la web (más control en vivo del Roland GO:KEYS), sin necesidad de servidor Apache ni HTTPS

---

## 💻 Requisitos del Sistema

### Servidor (app web PHP)
- **PHP:** 8.0 o superior
- **MySQL:** 8.0+ o **MariaDB:** 10.3+
- **Apache/Nginx** con mod_rewrite / `try_files`
- **HTTPS** obligatorio para el uso del metrónomo MIDI (Web MIDI API)

### Cliente (app web PHP)
- Navegador **Chromium-based** para MIDI (Chrome, Brave, Vivaldi, Edge)
- JavaScript habilitado
- Para MIDI en tablet Android: cable **USB-C a USB-B** + piano con puerto USB to Host

### App de escritorio (Electron, `App/`)
- **Node.js** 18+ (para `npm install` y ejecutar Electron)
- Acceso a la misma base de datos MySQL/MariaDB que la app web (local o remota)
- No requiere Apache, HTTPS ni navegador Chromium — Electron trae su propio Chromium y el acceso MIDI es nativo vía Node
- (Opcional) Sintetizador **Roland GO:KEYS** conectado por USB para la página de control en vivo

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
# Migraciones, en orden cronológico (schema.sql solo trae el esquema base v1.x):
mysql -u piano_user -p piano_tracker < migracion_v1.3.sql
mysql -u piano_user -p piano_tracker < migracion_midi.sql
mysql -u piano_user -p piano_tracker < migracion_tecnica.sql
mysql -u piano_user -p piano_tracker < migracion_mantenimiento.sql
mysql -u piano_user -p piano_tracker < inicializar_tempo_objetivo.sql
mysql -u piano_user -p piano_tracker < migracion_graduacion_automatica.sql
mysql -u piano_user -p piano_tracker < migracion_tono_roland.sql   # solo necesaria si se usa la app Electron con Roland
```

### 3. Configurar conexión

`config/database.php` **no está en git** (ver `.gitignore`) porque contiene la contraseña real de la BD. Copiar la plantilla y editar:

```bash
cp config/database.example.php config/database.php
```

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'piano_tracker');
define('DB_USER', 'piano_user');
define('DB_PASS', 'tu_contraseña');
```

`config/database.php` incluye automáticamente `includes/funciones.php` (algoritmos y helpers, sin credenciales, sí versionado).

### 4. Configurar el login

La contraseña de acceso se guarda como hash en `configuracion.password_hash` (ver tabla `configuracion` más abajo). Generar el hash con:

```bash
php generar_hash.php 'tu_contraseña'
```

y aplicar el `UPDATE` que imprime el script. `generar_hash.php` recibe la contraseña como argumento de línea de comandos — no la hardcodees en el archivo.

### 5. Permisos

```bash
chown www-data:www-data -R /var/www/html/piano_tracker
chmod 755 -R /var/www/html/piano_tracker
```

---

## 📁 Estructura de Archivos

```
piano_tracker/
├── config/
│   ├── database.php            # Conexión DB (credenciales) — NO versionado, ver .gitignore
│   ├── database.example.php    # Plantilla de config/database.php
│   └── auth.php                # Login/logout, estaAutenticado(), requerirAuth()
├── includes/
│   ├── funciones.php           # Algoritmos y helpers de negocio (SÍ versionado, sin credenciales)
│   ├── header.php               # Cabecera HTML + navegación
│   └── footer.php               # Pie de página
├── ajax/
│   ├── timer.php                # Guardado periódico del cronómetro
│   └── sugerencias.php          # Aplicar/descartar avisos de progresión (tempo, graduación)
├── assets/
│   ├── css/
│   │   └── style.css            # Estilos globales (incluye metrónomo)
│   └── js/
│       └── app.js               # JS auxiliar
├── database/
│   └── schema.sql               # Esquema BASE de la BD (v1.x; las migraciones lo completan)
├── App/                         # App de escritorio Electron/Node — ver sección 7
│   ├── main.js                  # Proceso principal Electron
│   ├── server/                  # Servidor Express (mismo dominio funcional que la web)
│   │   ├── config.js            # Credenciales DB — NO versionado
│   │   ├── config.example.js    # Plantilla de server/config.js
│   │   ├── database.js          # Pool mysql2 (misma BD que la app web)
│   │   ├── helpers.js           # Puerto Node de includes/funciones.php
│   │   └── routes/               # Un router por página (dashboard, sesion, repertorio, tecnica, roland…)
│   ├── views/                    # Plantillas EJS (equivalentes a los .php)
│   └── assets/js/                # tone-picker.js, roland_tones.js, midi-roland.js, roland-presets.js
├── index.php                    # Dashboard
├── repertorio.php               # Gestión de piezas + tono MIDI GM + mantenimiento
├── sesion.php                   # Sesiones, timer, metrónomo, MIDI
├── tecnica.php                  # CRUD de ejercicios de técnica
├── midi.php                     # Control MIDI del piano (instrumento, efectos, metrónomo)
├── informes.php / informe_mensual.php / informe_anual.php   # Estadísticas
├── admin.php                    # Administración + config metrónomo + reseteo masivo BPM técnica
├── gestionar_sesiones.php       # CRUD de sesiones manuales
├── login.php / logout.php       # Autenticación
├── generar_hash.php             # Genera el hash de la contraseña (recibe la contraseña por CLI)
├── migracion_v1.3.sql
├── migracion_midi.sql
├── migracion_tecnica.sql               # Tablas ejercicios_tecnica / sesion_tecnica_ejercicios
├── migracion_mantenimiento.sql         # tempo_objetivo, estado, fallos.tipo_pasada
├── inicializar_tempo_objetivo.sql      # Inicializa tempo_objetivo = tempo en piezas existentes
├── migracion_graduacion_automatica.sql # Graduación deja de ser sugerencia y se aplica sola
├── migracion_tono_roland.sql           # Tono Roland (msb/lsb/pc) por pieza — solo relevante para App/
├── auditoria_pedagogica.md      # Auditoría de la metodología pedagógica (histórica + addendum)
└── README.md
```

---

## 🗄️ Base de Datos

### Tabla: `piezas`

Esquema base (`database/schema.sql`) más las columnas añadidas por `migracion_mantenimiento.sql`, `migracion_graduacion_automatica.sql` y (solo si se usa la app Electron) `migracion_tono_roland.sql`:

```sql
CREATE TABLE piezas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    compositor    VARCHAR(200) NOT NULL,
    titulo        VARCHAR(300) NOT NULL,
    libro         VARCHAR(200),
    grado         INT,
    tempo         INT,
    tempo_objetivo INT NULL,                        -- tempo al que se gradúa a mantenimiento
    ponderacion   DECIMAL(5,2) DEFAULT 1.00,
    instrumento   VARCHAR(50)  DEFAULT 'Piano',      -- campo legado, no se usa en UI
    programa_midi INT          NOT NULL DEFAULT 0,   -- programa General MIDI (0-127)
    tono_nombre   VARCHAR(60)  NULL,                 -- (solo App/) nombre del tono Roland asignado
    tono_msb      TINYINT UNSIGNED NULL,             -- (solo App/) Bank Select MSB del tono Roland
    tono_lsb      TINYINT UNSIGNED NULL,             -- (solo App/) Bank Select LSB del tono Roland
    tono_pc       SMALLINT UNSIGNED NULL,            -- (solo App/) Program Change del tono Roland
    estado        ENUM('aprendizaje','mantenimiento') NOT NULL DEFAULT 'aprendizaje',
    mes_evaluado  DATE NULL,                         -- último mes natural ya evaluado por evaluarProgresionMensual()
    meses_objetivo_consecutivos INT NOT NULL DEFAULT 0,
    sugerencia_tempo_pendiente  INT NULL,            -- BPM sugerido, pendiente de que el usuario lo confirme
    aviso_graduacion_pendiente  BOOLEAN NOT NULL DEFAULT FALSE, -- graduación YA aplicada; solo pendiente de "Entendido"
    activa        BOOLEAN      DEFAULT TRUE,
    fecha_creacion TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);
```

**Campos clave:**
- `tempo`: Velocidad actual de la pieza; se carga automáticamente en el metrónomo al iniciar Repertorio
- `tempo_objetivo`: Tempo al que se aspira antes de graduar la pieza a "mantenimiento" (ver [Algoritmos Clave](#-algoritmos-clave))
- `programa_midi`: Número de programa General MIDI (0 = Acoustic Grand Piano). Se envía como MIDI Program Change al piano
- `tono_nombre`/`tono_msb`/`tono_lsb`/`tono_pc`: tono específico del Roland GO:KEYS (solo lo usa la app Electron; si están vacíos, `App/` usa `programa_midi` como en la web)
- `ponderacion`: Factor de prioridad en el algoritmo de sugerencia
- `estado`: `aprendizaje` (compite normalmente por prioridad) o `mantenimiento` (solo se sugiere si no queda ninguna pieza en aprendizaje pendiente)
- `sugerencia_tempo_pendiente` / `aviso_graduacion_pendiente`: banderas que muestra el dashboard (`index.php`) para confirmar la subida de tempo o descartar el aviso de graduación ya aplicada

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
    tipo_pasada     ENUM('libre','metronomo') NOT NULL DEFAULT 'metronomo',
    fecha_registro  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (pieza_id)     REFERENCES piezas(id)      ON DELETE CASCADE
);
```

`tipo_pasada` distingue la pasada libre previa (sin metrónomo) de la interpretación real con metrónomo. Solo los fallos `'metronomo'` alimentan `obtenerPiezaSugerida()` y la progresión mensual de tempo/graduación (ver [Algoritmos Clave](#-algoritmos-clave)).

### Tabla: `ejercicios_tecnica`

```sql
CREATE TABLE ejercicios_tecnica (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    bloque         TINYINT NOT NULL,
    numero         TINYINT NOT NULL,
    nombre         VARCHAR(300) NOT NULL,
    bpm            INT NOT NULL DEFAULT 120,
    comentarios    TEXT,
    activo         BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bloque_numero (bloque, numero)
);
```

### Tabla: `sesion_tecnica_ejercicios`

```sql
CREATE TABLE sesion_tecnica_ejercicios (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id   INT NOT NULL,
    ejercicio_id   INT NOT NULL,
    resultado      ENUM('bien','neutro','mal') NOT NULL,
    bpm_practicado INT NOT NULL,
    fecha          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (ejercicio_id) REFERENCES ejercicios_tecnica(id) ON DELETE CASCADE
);
```

Registra cada valoración de un ejercicio de técnica durante una sesión; alimenta el orden "menos practicado primero" (`veces_total`) y la escalera de BPM. `actividades.tipo` incluye además `'tecnica_ejercicios'` y `'practica_tecnica'` (ver `migracion_tecnica.sql`).

### Relaciones

```
sesiones (1) ──→ (N) actividades
piezas   (1) ──→ (N) actividades           [solo tipo='repertorio']
piezas   (1) ──→ (N) fallos
actividades (1) ──→ (N) fallos
actividades (1) ──→ (N) sesion_tecnica_ejercicios
ejercicios_tecnica (1) ──→ (N) sesion_tecnica_ejercicios
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
- **Campos del formulario:** Compositor, Título, Libro, Grado, Tempo, Tempo objetivo, Tono MIDI (GM, desplegable con los 128 instrumentos GM), Ponderación
- **Campo eliminado:** "Instrumento" (texto libre) — reemplazado por el desplegable Tono MIDI
- **Tabla de piezas** con DataTables: muestra número de programa GM en columna "Tono GM", más columnas "Objetivo" y "Categoría" (Aprendizaje/Mantenimiento)
- **Botón manual "A mantenimiento"**: pasa la pieza a `estado = 'mantenimiento'` sin esperar a la graduación automática
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

#### 2.1 Mantenimiento de repertorio (progresión automática)

Al cargar el dashboard (`index.php`), `evaluarProgresionMensual()` (`includes/funciones.php`) revisa una vez por mes natural cada pieza activa en `aprendizaje` con `tempo_objetivo` definido:

- Si la media de fallos **con metrónomo** del mes anterior es ≤ 1 y `tempo < tempo_objetivo`: se marca `sugerencia_tempo_pendiente` (tempo actual + 5 BPM, sin pasar del objetivo). El dashboard la muestra como aviso a confirmar; el usuario la aplica o descarta desde `ajax/sugerencias.php` (acciones `aplicar_tempo` / `descartar_tempo`).
- Si `tempo` ya alcanzó `tempo_objetivo`: cuenta como "mes válido hacia la graduación" solo si la media de fallos con metrónomo es ≤ 0.25 **y** hubo ≥ 8 días practicados ese mes (un umbral más exigente que el de subir tempo, para que un mes con pocas sesiones sueltas no cuente). Tras 3 meses consecutivos así, la pieza pasa a `estado = 'mantenimiento'` **automáticamente** (mismo efecto que el botón manual) y se marca `aviso_graduacion_pendiente` solo para informar en el dashboard, con un botón "Entendido" que la descarta (`descartar_graduacion`); no pide confirmación porque el cambio ya se aplicó.
- Si no se cumple ninguna condición, se reinicia el contador de meses consecutivos.

Una pieza en `mantenimiento` cuyos **dos últimos registros de fallos** (de cualquier tipo de pasada) sean ambos > 0 vuelve automáticamente a `aprendizaje` (`revisarDemocionMantenimiento()`), bajando el tempo a `tempo_objetivo - 8` BPM en vez de reiniciar la escalera desde cero. Esta comprobación se dispara cada vez que se registra un fallo de una pieza en mantenimiento (`registrarFallo()`).

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

| Clave             | Contenido                         | Compartida con |
|-------------------|-----------------------------------|----------------|
| `metro_volumen`   | Volumen (0.0 – 1.0)               | `midi.php`     |
| `metro_acento`    | Acento primer pulso (bool)        | `midi.php`     |
| `midi_output_id`  | ID del puerto MIDI elegido        | `midi.php`     |
| `midi_program`    | Último programa GM enviado        | `midi.php`     |

#### 3.4 Registro de fallos y aviso de cambio de nivel (solo Repertorio)

Al terminar una pieza de repertorio, el formulario de fallos distingue **pasada libre** (sin metrónomo) de **pasada con metrónomo** (`tipo_pasada`), ya que solo la segunda alimenta el algoritmo de sugerencia y la progresión de tempo/graduación (ver [Mantenimiento de repertorio](#21-mantenimiento-de-repertorio-progresión-automática)).

`registrarFallo()` compara el nivel de "media de fallos/día (30 días)" de la pieza (misma escala de color que la leyenda de `repertorio.php`, función `nivelFallos()`) antes y después de guardar el registro. Si el nivel sube o baja, se muestra un **popup propio** (no el `confirm()`/`alert()` nativo del navegador) felicitando el progreso o avisando del retroceso.

#### 3.5 Edición de Tempo y Tono en sesión (solo Repertorio)

Junto al botón "Guardar notas" aparecen dos controles adicionales:

- **Tempo:** campo numérico con el tempo actual de la pieza. Al pulsar "Guardar tempo": actualiza `piezas.tempo` en BD y ajusta el metrónomo.
- **Tono:** desplegable GM con el programa actual. Al pulsar "Guardar tono": actualiza `piezas.programa_midi` en BD y envía Program Change MIDI.

Al avanzar a la siguiente pieza en Repertorio, ambos controles se actualizan automáticamente.

#### 3.6 MIDI (Web MIDI API)

**Requisito:** HTTPS + navegador Chromium-based.

**Selector de dispositivo:** en la parte superior del widget del metrónomo. Aparece siempre que el navegador soporte Web MIDI. Si el permiso está bloqueado, muestra un mensaje de error con instrucciones para Chrome y un botón "Reintentar conexión". Detecta dispositivos en hot-plug.

**Envío automático de Program Change:**
- Al pulsar "Iniciar" en una actividad de Repertorio
- Al completar una pieza y cargar la siguiente

**Mensaje enviado:** `[0xC0, programa_midi]` (canal 1, program 0–127)

---

### 4. Técnica (`tecnica.php`)

CRUD de ejercicios de técnica (nombre, BPM, comentarios, bloque/número, activo/inactivo), con tabla DataTables y contador de reproducciones por ejercicio.

**En sesión** (actividad de tipo `tecnica_ejercicios`, lógica en `sesion.php`):
- **Orden de rotación:** `ORDER BY veces_total ASC, bpm ASC, nombre ASC` — prioriza el ejercicio con menos repeticiones históricas, sin tener en cuenta la última valoración
- **Escalera de BPM adaptativa** tras cada intento (acción AJAX `ejercicio_valorar`): `Mal` → BPM−1, `Bien` → BPM+1, `Neutro` → sin cambio; suelo de 20 BPM
- **Tope de reintentos consecutivos en "Mal"** (`TOPE_INTENTOS_MAL`): al alcanzarlo, rota al siguiente ejercicio en vez de insistir indefinidamente
- **Reseteo individual** desde `tecnica.php`: BPM a 120 y borra el historial de prácticas de ese ejercicio
- **Reseteo masivo** desde `admin.php`: reinicia el BPM de todos los ejercicios activos a un valor común

---

### 5. Piano MIDI (`midi.php`)

Página dedicada al control del piano desde la tablet, sin necesidad de acceder al panel físico del instrumento.

No requiere base de datos. Todo el estado se persiste en `localStorage`.

#### 5.1 Conexión MIDI

Idéntica lógica que `sesion.php`. Comparte la clave `midi_output_id` por lo que el dispositivo seleccionado en una página queda seleccionado en la otra. Al conectar un dispositivo se envía automáticamente el estado actual (programa, volumen y efectos).

Si `requestMIDIAccess` falla:
- **`SecurityError`**: permiso bloqueado — muestra instrucciones específicas para Chrome y botón "Reintentar conexión"
- **Otros errores**: muestra el mensaje de error del navegador

#### 5.2 Selección de instrumento

- 128 instrumentos General MIDI organizados en 16 categorías de 8 voces cada una
- Categorías: Piano, Cromatofón, Órgano, Guitarra, Bajo, Cuerdas, Conjunto, Metal, Lengüeta, Flauta, Sínt. Lead, Sínt. Pad, Sínt. FX, Étnicos, Percusión, Efectos SFX
- Al pulsar un instrumento: envía `[0xC0, programa]` (Program Change, canal 1) y guarda en `localStorage` (`midi_program`)
- La categoría y voz activa se restauran al cargar la página

#### 5.3 Metrónomo

Mismo motor que `sesion.php` (Web Audio API). BPM por defecto 80, recuperado de `localStorage` (`metro_bpm`). Comparte las claves `metro_volumen` y `metro_acento` con la sesión.

#### 5.4 Controles MIDI (CC)

| Control  | CC MIDI | Rango   | localStorage  |
|----------|:-------:|---------|---------------|
| Volumen  | CC 7    | 0–127   | `midi_cc7`    |
| Reverb   | CC 91   | 0–127   | `midi_cc91`   |
| Chorus   | CC 93   | 0–127   | `midi_cc93`   |
| Paneo    | CC 10   | 0–127   | `midi_cc10`   |

Todos se restauran de `localStorage` al cargar la página y se reenvían al piano al conectar un dispositivo.

---

### 6. Admin (`admin.php`)

- **Configuración del metrónomo:** BPM por defecto para Técnica y Práctica, guardados en tabla `configuracion`
- **Reseteo masivo de técnica:** BPM de todos los ejercicios de técnica activos a un valor común
- **Gestión de sesiones:** enlace a `gestionar_sesiones.php`
- **Exportación:** CSV de sesiones, backup SQL completo
- **Importación:** restaurar backup SQL
- **Borrar datos:** eliminación completa con confirmación
- **Cambiar contraseña**

---

### 7. Gestionar Sesiones (`gestionar_sesiones.php`)

CRUD de sesiones históricas:
- Crear sesión manual con fecha, actividades, piezas y tiempos
- Editar y eliminar sesiones pasadas

---

### 8. App Electron (`App/`)

Reimplementación de la app en **Node/Express + EJS** (Electron como shell de escritorio), pensada para PC con el piano conectado por USB, sin depender de Apache/HTTPS/Web MIDI API. **Comparte la misma base de datos MySQL/MariaDB que la app web** (`App/server/config.js`, plantilla en `config.example.js`) — los datos y el algoritmo de sugerencia son los mismos vistos desde cualquiera de las dos apps.

**Arquitectura:**
- `main.js`: ventana Electron (sin menú, zoom con Ctrl +/-/0), carga `http://127.0.0.1:3712` tras arrancar el servidor Express embebido
- `server/index.js`: servidor Express con un router por página bajo `server/routes/` (`dashboard`, `sesion`, `repertorio`, `tecnica`, `informes`, `informe_mensual`, `informe_anual`, `admin`, `gestionar_sesiones`, `roland`, `sugerencias`)
- `server/helpers.js`: puerto a JavaScript de `includes/funciones.php` — mismas funciones (`obtenerPiezaSugerida`, `evaluarProgresionMensual`, `revisarDemocionMantenimiento`, `nivelFallos`, `registrarFallo`…), misma lógica pedagógica
- `views/*.ejs`: plantilla equivalente a cada `.php`; `views/partials/header.ejs` y `footer.ejs` sustituyen a `includes/header.php`/`footer.php`
- Sin sistema de login: al ser una app de escritorio de un único usuario, no hay `auth.php` equivalente

**Diferencias con la app web:**
- **Sin `midi.php` equivalente** para instrumentos GM genéricos; en su lugar, una página **`roland.php` / `/roland`** exclusiva de la app Electron para control en vivo del sintetizador **Roland GO:KEYS** por MIDI nativo (canal 4, el canal de recepción fijo del GO:KEYS): selector de ~1200 tonos propios del Roland (no GM) con búsqueda y organización en grupos/categorías (`assets/js/tone-picker.js` + `assets/js/roland_tones.js`), hasta 10 presets favoritos (`roland-presets.js`), y mezcla en vivo (Volumen CC7, Expresión CC11, Paneo, Modulación…) vía `assets/js/midi-roland.js`
- El mismo selector de tono Roland (`tone_picker` parcial EJS) se reutiliza en `sesion.ejs` y `repertorio.ejs` para asignar `tono_nombre`/`tono_msb`/`tono_lsb`/`tono_pc` a una pieza (columnas de `migracion_tono_roland.sql`); si una pieza no tiene tono Roland asignado, la app usa `programa_midi` como en la web
- PDFs de referencia del fabricante incluidos en el repo (`App/GOKEYS_*.pdf`): implementación MIDI, lista de estilos y lista de tonos del GO:KEYS

**Instalación (Linux):**
```bash
cd App
npm install
cp server/config.example.js server/config.js   # editar credenciales de la misma BD
npm start                                        # arranca Electron (o: npm run server, solo el backend)
./instalar_acceso_directo.sh                     # opcional: icono en el menú de aplicaciones
```

**Regla de mantenimiento:** todo cambio de comportamiento en la app PHP debe replicarse en `App/` (y viceversa), salvo lo específico de cada una (login/HTTPS en la web; Roland/Electron en `App/`) — así lo confirman los commits recientes ("Aplicado en paralelo en PHP y en la app Electron").

---

## 🧮 Algoritmos Clave

Todos definidos en `includes/funciones.php` (puerto equivalente en `App/server/helpers.js`).

### Algoritmo de Sugerencia de Piezas

**Ubicación:** `obtenerPiezaSugerida()`

```
Score = SUM((10 - Fallos_día_i) × 0.5^(días_desde_i / 15)) × (1 / Ponderación)

Fallos → puntos: 0 fallos = 10 pts, 10+ fallos = 0 pts
Decaimiento exponencial con semivida de 15 días, sobre una ventana de 180 días
Solo cuentan los fallos con tipo_pasada = 'metronomo' (el pase libre no entra en el cálculo)
```

**Ordenamiento:** menor score = mayor prioridad.

**Casos especiales:**
- Ninguna pasada con metrónomo registrada en los últimos 180 días → score `-1` (máxima prioridad posible, por delante incluso de una pieza dominada con score cercano a 0)
- Alta ponderación → score reducido → más prioridad
- Pieza en `estado = 'mantenimiento'` → se le suma `+1000000` al score, para que solo se sugiera cuando ya no queda ninguna pieza en `aprendizaje` disponible en la actividad

Nota de diseño: el corte binario original a 30 días (una pieza sin fallos recientes pasaba a score 0, igual que una recién añadida sin historial) se sustituyó por este decaimiento continuo para distinguir "nunca evaluada" de "dominada y sin fallos recientes" — ver `auditoria_pedagogica.md`, punto 5 de la lista priorizada.

### Progresión mensual de tempo y graduación a mantenimiento

**Ubicación:** `evaluarProgresionMensual($db)` — se invoca una vez por carga del dashboard (`index.php`); es idempotente (usa `piezas.mes_evaluado` para no reevaluar el mismo mes dos veces).

Para cada pieza activa en `aprendizaje` con `tempo_objetivo` definido, evalúa el **mes natural anterior** usando solo fallos con `tipo_pasada = 'metronomo'` (`mediaFallosMetronomo()`):

```
media ≤ 1  Y  tempo < tempo_objetivo
    → sugerencia_tempo_pendiente = min(tempo_objetivo, tempo + 5)   (pendiente de confirmar por el usuario)

tempo ≥ tempo_objetivo  Y  media ≤ 0.25  Y  días_practicados ≥ 8
    → cuenta como mes válido hacia la graduación (meses_objetivo_consecutivos++)
    → a los 3 meses consecutivos: estado = 'mantenimiento' (SE APLICA SOLA) + aviso_graduacion_pendiente = 1 (solo informativo)

en cualquier otro caso
    → meses_objetivo_consecutivos = 0
```

**Démoción automática:** `revisarDemocionMantenimiento($db, $piezaId)`, llamada desde `registrarFallo()` cada vez que se registra un fallo de una pieza en `mantenimiento`. Si los dos últimos registros de fallos (cualquier `tipo_pasada`) son ambos > 0, la pieza vuelve a `estado = 'aprendizaje'` con `tempo = tempo_objetivo - 8` (suelo 20).

### Nivel de fallos y aviso de cambio en sesión

`nivelFallos($media)` clasifica la media de fallos/día (30 días, todo tipo de pasada) en 6 rangos (0 Atención → 5 Excelente), la misma escala que la leyenda de color de `repertorio.php`. `registrarFallo()` calcula el nivel antes y después de insertar un nuevo registro de fallos y, si cambia, devuelve los datos para el popup de felicitación/aviso que muestra `sesion.php` (ver [3.4](#34-registro-de-fallos-y-aviso-de-cambio-de-nivel-solo-repertorio)).

---

## 📚 API de Funciones y AJAX

### Conexión (`config/database.php`)

#### `getDB() → PDO`
Devuelve la conexión PDO (singleton). Incluye automáticamente `includes/funciones.php` al final del archivo.

### Funciones de negocio (`includes/funciones.php`)

Separadas de `config/database.php` para poder versionarlas sin exponer credenciales (`config/database.php` está en `.gitignore`; `includes/funciones.php` sí se sube a git).

#### `formatearTiempo(int $segundos) → string`
Convierte segundos a `HH:MM:SS`.

#### `getNombreActividad(string $tipo) → string`
Nombre legible de un tipo de actividad (incluye `tecnica_ejercicios`, `practica_tecnica`).

#### `obtenerPiezaSugerida(PDO $db, array $excluidas) → array|null`
Devuelve la pieza con menor score excluyendo las ya practicadas en la sesión actual. Ver [Algoritmos Clave](#-algoritmos-clave).

#### `mediaFallosMetronomo(PDO $db, int $piezaId, string $primerDiaMes) → array|null`
Media y días practicados con metrónomo de una pieza en el mes natural indicado. `null` si no hay días practicados ese mes.

#### `evaluarProgresionMensual(PDO $db) → void`
Progresión mensual de tempo/graduación a mantenimiento para todas las piezas pendientes de evaluar. Se llama una vez al cargar `index.php`.

#### `revisarDemocionMantenimiento(PDO $db, int $piezaId) → bool`
Démoción de mantenimiento a aprendizaje si corresponde. Devuelve `true` si demovió la pieza.

#### `nivelFallos(float|null $media) → array|null`
Clasifica una media de fallos/día en uno de 6 niveles (rango, texto, color).

#### `mediaFallosDia30(PDO $db, int $piezaId) → float|null`
Media de fallos/día de una pieza en los últimos 30 días (todo tipo de pasada).

#### `registrarFallo(PDO $db, int $actividadId, int $piezaId, int $cantidad, string $tipoPasada) → array|null`
Inserta el registro de fallos, revisa démoción si la pieza está en mantenimiento, y devuelve datos de cambio de nivel (para el popup de aviso) o `null` si no cambia.

---

### Acciones AJAX (`sesion.php`)

Todas vía `POST sesion.php` con header `X-Requested-With: XMLHttpRequest` y body JSON.

| Acción                | Parámetros de entrada                                                    | Respuesta                                        |
|-----------------------|----------------------------------------------------------------------------|--------------------------------------------------|
| `iniciar`             | `actividad_id`                                                            | `{success}`                                      |
| `guardar`             | `actividad_id`, `tiempo`                                                  | `{success}`                                      |
| `guardar_notas`       | `actividad_id`, `notas`                                                   | `{success}`                                      |
| `guardar_tempo`       | `pieza_id`, `tempo` (20–300)                                              | `{success}`                                      |
| `guardar_programa_midi`| `pieza_id`, `programa_midi` (0–127)                                      | `{success}`                                      |
| `completar_pieza`     | `actividad_id`, `pieza_id`, `fallos`, `tipo_pasada`, `tiempo`             | `{success, siguiente_pieza: {...} \| null, cambio_nivel: {...} \| null}` |
| `terminar_repertorio` | `actividad_id`, `tiempo`, `pieza_id`, `fallos`, `tipo_pasada`             | `{success, hay_siguiente, cambio_nivel}`         |
| `siguiente`           | `actividad_id`, `tiempo`, `pieza_id`, `fallos`, `tipo_pasada`             | `{success, hay_siguiente, cambio_nivel}`         |
| `finalizar`           | `sesion_id`, `actividad_id`, `tiempo`, `pieza_id`, `fallos`, `tipo_pasada`| `{success}`                                      |
| `ejercicio_valorar`   | `actividad_id`, `ejercicio_id`, `resultado` (bien/neutro/mal), `bpm_practicado` | `{success, siguiente_ejercicio}`            |

`cambio_nivel` (cuando no es `null`) trae `{pieza, direccion: 'sube'\|'baja', anterior, nuevo}`, devuelto por `registrarFallo()` — ver [3.4](#34-registro-de-fallos-y-aviso-de-cambio-de-nivel-solo-repertorio).

### Acciones AJAX (`ajax/sugerencias.php`)

`POST ajax/sugerencias.php` con body JSON `{accion, pieza_id}`.

| Acción                  | Efecto                                                                              |
|--------------------------|--------------------------------------------------------------------------------------|
| `aplicar_tempo`          | Aplica `sugerencia_tempo_pendiente` a `piezas.tempo` y limpia la bandera             |
| `descartar_tempo`        | Descarta la sugerencia de tempo sin aplicarla                                       |
| `descartar_graduacion`   | Descarta el aviso de graduación (la graduación en sí ya se aplicó automáticamente)   |

### Acciones POST (`tecnica.php`)

Formularios tradicionales (no AJAX): `crear`, `editar`, `activar`/`desactivar`, `resetear_bpm` (BPM a 120 + borra historial de ese ejercicio), `eliminar` (bloqueado si el ejercicio tiene prácticas registradas).

### Acción POST (`admin.php`)

`resetear_bpm_tecnica` con `bpm_reset`: reinicia el BPM de **todos** los ejercicios de técnica activos al valor indicado.

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

El array de 128 instrumentos GM está definido inline en `repertorio.php` (`$gmInstrumentos`), en `sesion.php` (PHP array `$gmInstrumentosSession` + JS array `GM_INSTRUMENTS`) y en `midi.php` (JS array `GM_INSTRUMENTS`). Para modificar nombres, actualizar los cuatro lugares.

### Mantener la app web y `App/` (Electron) en paralelo

Cualquier cambio de comportamiento o de esquema de BD hecho en la app PHP debe replicarse en `App/`, salvo lo específico de cada una (login/HTTPS en la web; Roland/Electron en `App/`):

| Cambio en la app web                          | Replicar en `App/`                                    |
|------------------------------------------------|---------------------------------------------------------|
| `includes/funciones.php`                        | `App/server/helpers.js`                                 |
| Handler AJAX en `sesion.php` / `ajax/sugerencias.php` | Router correspondiente en `App/server/routes/`      |
| Migración SQL (`ALTER TABLE`…)                  | Ninguna — es la **misma base de datos**; solo hay que aplicar la migración una vez |
| Plantilla `.php`                                 | `.ejs` equivalente en `App/views/`                       |
| `assets/css/style.css`                           | `App/assets/css/style.css` (hojas separadas, no compartidas) |

### Añadir dependencias externas (CDN)

DataTables, jQuery y Chart.js se cargan por CDN inline en cada página que los usa (no hay bundler). Al añadir una página nueva con tablas o gráficos, replicar el patrón `<script src="https://cdn...">` ya usado en `repertorio.php`/`tecnica.php`/`informes.php`.

---

## 🔒 Seguridad

- **Prepared Statements** en todas las consultas SQL (PDO / mysql2)
- **htmlspecialchars()** en todos los outputs HTML
- **Validación de rangos** en AJAX: tempo (20–300), programa MIDI (0–127), BPM (20–300)
- **HTTPS requerido** para Web MIDI API (no aplica a `App/`, que usa MIDI nativo vía Node)
- **Credenciales fuera de git:** `config/database.php` y `App/server/config.js` están en `.gitignore`; solo se versionan las plantillas `config/database.example.php` y `App/server/config.example.js`. `includes/funciones.php` (algoritmos, sin credenciales) sí está versionado
- **`generar_hash.php`** recibe la contraseña como argumento de línea de comandos (`php generar_hash.php '<contraseña>'`) en vez de tenerla hardcodeada en el archivo

---

## 🐛 Troubleshooting

| Problema | Causa probable | Solución |
|----------|---------------|----------|
| MIDI no disponible | HTTP en lugar de HTTPS | Servir la app por HTTPS |
| MIDI no disponible | Firefox/DuckDuckGo | Usar Chrome, Brave o Vivaldi |
| Piano no aparece en lista | Cable no seleccionado como MIDI | En Android, elegir modo MIDI al conectar USB |
| Acceso MIDI bloqueado | Permiso denegado previamente en Chrome | Candado en la URL → MIDI → Permitir → "Reintentar conexión" |
| Metrónomo sin sonido | Sin interacción previa del usuario | Pulsar cualquier botón antes de iniciar |
| BPM por defecto incorrecto | Sin registros en `configuracion` | Guardar desde Admin → Configuración metrónomo |
| Cronómetro no guarda | Error AJAX | Ver consola del navegador (F12) |
| `App/`: pantalla en blanco al abrir | El servidor Express aún no ha arrancado | Esperar unos segundos (arranque diferido 300 ms en `main.js`); si persiste, revisar consola de Electron |
| `App/`: "Error de conexión" en consola | `server/config.js` no existe o tiene credenciales incorrectas | Copiar `server/config.example.js` a `server/config.js` y configurar la misma BD que la web |
| `App/`: Roland no responde en la página `/roland` | Puerto MIDI no seleccionado o cable no conectado | Seleccionar la salida en el desplegable superior y pulsar "Reintentar conexión" |

---

## 📝 Changelog

### v1.9 — Agosto 2026

**Repertorio — mantenimiento de piezas:**
- ✅ La graduación a mantenimiento deja de quedar como sugerencia pendiente de confirmar: se aplica sola al alcanzar el umbral (mismo efecto que el botón manual "A mantenimiento"). El dashboard pasa a mostrar solo un aviso informativo con botón "Entendido" (`aviso_graduacion_pendiente`, renombrada desde `sugerencia_graduacion_pendiente`; `migracion_graduacion_automatica.sql`)
- ✅ Umbral de graduación más exigente que el de subir tempo: media de fallos con metrónomo ≤ 0.25 (antes ≤ 1) y mínimo de 8 días practicados en el mes, para que un solo día suelto con suerte no cuente como mes válido

**Sesión:**
- ✅ Al registrar fallos de una pieza de repertorio, se compara su nivel de "media de fallos/día (30 días)" antes y después del registro; si sube o baja de nivel se muestra un popup propio (no el `confirm()`/`alert()` nativo) felicitando el progreso o avisando del retroceso

**Seguridad:**
- ✅ `generar_hash.php` recibe la contraseña por argumento de línea de comandos en vez de tenerla hardcodeada

**App Electron:**
- ✅ Corregido el color del reloj de la sesión (`.timer-display h2` no fijaba color y `.card h2` ganaba la especificidad, dejándolo casi ilegible sobre el fondo degradado)
- ✅ Reducido el espacio del bloque "Tono en el Roland" en la sesión de práctica y ajustado el selector de tono compartido (`tone-picker`, usado también en `/roland` y `/repertorio`)

Aplicado en paralelo en la app web PHP y en `App/` (Electron) salvo lo marcado como específico de cada una.

### v1.8 — Junio–Agosto 2026

**Técnica (ejercicios) — nueva página `tecnica.php`:**
- ✅ Nuevo tipo de actividad `tecnica_ejercicios`: rotación de ejercicios por BPM ascendente/descendente según resultado (Bien/Neutro/Mal)
- ✅ Tope de reintentos consecutivos en "Mal" antes de rotar al siguiente ejercicio (`TOPE_INTENTOS_MAL`)
- ✅ Admin: reseteo masivo del BPM de todos los ejercicios de técnica a un valor común

**Repertorio — mantenimiento de piezas:**
- ✅ Campo `tempo_objetivo`: tempo al que se sugiere pasar una pieza a mantenimiento
- ✅ Progresión automática mensual: sube tempo o gradúa a mantenimiento según media de fallos con metrónomo
- ✅ Democión automática de mantenimiento a aprendizaje si los dos últimos registros de fallos son > 0
- ✅ Columnas "Objetivo" y "Categoría" (Aprendizaje/Mantenimiento) en el listado de piezas
- ✅ Algoritmo de sugerencia: decaimiento exponencial de fallos (semivida 15 días) en lugar de corte binario a 30 días; piezas en mantenimiento no compiten con las de aprendizaje activo

**Sesión:**
- ✅ Registro de fallos distingue pasada "libre" y "con metrónomo" (`tipo_pasada`)
- ✅ Modal de confirmación reutilizable (sustituye a los `confirm()` nativos del navegador)

**Arquitectura y seguridad:**
- ✅ Funciones de negocio separadas de `config/database.php` a `includes/funciones.php`, versionable sin exponer credenciales; `config/database.php` pasa a estar en `.gitignore` (plantilla en `config/database.example.php`)

**App de escritorio Electron (`App/`) — nueva:**
- ✅ Reimplementación completa en Node/Express/EJS, misma base de datos MySQL/MariaDB que la app web, mismo algoritmo de sugerencia y mismas reglas de mantenimiento (`App/server/helpers.js`)
- ✅ Control MIDI nativo vía Node (sin Web MIDI API ni HTTPS)
- ✅ Página exclusiva `/roland` para control en vivo del sintetizador Roland GO:KEYS: selector de ~1200 tonos propios con búsqueda, presets favoritos y mezcla (volumen, expresión, paneo, modulación…)
- ✅ Tono Roland asignable por pieza de repertorio (`tono_nombre`/`tono_msb`/`tono_lsb`/`tono_pc`, `migracion_tono_roland.sql`), con `programa_midi` como respaldo si no está asignado
- ✅ Script `instalar_acceso_directo.sh` para crear un icono de aplicación en Linux

### v1.7 — Junio 2026

**Piano MIDI (`midi.php`) — nueva página:**
- ✅ Selector de instrumento con 128 voces GM organizadas en 16 categorías
- ✅ Envío de Program Change al pulsar cualquier instrumento
- ✅ Controles de Volumen (CC7), Reverb (CC91), Chorus (CC93) y Paneo (CC10)
- ✅ Metrónomo integrado con el mismo motor que `sesion.php`
- ✅ Estado persistido en `localStorage`; se restaura y reenvía al piano al conectar
- ✅ Enlace en el menú de navegación principal

**MIDI — mejora de gestión de errores:**
- ✅ Si el permiso MIDI está bloqueado, se muestra mensaje de error visible con instrucciones para Chrome
- ✅ Botón "Reintentar conexión" sin necesidad de recargar la página
- ✅ Aplicado tanto en `sesion.php` como en `midi.php`

**Metrónomo:**
- ✅ Botones del metrónomo más grandes para mayor comodidad en tablet

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

**Piano Tracker v1.9 — Documentación Técnica**
