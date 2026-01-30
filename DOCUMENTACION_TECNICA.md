# Piano Tracker - Documentación Técnica v1.9.0

**Aplicación web para gestión de práctica de piano**  
**Autor:** Guillermo  
**Fecha:** 30 Enero 2025  
**Versión:** 1.9.0  
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
- **Sesiones de práctica:** Sistema de actividades con cronómetro integrado y auto-finalización
- **Seguimiento de fallos:** Registro de errores por pieza con cálculo de medias
- **Algoritmo de sugerencia:** Sistema inteligente que prioriza piezas según fallos recientes y ponderación
- **Dashboard unificado:** Estadísticas completas de tiempo, días practicados y rachas
- **Informes mensuales:** Tablas detalladas con gráficos de tarta de distribución de actividades y rendimiento
- **Informes anuales:** Vista completa de 12 meses con análisis comparativo y gráficos visuales
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
├── ajax/
│   └── timer.php             # Handler AJAX para cronómetro
├── assets/
│   └── css/
│       └── style.css         # Estilos globales
├── database/
│   └── schema.sql            # Esquema completo de la base de datos
├── index.php                 # Página de inicio (dashboard)
├── repertorio.php            # Gestión de piezas del repertorio
├── sesion.php                # Sesiones de práctica
├── informes.php              # Estadísticas y reportes
├── informe_mensual.php       # Informe mensual detallado (PDF)
├── informe_anual.php         # Informe anual detallado (PDF)
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

**Propósito:** Dashboard principal unificado con estadísticas completas de práctica.

**Funcionalidades:**
- **Estadísticas de Tiempo:**
  - Tiempo practicado: hoy, esta semana, este mes, este año
- **Estadísticas de Días:**
  - Días practicados con porcentajes: semana (X/Y - Z%), mes (X/Y - Z%), año (X días - Z%)
  - Número de piezas activas en repertorio
- **Rachas de Práctica:**
  - Racha actual: excluye día actual si no hay actividad registrada
  - Racha más larga histórica
- **Últimas 5 sesiones:** Con tiempo total y media de fallos del repertorio
- **Enlace rápido:** A sesión en curso si existe
- **Auto-corrección:** Marca como finalizadas las sesiones con todas las actividades completadas

**Lógica de racha mejorada:**
```php
// Verificar si hay actividad hoy
$stmt = $db->query("SELECT COUNT(*) as count FROM sesiones WHERE fecha = CURDATE()");
$hayActividadHoy = $stmt->fetch()['count'] > 0;

// Si no hay actividad hoy, empezar desde ayer
$fechaCheck = clone $hoy;
if (!$hayActividadHoy) {
    $fechaCheck->modify('-1 day');
}
```

