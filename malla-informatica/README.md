# Malla Ingeniería Civil en Computación e Informática

Página web que muestra la malla curricular (11 semestres, 59 ramos). Al hacer clic en un ramo se
abre su página con la descripción y los contenidos. Todo se guarda en MySQL (phpMyAdmin).

```
malla-informatica/
├── index.html      # Malla completa con buscador y filtro por área
├── ramo.html       # Detalle de un ramo (ramo.html?id=1)
├── api.php         # Entrega los datos en JSON desde MySQL
├── conexion.php    # Conexión a la base de datos
├── style.css       # Estilos
└── database.sql    # Crea la base malla_db con áreas, ramos y contenidos
```

## Base de datos (malla_db)
- `areas`: formación profesional, disciplinar y general (con su color)
- `ramos`: semestre, nombre, área y descripción
- `contenidos`: temas de cada ramo

## Local (XAMPP)
1. Inicia Apache y MySQL.
2. Copia la carpeta a `C:\xampp\htdocs\malla-informatica`.
3. En phpMyAdmin → Importar → `database.sql`.
4. Abre `http://localhost/malla-informatica/`.

## Servidor (Amazon Linux 2023)
El usuario y la clave de la base van en `config.local.php` (no se sube a GitHub).
Actualizar: `cd /var/www/repo && sudo git pull`

## Importante
Los nombres de los ramos y su semestre salen de la malla oficial entregada. Las descripciones y los
contenidos son referenciales: reemplázalos por los programas oficiales editando las tablas
`ramos` y `contenidos` en phpMyAdmin.
