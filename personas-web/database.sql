-- Base de datos para la página de 3 personas
-- Importar desde phpMyAdmin (pestaña "Importar") o pegar en la pestaña "SQL"

CREATE DATABASE IF NOT EXISTS personas_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE personas_db;

DROP TABLE IF EXISTS personas;

CREATE TABLE personas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  cargo VARCHAR(100) NOT NULL,
  correo VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

INSERT INTO personas (nombre, cargo, correo) VALUES
  ('Persona Uno',  'Desarrollador', 'uno@ejemplo.com'),
  ('Persona Dos',  'Diseñadora',    'dos@ejemplo.com'),
  ('Persona Tres', 'Analista',      'tres@ejemplo.com');