**Consultas SQL principales:**
```sql
-- Tiempo esta semana
SELECT SUM(tiempo_segundos) as total FROM actividades a 
JOIN sesiones s ON a.sesion_id = s.id 
WHERE YEARWEEK(s.fecha, 1) = YEARWEEK(CURDATE(), 1)

-- Tiempo este año
SELECT SUM(tiempo_segundos) as total FROM actividades a 
JOIN sesiones s ON a.sesion_id = s.id 
WHERE YEAR(s.fecha) = YEAR(CURDATE())

-- Auto-corrección de sesiones
UPDATE sesiones s 
SET s.estado = 'finalizada' 
WHERE s.estado IN ('planificada', 'en_curso')
AND NOT EXISTS (
    SELECT 1 FROM actividades a 
    WHERE a.sesion_id = s.id 
    AND a.estado IN ('pendiente', 'en_curso')
)
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

**Códigos de color por media (adaptado para daltonismo):**
- 🔵 Azul oscuro (#2E5F8A) - 0 fallos o < 0.5: Perfección
- 🔵 Azul medio (#4A7BA7) - 0.5-1.5: Excelente  
- 🔵 Azul claro (#A3C1DA) - 1.5-2.5: Muy bien
- 🟢 Verde amarillento (#D4E89E) - 2.5-3.5: Bien
- ⚫ Gris (#9B9B9B) - 3.5-5: Mejorable
- 🔴 Rojo (#E57373) - > 5: Atención

**Nota:** Sistema de 6 niveles diseñado para ser distinguible por personas con daltonismo. Los colores se aplican como fondo de celda con texto blanco o negro según el contraste necesario.

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
- **Auto-finalización inteligente:**
  - Al completar última actividad con "Terminar Repertorio"
  - Al salir de la página (beforeunload event)

#### 3.3 Ver detalles de sesión
- Resumen completo de sesión finalizada
- Lista de actividades con tiempos y fallos
- Estadísticas agregadas

#### 3.4 Auto-finalización de sesiones

**Sistema de auto-finalización (v1.9.0):**

1. **Al terminar repertorio como última actividad:**
```php
// ajax/timer.php - caso 'terminar_repertorio'
$stmt = $db->prepare("SELECT id FROM actividades 
                      WHERE sesion_id = :sesion_id 
                      AND estado = 'pendiente' 
                      ORDER BY orden LIMIT 1");
$siguiente = $stmt->fetch();

if (!$siguiente) {
    // No hay más actividades pendientes - finalizar sesión
    $stmt = $db->prepare("UPDATE sesiones SET estado = 'finalizada' WHERE id = :id");
    $stmt->execute([':id' => $sesionId]);
}
```

2. **Al salir de la página (Ctrl+W, cerrar pestaña, navegar):**
```javascript
// sesion.php - beforeunload listener
window.addEventListener('beforeunload', function(e) {
    if (!sesionId) return;
    
    // Usar XHR síncrono para garantizar envío antes de cerrar
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/piano/ajax/timer.php', false);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.send(JSON.stringify({
        accion: 'auto_finalizar',
        sesion_id: sesionId,
        actividad_id: actividadId,
        tiempo: tiempoActual
    }));
});
```

3. **Handler AJAX auto_finalizar:**
```php
// ajax/timer.php
case 'auto_finalizar':
    // Guardar tiempo de actividad actual
    UPDATE actividades SET tiempo_segundos = :tiempo WHERE id = :id
    
    // Completar actividad en curso
    UPDATE actividades SET estado = 'completada', fecha_fin = NOW() 
    WHERE id = :id AND estado = 'en_curso'
    
    // Completar todas las pendientes
    UPDATE actividades SET estado = 'completada' 
    WHERE sesion_id = :sesion_id AND estado = 'pendiente'
    
    // Finalizar sesión
    UPDATE sesiones SET estado = 'finalizada' WHERE id = :id
