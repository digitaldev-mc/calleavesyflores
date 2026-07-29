# La Calle de las Aves y las Flores — Manizales Comparte

Landing institucional + panel de administración para un proyecto de arte urbano
que convierte persianas/rejas comerciales en murales de aves. Incluye cotizador,
generación de PDF de cotización y panel de administración con backend real.

Este documento es la referencia técnica del código. Para **operación y despliegue**
(hosting, GitHub Actions, VPS), usa **`COMO-FUNCIONA.md`** (mismo patrón que
presupuesto-ventas).

---

## 1. Arquitectura

Stack: **HTML/CSS/JS estático en el frontend + PHP (sin framework) + MySQL (PDO)**.
No usa Composer ni dependencias externas de servidor. La única librería de terceros
es **jsPDF** (cargada por CDN en el navegador) para generar el PDF de la cotización
del lado del cliente.

Flujo: `index.html` (SPA sencilla) → hace `fetch` a `api.php?action=...` →
`api.php` usa `db.php` (conexión PDO) y `config.php` (credenciales) → MySQL.

```
Navegador (index.html + JS)
        │  fetch JSON  (GET/POST a api.php?action=...)
        ▼
   api.php  ──requiere──▶  db.php  ──requiere──▶  config.php
        │                    │
        │                    ▼
        └───────────────▶  MySQL (7 tablas)
```

La sesión de administrador es **server-side** (`session_start()`), con la contraseña
guardada **hasheada** (`password_hash` / `password_verify`) en la tabla `config`.
Las acciones de administración exigen sesión válida + token **CSRF**.

---

## 2. Archivos del proyecto

| Archivo               | Rol                                                                                  | ¿Se edita? |
|-----------------------|--------------------------------------------------------------------------------------|------------|
| `logo.png`            | Logo del sitio (fondo transparente). Nav y footer.                                    | No |
| `index.html`          | Frontend completo: landing pública + modales + panel admin. Consume `api.php`.        | No (salvo cambios de diseño) |
| `api.php`             | Endpoint único del backend. Enrutado por `?action=`. Lógica pública + admin.          | No |
| `config.php`          | **Único archivo a editar antes de subir.** Credenciales de BD y correos.              | **Sí** |
| `db.php`              | Conexión PDO a MySQL + helpers (`cfg_get`, `cfg_set`, `responder`).                   | No |
| `setup.php`           | Instalador de **un solo uso**. Crea tablas y siembra datos. **Borrar tras instalar.** | No (se ejecuta y se borra) |
| `.htaccess`           | Fuerza HTTPS, bloquea `config.php`/`db.php`, cabeceras de seguridad, `-Indexes`.       | No |
| `GUIA-DESPLIEGUE.html`| Guía visual paso a paso para el usuario final (no se sube al servidor).               | No |
| `deploy/`             | Scripts de despliegue VPS: `setup-vps.sh`, `deploy.sh`, hook `post-receive`.          | No |
| `README.md`           | Este documento (no se sube al servidor).                                             | No |

> Archivos que **sí** se suben al servidor: `index.html`, `logo.png`, `api.php`,
> `config.php`, `db.php`, `setup.php`, `.htaccess`, carpeta `deploy/`.
> **No** se suben: `GUIA-DESPLIEGUE.html`, `README.md`, `config.php.example` (solo plantilla).

---

## 3. Configuración (`config.php`)

Constantes que el usuario debe rellenar con los datos de la BD MySQL de DreamHost
(panel → **MySQL Databases**):

```php
define('DB_HOST', 'mysql.tudominio.com'); // hostname MySQL del panel
define('DB_NAME', 'aves_y_flores');       // nombre de la base de datos
define('DB_USER', 'usuario_bd');          // usuario de la BD
define('DB_PASS', 'contraseña_bd');       // contraseña del usuario

define('CORREO_DESTINO', 'manizalescomparte@gmail.com'); // recibe solicitudes
define('CORREO_ORIGEN',  'notificaciones@tudominio.com'); // From (cuenta del dominio)
```

`date_default_timezone_set('America/Bogota')` ya viene fijado.

---

## 4. Modelo de datos (MySQL)

