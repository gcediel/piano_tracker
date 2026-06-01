# Piano Tracker - Aplicación de seguimiento de práctica de piano

## Características principales

✅ **Gestión de repertorio**: CRUD completo de piezas con compositor, título, libro, grado, tempo, ponderación y tono MIDI (GM)
✅ **Planificación de sesiones**: Añade actividades (calentamiento, técnica, práctica, repertorio, improvisación, composición)
✅ **Timer dinámico con AJAX**: Cronómetro que guarda progreso automáticamente cada 5 segundos
✅ **Metrónomo integrado**: Con BPM ajustable, pulsos por compás, acento en primer pulso, control de volumen y persistencia de preferencias
✅ **Soporte MIDI**: Envío automático de Program Change al piano vía Web MIDI API al cambiar de pieza en Repertorio
✅ **Algoritmo de selección inteligente**: Sugiere automáticamente qué pieza del repertorio practicar basándose en fallos ponderados de los últimos 30 días
✅ **Registro de fallos**: Contabiliza errores por pieza durante la práctica
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

```bash
# Añade la columna de tono MIDI a la tabla de piezas
mysql -u piano_user -p piano_tracker < /var/www/html/piano/migracion_midi.sql
```

### 6. Configurar credenciales

Edita el archivo `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'piano_tracker');
define('DB_USER', 'piano_user');
define('DB_PASS', 'tu_password');
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
- Campos: Compositor, Título, Libro, Grado, Tempo, Ponderación, **Tono MIDI (GM)**
- El **Tono MIDI** (0–127, General MIDI) determina el sonido que se activará en el piano durante la práctica de repertorio
- La **Ponderación** determina la frecuencia de práctica (1.25 = 25% más frecuente)

### 2. Configurar el metrónomo (Admin)
- Ve a **Admin** → sección "Configuración del metrónomo"
- Ajusta el BPM por defecto para **Técnica** y **Práctica**
- El Repertorio siempre usa el tempo de la pieza activa

### 3. Crear una sesión de práctica
- Ve a **Sesión** → Planificar nueva sesión
- Añade actividades en el orden que desees
- La aplicación sugerirá automáticamente la pieza de repertorio según el algoritmo

### 4. Usar el timer y el metrónomo
- Haz clic en **Iniciar** para comenzar el cronómetro
- El **metrónomo** se muestra en el panel inferior:
  - Ajusta BPM con los botones -5 / -1 / +1 / +5
  - Configura los pulsos por compás
  - Activa/desactiva el acento del primer pulso
  - Ajusta el volumen con el slider
- En actividades de **Repertorio**:
  - Puedes editar el tempo de la pieza directamente (se guarda en la BD)
  - Puedes cambiar el tono MIDI de la pieza (se guarda en la BD)
  - Si tienes el piano conectado por USB, el sonido cambiará automáticamente

### 5. Conectar el piano (MIDI)
- Conecta el piano a la tablet/ordenador con un cable **USB-C a USB-B** (o adaptador OTG)
- Selecciona el modo **MIDI** si Android lo pregunta
- En la sesión, el selector de puerto MIDI aparecerá en el widget del metrónomo
- El dispositivo seleccionado se recuerda para futuras sesiones

> **Compatibilidad MIDI:** Chrome, Brave, Vivaldi y otros navegadores Chromium. No compatible con Firefox ni DuckDuckGo.

### 6. Ver informes
- Ve a **Informes** → selecciona el periodo
- Estadísticas de tiempo por actividad y fallos por pieza con gráficos

---

## Algoritmo de selección de piezas

```
Score = SUM((10 - Fallos_día) × Peso_temporal) × (1 / Ponderación)
```

- Peso temporal: hace 30 días → peso 1; ayer → peso 30
- **Menor score = mayor prioridad**
- Piezas sin práctica reciente → score 0 → máxima prioridad

---

## Estructura de archivos

```
piano_tracker/
├── config/
│   └── database.php          # Configuración BD + funciones auxiliares
├── includes/
│   ├── header.php            # Header común
│   └── footer.php            # Footer común
├── assets/
│   ├── css/
│   │   └── style.css         # Estilos globales
│   └── js/
│       └── app.js            # JavaScript auxiliar
├── database/
│   └── schema.sql            # Esquema completo de la BD
├── index.php                 # Dashboard principal
├── repertorio.php            # Gestión de piezas (con tono MIDI GM)
├── sesion.php                # Sesiones de práctica (timer + metrónomo + MIDI)
├── informes.php              # Estadísticas e informes
├── admin.php                 # Panel de administración
├── gestionar_sesiones.php    # CRUD de sesiones manuales
├── schema.sql                # Esquema inicial de BD
├── migracion_v1.3.sql        # Migración v1.3
├── migracion_midi.sql        # Migración: columna programa_midi en piezas
└── README.md                 # Este archivo
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

### El metrónomo no suena
- El audio requiere interacción previa del usuario (limitación del navegador)
- Pulsar cualquier botón antes de iniciar el metrónomo

---

**Versión**: 1.6  
**Última actualización**: Junio 2026  
**Licencia**: Uso personal
