-- Base de datos para Piano Tracker
CREATE DATABASE IF NOT EXISTS piano_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE piano_tracker;

-- Tabla de configuración general
CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(100) PRIMARY KEY,
    valor TEXT NOT NULL,
    descripcion VARCHAR(500),
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NO insertar contraseña por defecto
-- La primera vez se debe acceder a setup.php para establecerla

-- Tabla de piezas del repertorio
CREATE TABLE IF NOT EXISTS piezas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compositor VARCHAR(200) NOT NULL,
    titulo VARCHAR(300) NOT NULL,
    libro VARCHAR(200),
    grado INT,
    tempo INT,
    ponderacion DECIMAL(5,2) DEFAULT 1.00,
    instrumento VARCHAR(50) DEFAULT 'Piano',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activa BOOLEAN DEFAULT TRUE,
    INDEX idx_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de sesiones de práctica
CREATE TABLE IF NOT EXISTS sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    hora_inicio TIME,
    hora_fin TIME,
    estado ENUM('planificada', 'en_curso', 'finalizada') DEFAULT 'planificada',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de actividades dentro de cada sesión
CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id INT NOT NULL,
    orden INT NOT NULL,
    tipo ENUM('calentamiento', 'tecnica', 'practica', 'repertorio', 'improvisacion', 'composicion') NOT NULL,
    pieza_id INT NULL,
    notas TEXT,
    tiempo_segundos INT DEFAULT 0,
    estado ENUM('pendiente', 'en_curso', 'completada') DEFAULT 'pendiente',
    fecha_inicio DATETIME,
    fecha_fin DATETIME,
    FOREIGN KEY (sesion_id) REFERENCES sesiones(id) ON DELETE CASCADE,
    FOREIGN KEY (pieza_id) REFERENCES piezas(id) ON DELETE SET NULL,
    INDEX idx_sesion (sesion_id),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de registro de fallos por actividad
CREATE TABLE IF NOT EXISTS fallos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    pieza_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 0,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (pieza_id) REFERENCES piezas(id) ON DELETE CASCADE,
    INDEX idx_actividad (actividad_id),
    INDEX idx_pieza_fecha (pieza_id, fecha_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de ejemplo (opcional, comentar si no se desea)
INSERT INTO piezas (compositor, titulo, libro, grado, tempo, ponderacion) VALUES
('J.S. Bach', 'Preludio en Do Mayor', 'Clave Bien Temperado I', 5, 80, 1.00),
('F. Chopin', 'Nocturno Op.9 No.2', NULL, 6, 60, 1.25),
('L. van Beethoven', 'Para Elisa', NULL, 4, 72, 1.00),
('C. Debussy', 'Clair de Lune', 'Suite Bergamasque', 7, 50, 1.50);
