# Personas Web

Página en HTML que muestra 3 personas desde MySQL (phpMyAdmin).
`index.html` pide los datos a `api.php`, que los lee de la base.

```
personas-web/
├── index.html      # Página (HTML + JavaScript)
├── api.php         # Devuelve las personas en JSON
├── conexion.php    # Conexión a MySQL
├── style.css       # Estilos
└── database.sql    # Base de datos y las 3 personas
```

## Local (XAMPP)
1. Inicia Apache y MySQL.
2. Copia la carpeta a `C:\xampp\htdocs\personas-web`.
3. En phpMyAdmin → Importar → `database.sql`.
4. Abre `http://localhost/personas-web/`.

## Servidor (Amazon Linux 2023)
El usuario y la clave de la base van en `config.local.php` (no se sube a GitHub).
Actualizar: `cd /var/www/repo && sudo git pull`
