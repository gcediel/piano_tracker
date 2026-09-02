# Piano Tracker - Aplicación de seguimiento de práctica de piano

Hay dos implementaciones que comparten la misma base de datos: esta app web PHP (para navegador/tablet) y una app de escritorio Electron/Node en [`App/`](App/) (para PC, con control MIDI nativo y control en vivo de un Roland GO:KEYS). Ver `DOCUMENTACION_TECNICA.md` para el detalle de `App/`.

## Características principales

✅ **Gestión de repertorio**: CRUD completo de piezas con compositor, título, libro, grado, tempo, tempo objetivo, ponderación y tono MIDI (GM)
✅ **Mantenimiento automático de repertorio**: sube el tempo o gradúa una pieza a "mantenimiento" sola según la media de fallos con metrónomo del mes; si el rendimiento baja, la pieza vuelve a "aprendizaje" automáticamente
✅ **Técnica por ejercicios**: página `tecnica.php` con rotación de ejercicios y escalera de BPM adaptativa (sube/baja/mantiene según Bien/Neutro/Mal)
✅ **Planificación de sesiones**: Añade actividades (calentamiento, técnica, técnica por ejercicios, práctica, repertorio, improvisación, composición)
✅ **Timer dinámico con AJAX**: Cronómetro que guarda progreso automáticamente cada 5 segundos
✅ **Metrónomo integrado**: Con BPM ajustable, pulsos por compás, acento en primer pulso, control de volumen y persistencia de preferencias
✅ **Soporte MIDI**: Envío automático de Program Change al piano vía Web MIDI API al cambiar de pieza en Repertorio
✅ **Piano MIDI**: Página dedicada para seleccionar instrumento (128 voces GM en 16 categorías) y ajustar volumen, reverb, chorus y paneo directamente desde la tablet, sin tocar el panel del piano
✅ **Algoritmo de selección inteligente**: Sugiere automáticamente qué pieza del repertorio practicar, con decaimiento exponencial de los fallos (con metrónomo) en vez de un corte binario a 30 días
✅ **Registro de fallos**: Contabiliza errores por pieza durante la práctica, distinguiendo pasada libre y pasada con metrónomo; avisa con un popup si el nivel de la pieza sube o baja
✅ **Informes detallados**: Estadísticas por día, semana, mes y año con tablas de tiempo y fallos
✅ **Interfaz responsive**: Diseño optimizado para desktop, móvil y tablet

## Instalación

### 1. Requisitos
- Apache 2.4+ con mod_rewrite
- PHP 7.4+ con PDO MySQL
- MySQL 5.7+ o MariaDB 10.3+

### 2. Configurar el entorno LAMP

#### En Debian/Ubuntu/MX Linux:
```bash
sudo apt update
sudo apt install apache2 mysql-server php php-mysql libapache2-mod-php
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### En AlmaLinux/RHEL/CentOS:
```bash
sudo dnf install httpd mariadb-server php php-mysqlnd
sudo systemctl start httpd mariadb
sudo systemctl enable httpd mariadb
```

### 3. Copiar archivos

```bash
sudo cp -r piano_tracker /var/www/html/piano
sudo chown -R www-data:www-data /var/www/html/piano  # Debian/Ubuntu
sudo chmod -R 755 /var/www/html/piano
```

### 4. Crear la base de datos

```bash
mysql -u root -p < /var/www/html/piano/schema.sql
```

### 5. Aplicar migraciones

En orden cronológico (`schema.sql` solo trae el esquema base v1.x):

```bash
cd /var/www/html/piano
mysql -u piano_user -p piano_tracker < migracion_v1.3.sql
mysql -u piano_user -p piano_tracker < migracion_midi.sql
mysql -u piano_user -p piano_tracker < migracion_tecnica.sql
mysql -u piano_user -p piano_tracker < migracion_mantenimiento.sql
mysql -u piano_user -p piano_tracker < inicializar_tempo_objetivo.sql
mysql -u piano_user -p piano_tracker < migracion_graduacion_automatica.sql
mysql -u piano_user -p piano_tracker < migracion_tono_roland.sql   # solo si vas a usar la app Electron con Roland
```

### 6. Configurar credenciales

`config/database.php` **no está en el repositorio** (contiene la contraseña real de la BD). Copia la plantilla y edítala:

```bash
cp config/database.example.php config/database.php
```

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'piano_tracker');
define('DB_USER', 'piano_user');
define('DB_PASS', 'tu_password');
```

Para la contraseña de acceso a la app (login), genera el hash y aplícalo a mano:

```bash
php generar_hash.php 'tu_contraseña'
```

**RECOMENDACIÓN DE SEGURIDAD**: Crea un usuario MySQL específico:

