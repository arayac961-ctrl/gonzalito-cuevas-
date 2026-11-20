# Malla Curricular y Foro de Proyectos — Ing. Civil en Computación e Informática

Aplicación web en PHP + MySQL que muestra los 11 semestres (59 ramos) de la carrera e incorpora un **sistema de cuentas (registro e inicio de sesión)** y un **foro por asignatura** donde los estudiantes pueden subir proyectos (archivos adjuntos hasta 25 MB y/o enlaces de GitHub) y comentar.

```
malla-informatica/
├── index.html      # Malla completa con buscador, filtro por área, login y contador de proyectos
├── ramo.html       # Detalle del ramo + foro de proyectos + subida de archivos + comentarios
├── api.php         # API REST: malla, proyectos, descarga segura, comentarios y borrado
├── auth.php        # API de autenticación: registro, inicio/cierre de sesión y token CSRF
├── conexion.php    # Conexión PDO a MySQL (soporta config.local.php)
├── style.css       # Estilos de la malla, modales de login y tarjetas del foro
├── database.sql    # Esquema completo (areas, ramos, contenidos, usuarios, proyectos, comentarios)
└── uploads/        # Carpeta para archivos subidos (protegida con .htaccess)
```

## Base de datos (`malla_db`)
- `areas`, `ramos`, `contenidos`: estructura oficial de la malla curricular.
- `usuarios`: cuentas con contraseña cifrada (`password_hash`) y rol (`estudiante` / `admin`).
- `proyectos`: aportes asociados a cada `ramo_id` y `usuario_id` (título, descripción, URL y archivo).
- `comentarios`: hilo de discusión por cada proyecto.

## Instalación Local (XAMPP / Laragon)
1. Inicia **Apache** y **MySQL**.
2. Copia la carpeta `malla-informatica` dentro de `htdocs/`.
3. En **phpMyAdmin → Importar**, selecciona `database.sql` y ejecuta.
4. Abre `http://localhost/malla-informatica/`.

## Despliegue en AWS (Amazon Linux 2023 / Ubuntu + Apache/Nginx + PHP + MariaDB/RDS)
1. **Subir a GitHub**:
   ```bash
   git init
   git add .
   git commit -m "Malla curricular con foro de proyectos y login"
   git branch -M main
   git remote add origin https://github.com/TU_USUARIO/TU_REPO.git
   git push -u origin main
   ```
2. **En tu instancia EC2 de AWS**:
   ```bash
   cd /var/www/html
   sudo git clone https://github.com/TU_USUARIO/TU_REPO.git malla-informatica
   cd malla-informatica
   sudo mkdir -p uploads
   sudo chown -R apache:apache uploads   # En Ubuntu usa: www-data:www-data
   sudo chmod 755 uploads
   ```
3. **Configurar credenciales de BD** creando `config.local.php` (ignorado por Git):
   ```php
   <?php
   $host = "localhost"; // o endpoint de RDS
   $bd   = "malla_db";
   $user = "tu_usuario_bd";
   $pass = "tu_clave_bd";
   ```
4. **Importar la base de datos**:
   ```bash
   sudo mysql -u root -p < database.sql
   ```
5. *(Opcional)* Ajusta el límite de subida en `/etc/php.ini` si quieres archivos mayores a 2 MB:
   ```ini
   upload_max_filesize = 30M
   post_max_size = 32M
   ```
   y reinicia el servidor web (`sudo systemctl restart httpd php-fpm` o `apache2`).