```

**Beneficios:**
- ✅ No quedan sesiones "en_curso" huérfanas
- ✅ El tiempo se guarda correctamente
- ✅ Mejor experiencia de usuario (no hace falta finalizar manualmente)

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

**Propósito:** Página principal de análisis estadístico con enlaces a informes detallados.

**Funcionalidades:**
- **Filtros de periodo:** Día, semana, mes, año
- **Tiempo por actividad:** Gráfico de distribución
- **Práctica de piezas del repertorio:** Tabla con días practicados y media de fallos
- **Práctica diaria:** Tabla con tiempo por tipo de actividad por día
- **Enlaces a informes detallados:**
  - 📄 Informes mensuales detallados (con gráficos)
  - 📅 Informes anuales detallados (con gráficos)

---

### 5. Informe Mensual Detallado (`informe_mensual.php`)

**Propósito:** Informe completo mensual con visualizaciones gráficas para impresión/PDF.

**Funcionalidades:**

#### 5.1 Selector de periodo
- Selector de mes y año (grande y legible)
- Botón "Generar informe"
- Botón "Imprimir / Guardar PDF"
- Botón "Volver a Informes"

#### 5.2 Tabla 1: Tiempo de práctica por tipo de actividad
- Columnas: Actividad | Días 1-31 | Días practicados | Total | %
- Formato horizontal con scroll
- Colores de fondo para facilitar lectura
- Tiempo en formato breve: "H:MM" o "M'"

#### 5.3 Gráfico 1: Distribución de Tiempo por Actividad
- **Gráfico de tarta (donut)** 300x300px
- Colores distintivos por actividad:
  - 🔴 Calentamiento: #FF6B6B
  - 🟢 Técnica: #4ECDC4
  - 🔵 Práctica: #45B7D1
  - 🟠 Repertorio: #FFA07A
  - 🟣 Improvisación: #98D8C8
  - 🟡 Composición: #C7CEEA
- Leyenda con tiempo y porcentaje
- Total en el centro del donut

#### 5.4 Tabla 2: Piezas de Repertorio
- Columnas: Libro | Gr | Compositor | Nombre | Tempo | Instr | **Pond** | Días 1-31 | Días | Media
- Columna **Ponderación** añadida
- Celdas con código de colores según media de fallos
- Color de fila según media mensual
- Solo muestra piezas practicadas en el mes

#### 5.5 Gráfico 2: Distribución de Piezas por Rendimiento
- **Gráfico de tarta (donut)** 300x300px
- 6 categorías de rendimiento con paleta para daltonismo:
  - 🔵 Excelente (< 0.5): #2E5F8A
  - 🔷 Muy bien (0.5-1.5): #4A7BA7
  - 🔹 Bien (1.5-2.5): #A3C1DA
  - 🟢 Aceptable (2.5-3.5): #D4E89E
  - ⚫ Mejorable (3.5-5): #9B9B9B
  - 🔴 Atención (> 5): #E57373
- Leyenda con cantidad y porcentaje
- Total de piezas en el centro

**Formato de salida:**
- Orientación: Landscape (apaisada)
- Optimizado para PDF con `print-color-adjust: exact`
- Usuario debe activar "Gráficos de fondo" en opciones de impresión

---

### 6. Informe Anual Detallado (`informe_anual.php`)

**Propósito:** Informe completo anual con análisis de 12 meses para impresión/PDF.

**Funcionalidades:**

#### 6.1 Selector de periodo
- Selector de año (grande y legible)
- Botón "Generar informe"
- Botón "Imprimir / Guardar PDF"
- Botón "Volver a Informes"
- Título visible con año y tiempo total

#### 6.2 Tabla 1: Tiempo de práctica por tipo de actividad
- Columnas: Actividad | Ene | Feb | Mar | ... | Dic | Días | Total | %
- **12 columnas mensuales** con nombres abreviados
- Cada celda muestra:
  - Tiempo en formato breve
  - Días practicados ese mes (texto pequeño)
- Fila TOTAL al final

#### 6.3 Gráfico 1: Distribución de Tiempo por Actividad
- Idéntico al informe mensual
- Datos del año completo
- Mismos colores y formato

#### 6.4 Tabla 2: Piezas de Repertorio
- Columnas: Libro | Gr | Compositor | Nombre | Tempo | Instr | Pond | Ene | Feb | ... | Dic | Días | Media
- **12 columnas mensuales** con medias de fallos/día de cada mes
- Cada celda mensual:
  - Muestra media de fallos/día de ese mes
  - Color según el rendimiento de ese mes
- Columna **Media**: media global del año
- Color de fila según media anual
- Solo muestra piezas practicadas en el año

**Algoritmo de cálculo mensual:**
```php
foreach ($datosFallos as $dato) {
    $mes = (int)$dato['mes'];
    $piezaId = $dato['pieza_id'];
    
    if (!isset($piezas[$piezaId]['medias_por_mes'][$mes])) {
        $piezas[$piezaId]['medias_por_mes'][$mes] = null;
    }
    
    // Media = total_fallos_mes / días_practicados_mes
    $media = $dato['total_fallos'] / $dato['dias_practicados'];
    $piezas[$piezaId]['medias_por_mes'][$mes] = $media;
}

// Media anual
$piezas[$piezaId]['media_fallos_anio'] = 
    $piezas[$piezaId]['total_fallos_anio'] / 
    $piezas[$piezaId]['dias_practicados_anio'];
```

#### 6.5 Gráfico 2: Distribución de Piezas por Rendimiento
- Idéntico al informe mensual
- Datos del año completo
- Misma paleta de colores

**Formato de salida:**
- Orientación: Landscape (apaisada)
- Optimizado para PDF
- Tablas más compactas por mayor cantidad de columnas

**Funciones auxiliares:**
```php
function getNombreMesCorto($numeroMes) {
    return ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
            'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][$numeroMes - 1];
}

