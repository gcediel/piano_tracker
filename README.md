# Piano Tracker - Aplicación de seguimiento de práctica de piano

## Características principales

✅ **Gestión de repertorio**: CRUD completo de piezas con compositor, título, libro, grado, tempo y ponderación
✅ **Planificación de sesiones**: Añade actividades (calentamiento, técnica, práctica, repertorio, improvisación, composición)
✅ **Timer dinámico con AJAX**: Cronómetro que guarda progreso automáticamente cada 5 segundos
✅ **Algoritmo de selección inteligente**: Sugiere automáticamente qué pieza del repertorio practicar basándose en fallos ponderados de los últimos 30 días
✅ **Registro de fallos**: Contabiliza errores por pieza durante la práctica
✅ **Informes detallados**: Estadísticas por día, semana, mes y año con tablas de tiempo y fallos
✅ **Interfaz responsive**: Diseño limpio y funcional que funciona en desktop y móvil

## Instalación

### 1. Requisitos
- Apache 2.4+ con mod_rewrite
- PHP 7.4+ con PDO MySQL
- MySQL 5.7+ o MariaDB 10.3+

### 2. Configurar el entorno LAMP

#### En Debian/Ubuntu/MX Linux:
```bash
# Instalar LAMP si no lo tienes
sudo apt update
sudo apt install apache2 mysql-server php php-mysql libapache2-mod-php

# Habilitar mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### En AlmaLinux/RHEL/CentOS:
```bash
# Instalar LAMP
sudo dnf install httpd mariadb-server php php-mysqlnd

# Iniciar servicios
sudo systemctl start httpd
sudo systemctl start mariadb
sudo systemctl enable httpd
sudo systemctl enable mariadb
```

### 3. Copiar archivos

```bash
# Copiar la aplicación a tu DocumentRoot
sudo cp -r piano_tracker /var/www/html/piano

# Asignar permisos correctos
sudo chown -R www-data:www-data /var/www/html/piano  # Debian/Ubuntu
# O
sudo chown -R apache:apache /var/www/html/piano      # AlmaLinux/RHEL

sudo chmod -R 755 /var/www/html/piano
```

### 4. Crear la base de datos

```bash
# Acceder a MySQL
mysql -u root -p

# Ejecutar el script SQL
mysql -u root -p < /var/www/html/piano/schema.sql

# O manualmente:
# mysql> source /var/www/html/piano/schema.sql;
```

### 5. Configurar credenciales

Edita el archivo `config/database.php` con tus credenciales:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'piano_tracker');
define('DB_USER', 'root');          // Cambia esto
define('DB_PASS', 'tu_password');   // Cambia esto
```

**RECOMENDACIÓN DE SEGURIDAD**: Crea un usuario MySQL específico para la aplicación:

```sql
CREATE USER 'piano_user'@'localhost' IDENTIFIED BY 'password_seguro';
GRANT ALL PRIVILEGES ON piano_tracker.* TO 'piano_user'@'localhost';
FLUSH PRIVILEGES;
```

### 6. Configurar Apache (opcional pero recomendado)

Crea un VirtualHost en `/etc/apache2/sites-available/piano.conf`:

```apache
<VirtualHost *:80>
    ServerName piano.local
    DocumentRoot /var/www/html/piano
    
    <Directory /var/www/html/piano>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/piano_error.log
    CustomLog ${APACHE_LOG_DIR}/piano_access.log combined
</VirtualHost>
```

```bash
# Habilitar el sitio
sudo a2ensite piano.conf
sudo systemctl reload apache2

# Añadir entrada en /etc/hosts
echo "127.0.0.1 piano.local" | sudo tee -a /etc/hosts
```

### 7. Acceder a la aplicación

- Con VirtualHost: http://piano.local
- Sin VirtualHost: http://localhost/piano

## Uso de la aplicación

### 1. Gestionar repertorio
- Ve a **Repertorio** para añadir tus piezas
- Campos obligatorios: Compositor y Título
- La **Ponderación** determina la frecuencia de práctica (1.25 = 25% más frecuente)
- Puedes desactivar piezas que no quieras practicar temporalmente

