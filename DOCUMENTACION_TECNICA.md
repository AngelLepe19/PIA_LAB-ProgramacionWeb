# LecturaLibre — Documentación técnica

Biblioteca digital en PHP + MySQL (XAMPP). Este documento explica **cómo se monta** el proyecto y **cómo se ejecuta**, y describe el módulo administrativo (CRUD de libros, categorías y usuarios, moderación de reseñas y reportes).

---

## 1. Cómo se monta tu proyecto

### 1.1 Requisitos

| Herramienta | Versión recomendada |
|---|---|
| XAMPP (Apache + MySQL/MariaDB + PHP) | PHP 8.0 o superior |
| Git | cualquier versión reciente |
| MySQL Workbench o phpMyAdmin | para importar la base de datos |
| Navegador | Chrome, Edge o Firefox |

### 1.2 Configurar XAMPP

1. Instala XAMPP y abre el **XAMPP Control Panel**.
2. Pulsa **Start** en **Apache** y en **MySQL**. Ambos deben quedar en verde.
3. Verifica que Apache funciona abriendo <http://localhost> en el navegador.

> Si MySQL no inicia porque el puerto 3306 está ocupado (por ejemplo, por otra instalación de MySQL), cambia el puerto en XAMPP (`Config > my.ini`) y actualiza `DB_PORT` en `admin/inc/config.php`.

### 1.3 Clonar el repositorio

El proyecto debe quedar dentro de la carpeta `htdocs` de XAMPP:

```bash
cd C:\xampp\htdocs
git clone <URL-DEL-REPOSITORIO> LecturaLibre
cd LecturaLibre
```

