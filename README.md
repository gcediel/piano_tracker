# 🎹 Piano Tracker

**Aplicación web profesional para gestión de práctica de piano**

[![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://www.php.net/)
[![MySQL Version](https://img.shields.io/badge/MySQL-8.0+-orange.svg)](https://www.mysql.com/)
[![Version](https://img.shields.io/badge/version-1.0-brightgreen.svg)](CHANGELOG.md)

---

## 📖 Descripción

Piano Tracker es una aplicación web completa diseñada para pianistas que desean llevar un registro sistemático de su práctica. Permite gestionar un repertorio de piezas, registrar sesiones con cronómetro en tiempo real, hacer seguimiento de errores, y obtener sugerencias inteligentes de qué practicar basadas en un algoritmo de priorización.

### ✨ Características principales

- 🎼 **Gestión de repertorio** con metadatos completos
- ⏱️ **Sesiones con cronómetro** integrado y seguimiento en tiempo real
- 📊 **Estadísticas detalladas** con DataTables interactivas
- 🧮 **Algoritmo de sugerencia** inteligente
- 📈 **Informes visuales** por periodo
- 🎯 **Seguimiento de fallos** por pieza con cálculo de medias
- 💾 **Exportación/importación** de datos
- 🔧 **Panel administrativo** para gestión manual de sesiones

---

## 🚀 Instalación Rápida

### Requisitos

- PHP 8.0+
- MySQL 8.0+
- Apache/Nginx

### Pasos

```bash
# 1. Clonar repositorio
git clone https://github.com/tu-usuario/piano-tracker.git

# 2. Crear base de datos
mysql -u root -p
```

```sql
CREATE DATABASE piano_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'piano_user'@'localhost' IDENTIFIED BY 'contraseña_segura';
GRANT ALL PRIVILEGES ON piano_tracker.* TO 'piano_user'@'localhost';
```

```bash
# 3. Importar esquema
mysql -u piano_user -p piano_tracker < database/schema.sql

# 4. Configurar config/database.php

# 5. Acceder
http://localhost/piano-tracker/
```

Ver [DOCUMENTACION_TECNICA.md](DOCUMENTACION_TECNICA.md) para más detalles.

---

## 📋 Funcionalidades

| Módulo | Descripción |
|--------|-------------|
| **Inicio** | Dashboard con métricas, rachas y últimas sesiones |
| **Repertorio** | CRUD de piezas con estadísticas y código de colores |
| **Sesión** | Cronómetro en tiempo real con registro de fallos |
| **Informes** | Análisis estadístico por periodos |
| **Admin** | Gestión manual de sesiones históricas |

---

## 🧮 Algoritmo de Sugerencia

```
Score = SUM((10 - Fallos_día) × Peso_temporal) × (1 / Ponderación)
```

- Inversión de fallos (0 fallos = 10 pts, 10+ = 0 pts)
- Peso temporal lineal (reciente = más peso)
- Factor de ponderación (importante = más prioridad)
- **MENOR score = MAYOR prioridad**

---

## 🗂️ Estructura

```
piano_tracker/
├── config/         # Configuración y conexión DB
├── includes/       # Header/footer compartidos
├── assets/css/     # Estilos
├── database/       # Schema SQL
├── *.php           # Páginas principales
├── DOCUMENTACION_TECNICA.md
└── README.md
```

---

## 🛠️ Stack Tecnológico

- **Backend:** PHP 8.x + PDO
- **Base de datos:** MySQL 8.x
- **Frontend:** HTML5 + CSS3 + JavaScript
- **Librerías:** DataTables, jQuery

---

## 📚 Documentación

- [📖 Documentación Técnica](DOCUMENTACION_TECNICA.md)
- [📝 Changelog](CHANGELOG.md)
- [🗄️ Schema SQL](database/schema.sql)

---

## 🤝 Contribuir

1. Fork el proyecto
2. Crea rama feature (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Añadir funcionalidad'`)
4. Push (`git push origin feature/nueva-funcionalidad`)
5. Abre Pull Request

---

## 📝 Licencia

[Especificar licencia]

---

## 👤 Autor

**Guillermo** - Enero 2025

---

<p align="center">
  <strong>Piano Tracker v1.0</strong><br>
  Hecho con ❤️ para pianistas
</p>