```sql
CREATE USER 'piano_user'@'localhost' IDENTIFIED BY 'password_seguro';
GRANT ALL PRIVILEGES ON piano_tracker.* TO 'piano_user'@'localhost';
FLUSH PRIVILEGES;
```

### 7. Configurar Apache (opcional pero recomendado)

```apache
<VirtualHost *:443>
    ServerName piano.local
    DocumentRoot /var/www/html/piano
    <Directory /var/www/html/piano>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

> **Nota:** El soporte MIDI (Web MIDI API) requiere contexto seguro (HTTPS o localhost).

### 8. Acceder a la aplicación

Abrir el navegador en `https://tu-servidor/` o `http://localhost/piano`

---

## Uso de la aplicación

### 1. Gestionar repertorio
- Ve a **Repertorio** para añadir tus piezas
- Campos: Compositor, Título, Libro, Grado, Tempo, Tempo objetivo, Ponderación, **Tono MIDI (GM)**
- El **Tono MIDI** (0–127, General MIDI) determina el sonido que se activará en el piano durante la práctica de repertorio
- El **Tempo objetivo** es el tempo al que la pieza se gradúa a "mantenimiento" (ver más abajo)
- La **Ponderación** determina la frecuencia de práctica (1.25 = 25% más frecuente)

### 2. Mantenimiento automático de repertorio
- Cada mes, si la media de fallos con metrónomo de una pieza fue ≤ 1 y aún no llegó al tempo objetivo, el dashboard propone subir el tempo (+5 BPM) — se confirma o descarta con un clic
- Si la pieza ya está al tempo objetivo y mantiene una media de fallos ≤ 0.25 con al menos 8 días practicados durante 3 meses seguidos, pasa **sola** a "repertorio de mantenimiento" (el dashboard solo avisa de que ya ocurrió)
- Una pieza en mantenimiento vuelve a "aprendizaje" automáticamente si sus dos últimos registros de fallos tienen algún fallo
- También se puede pasar una pieza a mantenimiento a mano, con el botón "A mantenimiento" en Repertorio

### 3. Técnica por ejercicios
- Ve a **Técnica** para dar de alta ejercicios (nombre, BPM inicial, comentarios)
- En una sesión, la actividad de tipo "Técnica (ejercicios)" rota por los ejercicios menos practicados y sube o baja el BPM según valores Bien/Neutro/Mal tras cada intento
- Tras varios "Mal" seguidos en el mismo ejercicio, rota automáticamente al siguiente
- Desde **Admin** se puede resetear el BPM de todos los ejercicios a la vez

### 4. Configurar el metrónomo (Admin)
- Ve a **Admin** → sección "Configuración del metrónomo"
- Ajusta el BPM por defecto para **Técnica** y **Práctica**
- El Repertorio siempre usa el tempo de la pieza activa

### 5. Crear una sesión de práctica
- Ve a **Sesión** → Planificar nueva sesión
- Añade actividades en el orden que desees
- La aplicación sugerirá automáticamente la pieza de repertorio según el algoritmo

### 6. Usar el timer y el metrónomo
- Haz clic en **Iniciar** para comenzar el cronómetro
- El **metrónomo** se muestra en el panel inferior:
  - Ajusta BPM con los botones -5 / -1 / +1 / +5
  - Configura los pulsos por compás
  - Activa/desactiva el acento del primer pulso
  - Ajusta el volumen con el slider
- En actividades de **Repertorio**:
  - Al registrar los fallos, indica si la pasada fue **libre** o **con metrónomo** (solo la segunda cuenta para las sugerencias y la progresión de tempo)
  - Si el nivel de la pieza sube o baja de categoría con ese registro, aparece un aviso emergente
  - Puedes editar el tempo de la pieza directamente (se guarda en la BD)
  - Puedes cambiar el tono MIDI de la pieza (se guarda en la BD)
  - Si tienes el piano conectado por USB, el sonido cambiará automáticamente

### 7. Conectar el piano (MIDI)
- Conecta el piano a la tablet/ordenador con un cable **USB-C a USB-B** (o adaptador OTG)
- Selecciona el modo **MIDI** si Android lo pregunta
- En la sesión, el selector de puerto MIDI aparecerá en el widget del metrónomo
- El dispositivo seleccionado se recuerda para futuras sesiones

> **Compatibilidad MIDI:** Chrome, Brave, Vivaldi y otros navegadores Chromium. No compatible con Firefox ni DuckDuckGo.