Quedará en `C:\xampp\htdocs\LecturaLibre\`.

### 1.4 Importar la base de datos

1. Abre <http://localhost/phpmyadmin> (o MySQL Workbench).
2. Crea la base de datos **`lecturalibre`** con cotejamiento `utf8mb4_unicode_ci`.
3. Selecciona `lecturalibre` → pestaña **Importar** → elige `database/lecturalibre.sql` → **Continuar**.
   (En Workbench: *Server → Data Import → Import from Self-Contained File*.)
4. Comprueba que existen las 14 tablas y las vistas `v_descargas_por_categoria` y `v_libros_mas_descargados`.

### 1.5 Crear las contraseñas de prueba

Los usuarios de ejemplo de la base de datos traen hashes de relleno, con los que no se puede iniciar sesión. Ejecuta **una sola vez** el script `database/preparar_usuarios_prueba.sql` (en phpMyAdmin: pestaña **SQL** → pegar el contenido → **Continuar**).

| Rol | Correo | Contraseña de prueba |
|---|---|---|
| Administrador | admin@lecturalibre.com | `Admin2026!` |
| Trabajador | trabajador@lecturalibre.com | `Trabajador2026!` |
| Autor | autor@lecturalibre.com | `Autor2026!` |
| Lector | lector@lecturalibre.com | `Lector2026!` |

> Son cuentas solo para desarrollo. Deben cambiarse o eliminarse antes de publicar el sitio.

### 1.6 Revisar la conexión

Abre `admin/inc/config.php`. Los valores por defecto de XAMPP ya vienen puestos:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'lecturalibre');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Si tu MySQL tiene contraseña o usa otro puerto, cámbialos aquí.

### 1.7 Carpeta de archivos subidos

Los PDF y portadas que suben los autores se guardan en `uploads/pdf/` y `uploads/portadas/`. Estas carpetas ya existen en el repositorio (con un `.gitkeep`), pero **su contenido no se sube a Git** (ver `.gitignore`). Cada integrante tendrá sus propios archivos de prueba localmente.

Si al subir un PDF aparece "demasiado grande", aumenta en `C:\xampp\php\php.ini`: `upload_max_filesize = 40M` y `post_max_size = 40M`, y reinicia Apache.

---

## 2. Cómo se ejecuta

1. Con Apache y MySQL encendidos, abre:
   **<http://localhost/LecturaLibre/admin/login.php>**
2. Inicia sesión con una de las cuentas de prueba de la sección 1.5.
3. Según el rol, el sistema te lleva a su panel:

| Rol | Entra a | Puede hacer |
|---|---|---|
| **Administrador** | `reportes.php` | Todo: reportes, libros, categorías, usuarios/roles, moderación |
| **Trabajador** | `resenas.php` | Moderar reseñas; aprobar o rechazar libros pendientes |
| **Autor** | `libros.php` | Subir, editar y dar de baja **solo sus** libros (quedan pendientes de revisión) |
| **Lector** | — | No tiene acceso al panel |

### Recorrido rápido para comprobar que todo funciona

1. **Administrador → Libros / Inventario → + Agregar libro**: llena el formulario, elige categoría, sube un PDF y guarda. El libro aparece en la lista como *Aprobado*.
2. **Categorías**: añade una categoría nueva, edítala y elimina una vacía. Intenta eliminar una con libros: el sistema lo impide.
3. **Usuarios / Roles**: crea un usuario, cámbiale el rol y desactívalo.
4. **Moderación Reseñas**: aprueba o rechaza la reseña pendiente.
5. **Reportes**: revisa las tarjetas y las dos tablas que salen de las vistas SQL; prueba **Imprimir reporte**.
6. Cierra sesión, entra como **Autor**, sube un libro y comprueba que queda *Pendiente*. Luego entra como **Trabajador** y apruébalo.

---

## 3. Estructura del módulo administrativo

```
LecturaLibre/
├── admin/
│   ├── inc/
│   │   ├── config.php      Datos de conexión y límites
│   │   ├── bootstrap.php   Sesión, PDO, CSRF, roles, subida segura de archivos
│   │   ├── header.php      Encabezado y pestañas según el rol
│   │   └── footer.php
│   ├── assets/admin.css
│   ├── login.php / logout.php   Acceso temporal del panel
│   ├── reportes.php     Reportes (lee las vistas SQL)
│   ├── libros.php       CRUD 1: listar, aprobar/rechazar, dar de baja, reactivar
│   ├── libro_form.php   CRUD 1: crear y editar libros (con PDF y portada)
│   ├── categorias.php   CRUD 2: categorías
│   ├── usuarios.php     CRUD 2: usuarios y roles
│   └── resenas.php      Moderación de reseñas
├── database/
│   ├── lecturalibre.sql                  Base de datos (exportada de Workbench)
│   └── preparar_usuarios_prueba.sql      Contraseñas de prueba
├── uploads/             PDF y portadas subidos (no se versionan)
└── DOCUMENTACION_TECNICA.md
```

### Decisiones de diseño

- **PDO con consultas preparadas** en todo el módulo: evita inyección SQL.
- **Contraseñas con `password_hash` (bcrypt)**; nunca se guardan en texto plano.
- **Token CSRF** en todos los formularios que modifican datos; las acciones de borrar/aprobar son siempre `POST`, nunca enlaces.
- **Control de acceso por rol en el servidor** (`require_rol`), no solo ocultando botones. Un Autor no puede editar ni dar de baja libros de otro autor aunque manipule la petición.
- **"Eliminar" = baja lógica**: los libros pasan a estado `baja` y las cuentas a `inactivo`, porque la base de datos conserva el historial de pedidos (`ON DELETE RESTRICT`).
- **Flujo de aprobación**: los libros nuevos de un Autor quedan `pendiente`; un Trabajador o Administrador los aprueba o rechaza y se guarda quién y cuándo (`id_revisor`, `fecha_revision`). Si un Autor edita un libro ya aprobado, vuelve a `pendiente`.
- **Moderación de reseñas**: se guarda el moderador y la fecha (`id_moderador`, `fecha_moderacion`).
- **Subida de archivos**: se valida el tipo real del archivo por su contenido (no solo la extensión), se limita el tamaño y se guarda con nombre aleatorio. `uploads/.htaccess` impide ejecutar scripts ahí.
- **Reportes**: `reportes.php` solo hace `SELECT *` sobre `v_descargas_por_categoria` y `v_libros_mas_descargados`; las columnas de las tablas se dibujan según lo que devuelva cada vista.
- **Protecciones del administrador**: no puede quitarse su propio rol ni desactivar su propia cuenta.

### Integración con el login del equipo

El módulo espera que, al iniciar sesión, se guarde en la sesión:

```php
$_SESSION['usuario'] = ['id' => 1, 'nombre' => 'Ana', 'rol' => 'Administrador'];
// 'rol' = Lector | Autor | Trabajador | Administrador (nombre del rol en la tabla roles)
```

Si el login oficial guarda otras llaves, solo hay que adaptar la función `usuario_actual()` en `admin/inc/bootstrap.php`.

---

## 4. Flujo de trabajo con Git (para el equipo)

- La rama `main` solo recibe código por **Pull Request** revisado por el responsable del repositorio.
- Cada integrante trabaja en su propia rama: `feature/<tema>-<nombre>`.
- Commits pequeños, con mensaje claro y **con el usuario de GitHub de cada quien** (es requisito de evaluación que todos tengan commits a su nombre).

```bash
git checkout main && git pull
git checkout -b feature/mi-tarea
# ...trabajar...
git add .
git commit -m "Descripción clara del cambio"
git push -u origin feature/mi-tarea
# En GitHub: abrir Pull Request hacia main
```

---

## 5. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| "Ocurrió un error… revisa que XAMPP esté encendido" | MySQL apagado o datos de `config.php` incorrectos | Encender MySQL; revisar puerto, usuario y contraseña |
| Login dice "Correo o contraseña incorrectos" con las cuentas de ejemplo | No se ejecutó `preparar_usuarios_prueba.sql` | Ejecutarlo (sección 1.5) |
| Error en `reportes.php` por una vista inexistente | La base se importó sin las vistas | Reimportar `lecturalibre.sql` completo |
| No sube el PDF | Límites de PHP | Subir `upload_max_filesize` y `post_max_size` en `php.ini` |
| Se ve sin estilos | Sin internet (Bootstrap se carga desde CDN) | Conectarse a internet o descargar Bootstrap local |
| El logo lleva a una página que no existe | El enlace apunta a `../index.php` | Ajustar el `href` del logo en `admin/inc/header.php` a la página de inicio real |