Creado por `setup.php`. Charset `utf8mb4`, motor InnoDB.

- **config** `(clave VARCHAR PK, valor LONGTEXT)` — clave/valor. Guarda:
  `admin_hash`, `precioM2`, `pctComparte`, `pctInstitucional`, `correoDestino`,
  `condiciones`.
- **murales** `(id PK AI, num, nombre, cientifico, descripcion, tags, color, img LONGTEXT, seleccionado_por, orden)`
  — `tags` se guarda como texto unido por `||`; `img` es un data URI base64 (JPEG).
  `seleccionado_por` vacío = disponible; con valor = muestra cinta roja y se deshabilita.
- **aliados** `(id PK AI, nombre, img LONGTEXT, orden)` — logos base64.
- **videos** `(id PK AI, youtube_id, orden)` — se identifica/borra por `youtube_id`.
- **resenas** `(id PK AI, texto, quien, negocio, estrellas TINYINT, orden)`.
- **codigos** `(codigo VARCHAR PK, pct INT)` — descuento **adicional** en %.
- **solicitudes** `(id PK AI, numero, fecha, nombre, identificacion, negocio, direccion,
  telefono, correo, mural_num, mural_nombre, ancho, alto, m2, total, a_comparte, a_inst,
  codigo, codigo_pct, dcto, pagar, creado_en TIMESTAMP)`.

Semilla inicial: 7 murales (con imágenes embebidas en `setup.php`), 3 reseñas de
ejemplo y 2 códigos (`MZLSCOMPARTE`=10%, `AVESYFLORES`=5%).

---

## 5. API (`api.php`) — acciones por `?action=`

Respuestas siempre en JSON. Errores devuelven `{ "error": "..." }` con código HTTP.
El cuerpo de las peticiones POST se envía como JSON.

### Públicas
| action        | método | descripción |
|---------------|--------|-------------|
| `content`     | GET    | Devuelve todo el contenido público (config no sensible, murales, aliados, videos, reseñas, códigos). |
| `session`     | GET    | `{ admin: bool, csrf: string }`. Inicializa el token CSRF. |
| `login`       | POST   | `{ password }` → verifica con `password_verify`; setea sesión; devuelve `csrf`. |
| `logout`      | POST   | Cierra la sesión. |
| `solicitud`   | POST   | Registra una cotización + envía correo (`mail()`). Genera `numero` autoritativo en el servidor. Tiene **honeypot** (campo `website`). |

### Administración (requieren sesión admin + `csrf` en el cuerpo)
`solicitudes_list`, `solicitud_delete`, `mural_add`, `mural_marcar`, `mural_reabrir`,
`mural_delete`, `aliado_add`, `aliado_delete`, `video_add`, `video_delete` (por `youtube_id`),
`resena_add`, `resena_delete`, `codigo_add`, `codigo_delete`, `config_save`
(incluye cambio de contraseña opcional vía campo `nuevaPass`).

`exigir_admin()` valida la sesión y compara el CSRF con `hash_equals`.

---

## 6. Lógica del cotizador (debe mantenerse consistente frontend/PDF)

```
m2      = ancho * alto
total   = m2 * precioM2                    // precioM2 por defecto: 250000 COP
aporteC = total * (pctComparte / 100)      // Apoyo Manizales Comparte (15%)
aporteI = total * (pctInstitucional / 100) // Apoyo institucional (15%)
pagar   = total - aporteC - aporteI        // el establecimiento paga el 70%
// código de descuento (opcional) se aplica ADICIONALMENTE sobre "pagar":
dcto    = pagar * (codigoPct / 100)
pagar   = pagar - dcto
```

El PDF (jsPDF, client-side) refleja exactamente estas mismas líneas.

---

## 7. Seguridad implementada

- Contraseña admin **hasheada** en BD (nunca viaja al cliente ni está en el código).
- Sesión server-side + **token CSRF** en todas las acciones de administración.
- **PDO con prepared statements** en todas las consultas (anti SQL injection).
- Salida escapada en el frontend (`esc()`), atributos e `iframe` de YouTube saneados.
- **Honeypot** en el formulario público + pequeña demora en login fallido.
- `.htaccess`: fuerza HTTPS (301), bloquea acceso directo a `config.php` y `db.php`,
  `Options -Indexes`, cabeceras `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`.