function formatearTiempoBreve($segundos) {
    if ($segundos == 0) return '-';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    return $horas > 0 ? sprintf("%d:%02d", $horas, $minutos) : sprintf("%d'", $minutos);
}
```

---

### 7. Admin (`admin.php`)

**Propósito:** Panel de administración.

**Funcionalidades:**
- Enlace a gestión de sesiones manuales
- Exportación de datos (CSV/JSON)
- Importación de datos

---

### 8. Gestionar Sesiones (`gestionar_sesiones.php`)

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

### 7. Informe Mensual (`informe_mensual.php`)

**Propósito:** Generar informe mensual detallado con tabla completa de práctica diaria por pieza.

**Funcionalidades:**
- **Tabla transpuesta** de práctica mensual:
  - Filas: Piezas del repertorio
  - Columnas: Días del mes
  - Celdas: Número de fallos por día (código de colores)
- **Columnas fijas iniciales:**
  - Libro, Grado, Compositor, Nombre, Tempo, Instrumento
- **Columnas estadísticas finales:**
  - Días practicados (días distintos del mes)
  - Media de fallos del mes
  - Total de minutos practicados
- **Tabla de actividades:**
  - Resumen por tipo de actividad
  - Días practicados por tipo
  - Total de minutos por tipo
- **Sistema de colores para daltonismo:**
  - 6 niveles distinguibles (azul oscuro → azul medio → azul claro → verde → gris → rojo)
  - Basado en número exacto de fallos por día
  - Texto con contraste WCAG AA (blanco o negro según fondo)
- **Exportación a PDF:**
  - Orientación apaisada (landscape)
  - Ancho completo de página (`max-width: none`)
  - Colores preservados con `print-color-adjust: exact`
  - **Importante:** Activar "Gráficos de fondo" en el navegador al imprimir
- **Ajuste automático de texto:**
  - Columnas fijas con `white-space: normal` (multi-línea)
  - Mejor legibilidad sin tabla excesivamente ancha

**Cálculo de estadísticas mensuales:**
```sql
-- Fallos por pieza y día
SELECT 
    p.id,
    DATE(f.fecha_registro) as dia,
    SUM(f.cantidad) as fallos_dia
FROM piezas p
JOIN fallos f ON p.id = f.pieza_id
WHERE YEAR(f.fecha_registro) = :anio 
  AND MONTH(f.fecha_registro) = :mes
GROUP BY p.id, DATE(f.fecha_registro)

-- Días practicados por pieza
SELECT 
    p.id,
    COUNT(DISTINCT DATE(f.fecha_registro)) as dias_practicados
FROM piezas p
JOIN fallos f ON p.id = f.pieza_id
WHERE YEAR(f.fecha_registro) = :anio 
  AND MONTH(f.fecha_registro) = :mes
GROUP BY p.id

-- Media de fallos del mes
SUM(total_fallos) / COUNT(DISTINCT dias) as media_mes
```

**Códigos de color por número de fallos (adaptado para daltonismo):**
- 🔵 Azul oscuro (#2E5F8A) - 0 fallos: Perfección
- 🔵 Azul medio (#4A7BA7) - 1 fallo: Excelente
- 🔵 Azul claro (#A3C1DA) - 2 fallos: Muy bien
- 🟢 Verde amarillento (#D4E89E) - 3 fallos: Bien
- ⚫ Gris (#9B9B9B) - 4 fallos: Mejorable
- 🔴 Rojo (#E57373) - 5+ fallos: Atención

**CSS para preservar colores en PDF:**
```css
* {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

@media print {
    @page {
        size: landscape;
        margin: 1cm;
    }
    
    table, tr, td, th {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
```

**Nota importante sobre exportación PDF:**
Para que los colores se mantengan en el PDF exportado, es necesario:
1. Activar "Gráficos de fondo" en el diálogo de impresión del navegador
2. Chrome/Brave/Edge: Ctrl+P → Más ajustes → ☑ Gráficos de fondo
3. Firefox: Ctrl+P → Configuración → ☑ Imprimir fondos
4. Safari: ⌘+P → Safari → ☑ Imprimir fondos

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
**Stack:** PHP, MySQL, JavaScript, DataTables, jQuery, Canvas API  
**Fecha de creación:** Enero 2025  

---

## 📝 Historial de Cambios

### v1.9.0 (30 Enero 2025)
- ✅ Dashboard unificado con estadísticas extendidas (tiempo semana/año, días año)
- ✅ Racha mejorada (excluye día actual si no hay actividad)
- ✅ Informe mensual: tabla piezas renombrada, columna ponderación, 2 gráficos de tarta
- ✅ Informe anual: nuevo archivo completo con vista de 12 meses
- ✅ Sesión: auto-finalización al salir y al terminar última actividad
- ✅ Selectores mejorados en ambos informes (más grandes y legibles)
- ✅ Botones "Volver a Informes" en ambos informes
- ✅ Gráficos Canvas: distribución de actividades y rendimiento de piezas

### v1.8.5 (26 Enero 2025)
- Informe mensual con layout full-width
- Preservación de colores en PDF
- Documentación actualizada

### v1.0 (22 Enero 2025)
- Release inicial completo

---

**Piano Tracker v1.9.0 - Documentación Técnica Completa**
