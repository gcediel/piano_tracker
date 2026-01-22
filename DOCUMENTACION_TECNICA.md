# Piano Tracker - Documentación Técnica v1.0

**Aplicación web para gestión de práctica de piano**  
**Autor:** Guillermo  
**Fecha:** 22 Enero 2025  
**Versión:** 1.0  
**Stack:** PHP 8.x + MySQL 8.x + Vanilla JavaScript

---

## 📋 Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Requisitos del Sistema](#requisitos-del-sistema)
3. [Instalación](#instalación)
4. [Estructura de Archivos](#estructura-de-archivos)
5. [Base de Datos](#base-de-datos)
6. [Funcionalidades por Módulo](#funcionalidades-por-módulo)
7. [Algoritmos Clave](#algoritmos-clave)
8. [Guía de Desarrollo](#guía-de-desarrollo)
9. [API de Funciones](#api-de-funciones)

---

## 📖 Descripción General

Piano Tracker es una aplicación web para pianistas que permite:
- Gestionar un repertorio de piezas musicales
- Registrar sesiones de práctica con cronómetro
- Llevar seguimiento de errores/fallos por pieza
- Obtener sugerencias inteligentes de piezas a practicar
- Visualizar estadísticas y tendencias de práctica
- Generar informes detallados

### Características principales

- **Gestión de repertorio:** Alta, edición y eliminación de piezas con metadatos (compositor, título, grado, tempo, ponderación)
- **Sesiones de práctica:** Sistema de actividades con cronómetro integrado
- **Seguimiento de fallos:** Registro de errores por pieza con cálculo de medias
- **Algoritmo de sugerencia:** Sistema inteligente que prioriza piezas según fallos recientes y ponderación
- **Informes visuales:** Estadísticas con DataTables, gráficos y análisis temporal
- **Gestión administrativa:** Creación manual de sesiones históricas

---

## 💻 Requisitos del Sistema

### Servidor
- **PHP:** 8.0 o superior
- **MySQL:** 8.0 o superior (o MariaDB 10.5+)
- **Apache/Nginx:** Servidor web con mod_rewrite
- **Extensiones PHP requeridas:**
  - PDO
  - pdo_mysql
  - mbstring
  - json

### Cliente
- Navegador moderno (Chrome 90+, Firefox 88+, Safari 14+, Edge 90+)
- JavaScript habilitado

---

## 🚀 Instalación

### 1. Preparar el servidor

```bash
# Clonar archivos al servidor web
cd /var/www/html
git clone [repositorio] piano_tracker
cd piano_tracker
```

### 2. Configurar base de datos

```bash
# Crear base de datos
mysql -u root -p
```

```sql
CREATE DATABASE piano_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'piano_user'@'localhost' IDENTIFIED BY 'tu_contraseña_segura';
GRANT ALL PRIVILEGES ON piano_tracker.* TO 'piano_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Importar esquema
mysql -u piano_user -p piano_tracker < database/schema.sql
```

### 3. Configurar conexión

Editar `config/database.php`:

```php
$host = 'localhost';
$dbname = 'piano_tracker';
$username = 'piano_user';
$password = 'tu_contraseña_segura';
```

### 4. Permisos

```bash
chmod 755 -R /var/www/html/piano_tracker
chown www-data:www-data -R /var/www/html/piano_tracker
```

### 5. Acceder

Abrir navegador: `http://tu-servidor/piano_tracker/`

---

## 📁 Estructura de Archivos

```
piano_tracker/
├── config/
│   └── database.php           # Conexión DB + funciones auxiliares
├── includes/
│   ├── header.php            # Cabecera HTML + navegación
│   └── footer.php            # Pie de página HTML
├── assets/
│   └── css/
│       └── style.css         # Estilos globales
├── database/
│   └── schema.sql            # Esquema completo de la base de datos
├── index.php                 # Página de inicio (dashboard)
├── repertorio.php            # Gestión de piezas del repertorio
├── sesion.php                # Sesiones de práctica
├── informes.php              # Estadísticas y reportes
├── admin.php                 # Panel de administración
├── gestionar_sesiones.php    # CRUD de sesiones manuales
└── DOCUMENTACION_TECNICA.md  # Este archivo
```

---

## 🗄️ Base de Datos

### Esquema de tablas

#### Tabla: `piezas`
Almacena el repertorio de piezas musicales.

```sql
CREATE TABLE piezas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compositor VARCHAR(255) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    libro VARCHAR(255),
    grado INT,
    tempo INT,
    ponderacion DECIMAL(4,2) DEFAULT 1.00,
    instrumento VARCHAR(100) DEFAULT 'Piano',
    activa BOOLEAN DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Campos importantes:**
- `ponderacion`: Factor de importancia (1.0-2.0). Piezas con mayor ponderación tienen más prioridad en el algoritmo de sugerencia.
- `activa`: Booleano para ocultar/mostrar piezas sin eliminarlas.

#### Tabla: `sesiones`
Registra las sesiones de práctica.

```sql
CREATE TABLE sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    estado ENUM('planificada', 'en_curso', 'finalizada') DEFAULT 'planificada',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Estados:**
- `planificada`: Sesión creada pero no iniciada
- `en_curso`: Sesión activa con actividades pendientes
- `finalizada`: Todas las actividades completadas

#### Tabla: `actividades`
Actividades dentro de cada sesión.

```sql
CREATE TABLE actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id INT NOT NULL,
    orden INT NOT NULL,
    tipo ENUM('calentamiento', 'practica', 'tecnica', 'repertorio', 
              'improvisacion', 'composicion') NOT NULL,
    pieza_id INT,
    tiempo_segundos INT DEFAULT 0,
    notas TEXT,
    estado ENUM('pendiente', 'en_curso', 'completada') DEFAULT 'pendiente',
    fecha_inicio TIMESTAMP NULL,
    fecha_fin TIMESTAMP NULL,
    FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE CASCADE,
    FOREIGN KEY (pieza_id) REFERENCES piezas(id) ON DELETE SET NULL
);
```

**Tipos de actividades:**
- `calentamiento`: Ejercicios de calentamiento
- `tecnica`: Ejercicios técnicos (escalas, arpegios)
- `practica`: Práctica general
- `repertorio`: Piezas del repertorio (requiere `pieza_id`)
- `improvisacion`: Improvisación libre
- `composicion`: Composición

#### Tabla: `fallos`
Registro de errores/fallos por pieza.

```sql
CREATE TABLE fallos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    pieza_id INT NOT NULL,
    cantidad INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (pieza_id) REFERENCES piezas(id) ON DELETE CASCADE
);
```

**Uso:**
- Cada vez que se completa una actividad de repertorio, se registra el número de fallos cometidos.
- La `fecha_registro` se usa para cálculos temporales (últimos 30 días).

### Relaciones

```
sesiones (1) ──→ (N) actividades
piezas (1) ──→ (N) actividades (cuando tipo='repertorio')
piezas (1) ──→ (N) fallos
actividades (1) ──→ (N) fallos
```

---

## ⚙️ Funcionalidades por Módulo

### 1. Inicio (`index.php`)

**Propósito:** Dashboard principal con resumen de actividad.

**Funcionalidades:**
- Muestra tiempo practicado hoy, este mes
- Número de piezas activas en repertorio
- Racha actual y más larga de práctica consecutiva
- Porcentaje de días practicados (semana, mes, año)
- Últimas 5 sesiones con media de fallos del repertorio
- Enlace rápido a sesión en curso (si existe)
- Auto-corrección de sesiones (marca como finalizadas las que tienen todas las actividades completadas)

**Consultas SQL principales:**
```sql
-- Auto-corrección de sesiones
UPDATE sesiones s 
SET s.estado = 'finalizada' 
WHERE s.estado IN ('planificada', 'en_curso')
AND NOT EXISTS (
    SELECT 1 FROM actividades a 
    WHERE a.sesion_id = s.id 
    AND a.estado IN ('pendiente', 'en_curso')
)

-- Últimas sesiones con media de fallos
SELECT s.*, 
    (SELECT SUM(tiempo_segundos) FROM actividades WHERE sesion_id = s.id) as tiempo_total,
    (SELECT ROUND(AVG(f.cantidad), 2)
     FROM fallos f 
     JOIN actividades a ON f.actividad_id = a.id 
     WHERE a.sesion_id = s.id 
     AND a.tipo = 'repertorio') as media_fallos_repertorio
FROM sesiones s 
ORDER BY fecha DESC, id DESC 
LIMIT 5
```

---

### 2. Repertorio (`repertorio.php`)

**Propósito:** Gestión completa del repertorio de piezas.

**Funcionalidades:**
- **CRUD de piezas:**
  - Crear nueva pieza con metadatos
  - Editar pieza existente
  - Desactivar pieza (ocultar sin eliminar)
  - Eliminar pieza (solo si no tiene registros de práctica)
- **Estadísticas por pieza (últimos 30 días):**
  - Días practicados (días distintos)
  - Media de fallos por día (total fallos / días practicados)
  - Estado codificado por color según media
- **DataTables:** Búsqueda, ordenamiento, paginación

**Cálculo de media de fallos:**
```sql
SELECT 
    p.*,
    COUNT(DISTINCT DATE(f.fecha_registro)) as dias_practicados_30d,
    SUM(f.cantidad) as total_fallos_30d,
    ROUND(
        SUM(f.cantidad) / NULLIF(COUNT(DISTINCT DATE(f.fecha_registro)), 0),
    2) as media_fallos_dia
FROM piezas p
LEFT JOIN fallos f ON p.id = f.pieza_id
WHERE f.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY p.id
```

**Códigos de color por media:**
- 🟢 Verde (< 0.5): Perfección
- 🔵 Azul (0.5-1.5): Excelente
- 🟡 Amarillo (1.5-2.5): Muy bien
- 🟠 Naranja (2.5-3.5): Bien
- 🟣 Morado (3.5-5): Mejorable
- 🔴 Rojo (> 5): Atención

---

### 3. Sesión (`sesion.php`)

**Propósito:** Gestión de sesiones de práctica con cronómetro en tiempo real.

**Funcionalidades:**

#### 3.1 Crear sesión
- **Modo 1:** Planificar sesión para más tarde
- **Modo 2:** Iniciar sesión inmediatamente
- Configurar actividades (tipos + duración estimada)
- Para actividades de repertorio: sistema de sugerencia automática

#### 3.2 Ejecutar sesión
- **Cronómetro en tiempo real** con JavaScript + AJAX
- Control de inicio/pausa/reanudar/completar por actividad
- Registro de fallos al completar actividad de repertorio
- Añadir notas por actividad
- Barra de progreso visual

#### 3.3 Ver detalles de sesión
- Resumen completo de sesión finalizada
- Lista de actividades con tiempos y fallos
- Estadísticas agregadas

**Flujo de ejecución:**
```
1. Usuario crea sesión → estado='planificada'
2. Usuario inicia sesión → estado='en_curso'
3. Usuario inicia actividad → actividad.estado='en_curso', fecha_inicio=NOW()
4. Cronómetro corre (actualización cada segundo vía AJAX)
5. Usuario completa actividad → actividad.estado='completada', fecha_fin=NOW()
6. Si tipo='repertorio': registrar fallos en tabla fallos
7. Cuando todas actividades completadas → sesion.estado='finalizada'
```

**AJAX para cronómetro:**
```javascript
// sesion.php contiene código JavaScript
setInterval(function() {
    fetch('sesion.php?accion=actualizar_tiempo&actividad_id=' + actividadId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('tiempo').textContent = formatearTiempo(data.tiempo);
        });
}, 1000);
```

**Limpieza de sesiones programadas:**
Al crear nueva sesión, elimina sesiones programadas pendientes del día:
```sql
DELETE FROM sesiones 
WHERE estado = 'planificada' 
AND fecha = CURDATE()
```

---

### 4. Informes (`informes.php`)

**Propósito:** Análisis estadístico de la práctica.

**Funcionalidades:**
- **Filtros de periodo:** Día, semana, mes, año
- **Tiempo por actividad:** Gráfico de distribución
- **Práctica de piezas del repertorio:**
  - Tabla con días practicados y media de fallos
  - DataTables con búsqueda
- **Práctica diaria:**
  - Tabla con tiempo por tipo de actividad por día
  - Columna de media de fallos del repertorio
  - DataTables

**Consulta de práctica diaria:**
```sql
SELECT 
    s.fecha,
    s.id as sesion_id,
    SUM(a.tiempo_segundos) as tiempo_total,
    SUM(CASE WHEN a.tipo = 'calentamiento' THEN a.tiempo_segundos ELSE 0 END) as tiempo_calentamiento,
    SUM(CASE WHEN a.tipo = 'tecnica' THEN a.tiempo_segundos ELSE 0 END) as tiempo_tecnica,
    SUM(CASE WHEN a.tipo = 'practica' THEN a.tiempo_segundos ELSE 0 END) as tiempo_practica,
    SUM(CASE WHEN a.tipo = 'repertorio' THEN a.tiempo_segundos ELSE 0 END) as tiempo_repertorio,
    SUM(CASE WHEN a.tipo = 'improvisacion' THEN a.tiempo_segundos ELSE 0 END) as tiempo_improvisacion,
    SUM(CASE WHEN a.tipo = 'composicion' THEN a.tiempo_segundos ELSE 0 END) as tiempo_composicion,
    (SELECT ROUND(AVG(f.cantidad), 2)
     FROM fallos f 
     JOIN actividades a2 ON f.actividad_id = a2.id 
     WHERE a2.sesion_id = s.id 
     AND a2.tipo = 'repertorio') as media_fallos_repertorio
FROM sesiones s
LEFT JOIN actividades a ON s.id = a.sesion_id
WHERE s.fecha BETWEEN :fecha_inicio AND :fecha_fin
GROUP BY s.fecha, s.id
ORDER BY s.fecha
```

---

### 5. Admin (`admin.php`)

**Propósito:** Panel de administración.

**Funcionalidades:**
- Enlace a gestión de sesiones manuales
- Exportación de datos (CSV/JSON)
- Importación de datos

---

### 6. Gestionar Sesiones (`gestionar_sesiones.php`)

**Propósito:** CRUD manual de sesiones históricas.

**Funcionalidades:**
- **Crear sesión manual:** Útil para registrar sesiones pasadas
  - Especificar fecha exacta
  - Añadir múltiples actividades
  - Para actividades de repertorio: seleccionar pieza y registrar fallos
  - Especificar tiempo de cada actividad
- **Editar sesión:** Modificar actividades y tiempos
- **Eliminar sesión:** Borra sesión completa con todas sus actividades
- **Tabla con DataTables:** Búsqueda y ordenamiento de sesiones

**Importante:** Las sesiones creadas manualmente se marcan automáticamente como `estado='finalizada'`.

---

## 🧮 Algoritmos Clave

### Algoritmo de Sugerencia de Piezas

**Ubicación:** `config/database.php` → función `obtenerPiezaSugerida()`

**Propósito:** Determinar qué pieza del repertorio debe practicarse a continuación.

**Fórmula:**
```
Score = SUM((10 - Fallos_día_i) × Peso_día_i) × (1 / Ponderación)

Donde:
- Fallos_día_i = cantidad de fallos en el día i
- Peso_día_i = peso temporal lineal (1 a 30)
  - Hace 30 días → peso 1
  - Hace 1 día → peso 30
- Ponderación = importancia de la pieza (1.0 - 2.0)
```

**Inversión de fallos:**
```
Puntos = MAX(0, 10 - Fallos)

0 fallos → 10 puntos (perfecto)
1 fallo → 9 puntos
...
10+ fallos → 0 puntos (malo)
```

**Ordenamiento:**
- **MENOR score = MAYOR prioridad** (se sugiere primero)

**Lógica:**
- Piezas con **muchos fallos recientes** → score bajo → **alta prioridad**
- Piezas **bien tocadas** → score alto → baja prioridad
- Piezas **importantes** (alta ponderación) → score reducido → más prioridad
- Piezas **sin práctica reciente** → score 0 → **máxima prioridad**

**Implementación SQL:**
```sql
SELECT 
    SUM(
        GREATEST(0, 10 - f.cantidad) * 
        (31 - DATEDIFF(CURDATE(), DATE(f.fecha_registro)))
    ) as suma_ponderada
FROM fallos f
WHERE f.pieza_id = :pieza_id 
  AND f.fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND DATEDIFF(CURDATE(), DATE(f.fecha_registro)) < 30
```

**Ejemplo práctico:**

```
Pieza A: "Un poco de blues" (ponderación 1.0)
- Ayer: 4 fallos → (10-4) × 30 = 180
- Hace 3 días: 4 fallos → (10-4) × 28 = 168
- Hace 5 días: 3 fallos → (10-3) × 26 = 182
- Suma: 530
- Score: 530 × (1/1.0) = 530

Pieza B: "Preludio en Do" (ponderación 1.5)
- Hace 15 días: 2 fallos → (10-2) × 16 = 128
- Suma: 128
- Score: 128 × (1/1.5) = 85.33

Pieza C: "Invención 1" (ponderación 1.25)
- Sin práctica reciente
- Suma: 0
- Score: 0

Orden de sugerencia:
1. Invención 1 (score: 0) ← Se sugiere primero
2. Preludio en Do (score: 85.33)
3. Un poco de blues (score: 530) ← Practicada recientemente, no se sugiere
```

---

## 🛠️ Guía de Desarrollo

### Añadir nueva página/módulo

1. **Crear archivo PHP:**
```php
<?php
require_once 'config/database.php';
$pageTitle = 'Mi Nuevo Módulo - Piano Tracker';
$db = getDB();

// Tu lógica aquí

include 'includes/header.php';
?>

<!-- Tu HTML aquí -->

<?php include 'includes/footer.php'; ?>
```

2. **Añadir enlace en navegación:**
Editar `includes/header.php`:
```php
<a href="mi_modulo.php">Mi Módulo</a>
```

### Añadir nueva tabla a la base de datos

1. **Crear migración SQL:**
```sql
-- database/migrations/002_nueva_tabla.sql
CREATE TABLE mi_tabla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campo VARCHAR(255),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

2. **Aplicar migración:**
```bash
mysql -u piano_user -p piano_tracker < database/migrations/002_nueva_tabla.sql
```

3. **Actualizar `database/schema.sql`** con la nueva tabla.

### Añadir DataTables a una tabla

```html
<!-- 1. Incluir CSS en <head> -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

<!-- 2. Crear tabla con id único -->
<table id="miTabla" class="display" style="width:100%">
    <thead>
        <tr>
            <th>Columna 1</th>
            <th>Columna 2</th>
        </tr>
    </thead>
    <tbody>
        <!-- PHP loop aquí -->
    </tbody>
</table>

<!-- 3. Incluir JS antes de </body> -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#miTabla').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "pageLength": 10,
        "order": [[0, "asc"]]
    });
});
</script>
```

### Modificar el algoritmo de sugerencia

Editar `config/database.php`, función `obtenerPiezaSugerida()`.

**Ejemplo - Cambiar peso temporal a exponencial:**
```php
// Línea actual (peso lineal):
SUM(GREATEST(0, 10 - f.cantidad) * (31 - DATEDIFF(CURDATE(), DATE(f.fecha_registro))))

// Cambiar a peso exponencial:
SUM(GREATEST(0, 10 - f.cantidad) * EXP(-0.1 * DATEDIFF(CURDATE(), DATE(f.fecha_registro))))
```

**Ejemplo - Cambiar fórmula de score:**
```php
// Línea actual:
$score = $sumaPonderada * (1.0 / max($pieza['ponderacion'], 0.1));

// Cambiar a multiplicar (en vez de dividir):
$score = $sumaPonderada * $pieza['ponderacion'];

// No olvides invertir el ordenamiento si cambias esto
```

### Añadir nuevo tipo de actividad

1. **Modificar enum en base de datos:**
```sql
ALTER TABLE actividades 
MODIFY tipo ENUM('calentamiento', 'practica', 'tecnica', 'repertorio', 
                 'improvisacion', 'composicion', 'mi_nuevo_tipo') NOT NULL;
```

2. **Añadir opción en `sesion.php`:**
```html
<option value="mi_nuevo_tipo">Mi Nuevo Tipo</option>
```

3. **Añadir caso en consultas de `informes.php`:**
```sql
SUM(CASE WHEN a.tipo = 'mi_nuevo_tipo' THEN a.tiempo_segundos ELSE 0 END) as tiempo_mi_nuevo_tipo
```

---

## 📚 API de Funciones

### Funciones globales (`config/database.php`)

#### `getDB()`
```php
/**
 * Obtiene conexión PDO a la base de datos
 * @return PDO Objeto de conexión
 */
function getDB(): PDO
```

#### `formatearTiempo($segundos)`
```php
/**
 * Convierte segundos a formato legible
 * @param int $segundos Tiempo en segundos
 * @return string Formato "Xh Ym" o "Ym" o "Xs"
 * 
 * Ejemplos:
 * 3661 → "1h 1m"
 * 125 → "2m"
 * 45 → "45s"
 */
function formatearTiempo(int $segundos): string
```

#### `obtenerPiezaSugerida($db, $piezasYaSeleccionadas)`
```php
/**
 * Obtiene la siguiente pieza a practicar según algoritmo
 * @param PDO $db Conexión a base de datos
 * @param array $piezasYaSeleccionadas IDs de piezas ya seleccionadas
 * @return array|null Datos de la pieza sugerida o null
 */
function obtenerPiezaSugerida(PDO $db, array $piezasYaSeleccionadas = []): ?array
```

### Acciones AJAX (`sesion.php`)

#### Iniciar actividad
```
GET sesion.php?accion=iniciar&actividad_id=X
Retorna: JSON { success: true/false }
```

#### Pausar actividad
```
GET sesion.php?accion=pausar&actividad_id=X
Retorna: JSON { success: true/false }
```

#### Reanudar actividad
```
GET sesion.php?accion=reanudar&actividad_id=X
Retorna: JSON { success: true/false }
```

#### Completar actividad
```
POST sesion.php?accion=completar&actividad_id=X
Body: { fallos: N, notas: "..." }
Retorna: JSON { success: true/false }
```

#### Actualizar tiempo
```
GET sesion.php?accion=actualizar_tiempo&actividad_id=X
Retorna: JSON { tiempo: segundos }
```

---

## 🔒 Seguridad

### Medidas implementadas

1. **Prepared Statements:** Todas las consultas SQL usan PDO con placeholders
2. **Validación de entrada:** Sanitización con `htmlspecialchars()` en output
3. **CSRF:** Considerar añadir tokens en formularios (pendiente)
4. **SQL Injection:** Protegido vía PDO prepared statements
5. **XSS:** Output escapado con `htmlspecialchars()`

### Recomendaciones adicionales

```php
// Añadir tokens CSRF a formularios
session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// En formulario:
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

// En procesamiento:
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Invalid CSRF token');
}
```

---

## 📊 Optimización

### Índices recomendados

```sql
-- Ya existen índices en PRIMARY KEY y FOREIGN KEY
-- Añadir estos para mejorar rendimiento:

CREATE INDEX idx_fallos_fecha ON fallos(fecha_registro);
CREATE INDEX idx_sesiones_fecha ON sesiones(fecha);
CREATE INDEX idx_actividades_sesion ON actividades(sesion_id);
CREATE INDEX idx_piezas_activa ON piezas(activa);
```

### Caché de consultas frecuentes

Para instancias con muchos datos, considerar:
- Cachear estadísticas del dashboard en archivo JSON
- Regenerar caché cada hora vía cron
- Leer de caché en vez de base de datos

---

## 🐛 Troubleshooting

### Error: "Connection refused"
**Causa:** MySQL no accesible  
**Solución:**
```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

### Error: "Access denied for user"
**Causa:** Credenciales incorrectas  
**Solución:** Verificar `config/database.php` y permisos de usuario MySQL

### Cronómetro no funciona
**Causa:** JavaScript deshabilitado o error en consola  
**Solución:** Verificar consola del navegador (F12)

### DataTables no se muestran
**Causa:** jQuery o DataTables no cargados  
**Solución:** Verificar conexión a CDN en herramientas de red del navegador

---

## 📞 Soporte y Contribución

### Reportar bugs
Crear issue en GitHub con:
- Descripción del problema
- Pasos para reproducir
- Logs de error (PHP y navegador)
- Versión de PHP y MySQL

### Contribuir
1. Fork del repositorio
2. Crear rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Añadir nueva funcionalidad'`)
4. Push a la rama (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

---

## 📄 Licencia

[Especificar licencia aquí]

---

## 🙏 Créditos

**Desarrollador:** Guillermo  
**Stack:** PHP, MySQL, JavaScript, DataTables, jQuery  
**Fecha de creación:** Enero 2025  

---

**Piano Tracker v1.0 - Documentación Técnica Completa**
