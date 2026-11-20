# Personas Web

Página web simple en PHP + MySQL (phpMyAdmin) que muestra 3 personas desde la base de datos.

## Estructura

```
personas-web/
├── index.php        # Página principal
├── conexion.php     # Conexión a MySQL
├── style.css        # Estilos
├── database.sql     # Script de la base de datos
└── README.md
```

## Cómo ejecutarlo

1. Instala XAMPP (o Laragon) e inicia **Apache** y **MySQL**.
2. Copia la carpeta `personas-web` en `C:\xampp\htdocs\`.
3. Abre `http://localhost/phpmyadmin`, ve a la pestaña **Importar** y sube `database.sql`.
4. Abre `http://localhost/personas-web/` en el navegador.

Si tu MySQL tiene contraseña, cámbiala en `conexion.php`.