- `setup.php` **debe borrarse** tras la instalación.

---

## 8. Requisitos del servidor

- PHP 7.4+ (probado con sintaxis compatible 8.x; usa arrow functions y null coalescing).
- Extensiones: `pdo_mysql`, `mbstring`, `session` (estándar en DreamHost).
- Función `mail()` habilitada (estándar en DreamHost) para el aviso por correo.
- MySQL/MariaDB.

---

## 9. Pasos de despliegue (checklist para continuar)

1. **Crear la BD MySQL** en el panel de DreamHost. Anotar hostname, nombre, usuario, contraseña.
2. **Editar `config.php`** con esos 4 datos + `CORREO_ORIGEN` a una cuenta del dominio.
3. *(Recomendado)* **Crear la cuenta de correo** `notificaciones@tudominio.com` en el panel.
4. **Subir** los 6 archivos a la carpeta del dominio (File Manager o SFTP puerto 22).
   Verificar que `.htaccess` (oculto) se subió.
5. **Ejecutar** `https://tudominio.com/setup.php` una vez, definir la contraseña de admin (mín. 6), Instalar.
6. **Borrar `setup.php`** del servidor (paso de seguridad obligatorio).
7. **Activar SSL Let's Encrypt** (gratis) en el panel → *Secure Certificates*. El `.htaccess` ya fuerza HTTPS.
8. **Probar**: murales cargan, cotizador calcula, formulario descarga PDF y envía correo,
   login de admin funciona, y el marcar "Seleccionado por…" se ve para todos.

### VPS DreamHost + Git (recomendado para actualizaciones)

Ver sección completa en `GUIA-DESPLIEGUE.html` (#vps-git). Resumen:

1. SSH al VPS → `sudo bash deploy/setup-vps.sh` (crea usuario, repo bare, Apache).
2. Crear BD MySQL en el VPS (`CREATE DATABASE aves_y_flores …`).
3. En local: `git init`, commit, `git remote add production usuario@dominio:repos/aves-mc.git`, `git push production main`.
4. En el servidor: `cp config.php.example config.php` y editar credenciales.
5. Ejecutar `setup.php` una vez, borrarlo, activar SSL con certbot.
6. **Flujo diario:** `git push production main` → el hook `post-receive` despliega solo.

---

## 10. Pendientes / mejoras futuras

- **Entrega de correo:** `mail()` a Gmail puede caer en spam. Mejora recomendada:
  enviar por **SMTP autenticado** con una cuenta del dominio (p. ej. PHPMailer).
- **Respaldos:** programar/verificar backups de la BD periódicamente.
- **Optimización de imágenes:** hoy los murales/aliados se guardan como base64 en
  `LONGTEXT`. Alternativa a futuro: guardar como archivos en `/uploads` y referenciar
  por ruta (reduce el peso de la BD y de las respuestas de `content`).
- **Paginación** de `solicitudes_list` si el volumen crece mucho.

---

## 11. Notas para desarrollo local (opcional, para Cursor)

Para probar antes de subir a DreamHost:

```bash
# 1) Levantar MySQL local y crear una BD vacía llamada, p.ej., aves_y_flores
# 2) Ajustar config.php a las credenciales locales (DB_HOST='127.0.0.1', etc.)
# 3) Servir la carpeta con PHP:
php -S localhost:8000
# 4) Abrir http://localhost:8000/setup.php una vez, definir contraseña, Instalar.
# 5) Borrar setup.php y abrir http://localhost:8000/
```

> El envío de correo con `mail()` normalmente no funciona en local sin un MTA;
> es esperable. La solicitud igual se guarda en la BD.

Verificaciones útiles:
```bash
php -l config.php && php -l db.php && php -l api.php && php -l setup.php   # lint de sintaxis
node --check <(sed -n 's/.*<script>\(.*\)<\/script>.*/\1/p' index.html)   # (aprox.) sintaxis del JS
```
#   c a l l e a v e s y f l o r e s  
 