### 2. Crear una sesión de práctica
- Ve a **Sesión** → Planificar nueva sesión
- Añade actividades en el orden que desees
- Puedes añadir múltiples actividades del mismo tipo
- Para **Repertorio**, la aplicación sugerirá automáticamente la pieza según el algoritmo
- Añade notas opcionales para cada actividad (ej: "Escalas en Do Mayor, compases 12-24")

### 3. Usar el timer
- Haz clic en **Iniciar** para comenzar el cronómetro
- El tiempo se guarda automáticamente cada 5 segundos
- Para actividades de repertorio, registra los fallos cometidos
- Haz clic en **Siguiente actividad** para pasar a la siguiente
- Haz clic en **Finalizar sesión** cuando termines

### 4. Ver informes
- Ve a **Informes**
- Selecciona el periodo: día, semana, mes o año
- Verás estadísticas de tiempo por actividad y fallos por pieza
- Los informes incluyen gráficos visuales de tendencias

## Algoritmo de selección de piezas

El sistema calcula un **score** para cada pieza:

```
score = (media_fallos_últimos_30_días) × ponderación
```

- **Media de fallos**: Total de fallos / 30 días (incluye días sin práctica como 0)
- **Ponderación**: Factor multiplicador (mayor valor = más prioridad)
- La pieza con **menor score** es la sugerida

**Ejemplo**:
- Pieza A: 60 fallos en 30 días, ponderación 1.00 → score = 2.0 × 1.00 = 2.0
- Pieza B: 30 fallos en 30 días, ponderación 1.50 → score = 1.0 × 1.50 = 1.5 ✓ **Seleccionada**

## Estructura de archivos

```
piano_tracker/
├── config/
│   └── database.php          # Configuración de BD y funciones auxiliares
├── includes/
│   ├── header.php            # Header común
│   └── footer.php            # Footer común
├── ajax/
│   └── timer.php             # Endpoint AJAX para timer
├── assets/
│   ├── css/
│   │   └── style.css         # Estilos
│   └── js/
│       └── app.js            # JavaScript principal
├── index.php                 # Dashboard principal
├── repertorio.php            # Gestión de piezas
├── sesion.php                # Planificación y timer
├── informes.php              # Estadísticas e informes
├── schema.sql                # Esquema de base de datos
└── README.md                 # Este archivo
```

## Próximas funcionalidades sugeridas

- [ ] Exportar informes a PDF/Excel
- [ ] Gráficos interactivos con Chart.js
- [ ] Etiquetas/categorías para piezas
- [ ] Objetivo de práctica semanal
- [ ] Notificaciones de recordatorio
- [ ] Modo oscuro
- [ ] API REST para integración con apps móviles
- [ ] Backup automático de base de datos

## Solución de problemas

### Error de conexión a MySQL
```bash
# Verificar que MySQL está corriendo
sudo systemctl status mysql  # Debian/Ubuntu
sudo systemctl status mariadb # AlmaLinux

# Reiniciar si es necesario
sudo systemctl restart mysql
```

### Error de permisos
```bash
# Verificar permisos
ls -la /var/www/html/piano

# Corregir si es necesario
sudo chown -R www-data:www-data /var/www/html/piano
sudo chmod -R 755 /var/www/html/piano
```

### El timer no guarda
- Verificar que `/piano/ajax/timer.php` es accesible
- Revisar los logs de Apache: `sudo tail -f /var/log/apache2/error.log`
- Verificar que JavaScript está habilitado en el navegador

### No se muestran los CSS
- Verificar rutas en `includes/header.php`
- Asegurarse de que Apache permite `.htaccess` (AllowOverride All)

## Contacto y soporte

Para consultas o mejoras, puedes modificar libremente este código según tus necesidades.

---

**Versión**: 1.0.0  
**Fecha**: Enero 2026  
**Licencia**: Uso personal