### 8. Usar la página Piano MIDI
- Ve a **Piano MIDI** en el menú de navegación
- Selecciona el dispositivo MIDI en el desplegable de conexión
- **Instrumento:** elige la categoría (Piano, Órgano, Guitarra…) y pulsa cualquier voz para enviar el Program Change al piano al instante
- **Metrónomo:** disponible en esta misma página para usarla de forma independiente a la sesión
- **Controles:** ajusta Volumen (CC7), Reverb (CC91), Chorus (CC93) y Paneo (CC10) con los sliders; los valores se recuerdan entre visitas
- Si el acceso MIDI aparece bloqueado, pulsa el icono de candado en Chrome → MIDI → Permitir → Reintentar conexión

### 9. Ver informes
- Ve a **Informes** → selecciona el periodo
- Estadísticas de tiempo por actividad y fallos por pieza con gráficos

### 10. App de escritorio (Electron, opcional)
- En `App/` hay una versión de escritorio (Electron/Node) que comparte la misma base de datos y añade control MIDI nativo y una página `/roland` para controlar en vivo un sintetizador **Roland GO:KEYS** (tonos propios, presets, mezcla). Ver `DOCUMENTACION_TECNICA.md` para instalación y detalle.

---

## Algoritmo de selección de piezas

```
Score = SUM((10 - Fallos_día) × 0.5^(días_desde/15)) × (1 / Ponderación)
```

- Decaimiento exponencial con semivida de 15 días (no un corte binario a 30 días)
- Solo cuentan los fallos registrados con la pasada "con metrónomo"
- **Menor score = mayor prioridad**
- Piezas sin ninguna pasada con metrónomo en los últimos 180 días → prioridad máxima
- Piezas en "mantenimiento" solo se sugieren si no queda ninguna pieza en aprendizaje disponible

Detalle completo (progresión de tempo, graduación y démoción) en `DOCUMENTACION_TECNICA.md`.

---

## Estructura de archivos

```
piano_tracker/
├── config/
│   ├── database.php           # Configuración BD — NO versionado (contiene la contraseña real)
│   └── database.example.php   # Plantilla: copiar como database.php
├── includes/
│   ├── funciones.php          # Algoritmos y helpers de negocio (versionado, sin credenciales)
│   ├── header.php             # Header común
│   └── footer.php             # Footer común
├── ajax/
│   ├── timer.php               # Guardado periódico del cronómetro
│   └── sugerencias.php         # Aplicar/descartar avisos de progresión (tempo, graduación)
├── assets/
│   ├── css/
│   │   └── style.css          # Estilos globales
│   └── js/
│       └── app.js             # JavaScript auxiliar
├── database/
│   └── schema.sql             # Esquema BASE de la BD (las migraciones lo completan)
├── App/                        # App de escritorio Electron/Node (misma BD) — ver DOCUMENTACION_TECNICA.md
├── index.php                  # Dashboard principal
├── repertorio.php             # Gestión de piezas (tono MIDI GM, mantenimiento)
├── sesion.php                 # Sesiones de práctica (timer + metrónomo + MIDI)
├── tecnica.php                # CRUD de ejercicios de técnica
├── midi.php                   # Control MIDI del piano (instrumento, efectos, metrónomo)
├── informes.php               # Estadísticas e informes
├── admin.php                  # Panel de administración
├── gestionar_sesiones.php     # CRUD de sesiones manuales
├── generar_hash.php           # Genera el hash de la contraseña de login (recibe la contraseña por CLI)
├── migracion_v1.3.sql / migracion_midi.sql / migracion_tecnica.sql
├── migracion_mantenimiento.sql / inicializar_tempo_objetivo.sql / migracion_graduacion_automatica.sql
├── migracion_tono_roland.sql  # Solo relevante para App/
└── README.md                  # Este archivo
```

---

## Solución de problemas

### Error de conexión a MySQL
```bash
sudo systemctl status mysql
sudo systemctl restart mysql
```

### El MIDI no funciona
- Verificar que el navegador es Chromium-based (Chrome, Brave, Vivaldi…)
- Verificar que la app se sirve por **HTTPS** (requerido por Web MIDI API)
- En Android: asegurarse de seleccionar modo **MIDI** al conectar el cable USB
- Refrescar la página tras conectar el cable si el dispositivo no aparece

### El navegador muestra "acceso MIDI bloqueado"
- En Chrome: pulsar el icono de candado (o ⓘ) junto a la URL → buscar "MIDI" → cambiar a "Permitir"
- Después pulsar el botón **"Reintentar conexión"** que aparece en la página (no hace falta recargar)

### El metrónomo no suena
- El audio requiere interacción previa del usuario (limitación del navegador)
- Pulsar cualquier botón antes de iniciar el metrónomo

---

**Versión**: 1.9  
**Última actualización**: Agosto 2026  
**Licencia**: Uso personal
