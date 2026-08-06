# La Calle de las Aves y las Flores — Cómo funciona

Documento de operación: qué es, cómo se estructura, cómo se desarrolla localmente y cómo se despliega.

---

## 1. Qué es

Landing institucional + panel de administración para el proyecto de arte urbano
**La Calle de las Aves y las Flores** (Manizales Comparte): cotizador de murales,
generación de PDF, reseñas, aliados, videos y panel admin con backend PHP + MySQL.

- **Producción:** https://calleavesyflores.manizalescomparte.com
- **Repo:** https://github.com/digitaldev-mc/calleavesyflores

---

## 2. Arquitectura

```
Tu computador (HTML + PHP, sin build)
        │  git push origin main
        ▼
GitHub (digitaldev-mc/calleavesyflores)
        │  dispara GitHub Actions
        ▼
SSH → VPS DreamHost (vps16389.dreamhostps.com, usuario avesyflores_mzl)
        │  ejecuta scripts/deploy.sh
        ▼
  1. git pull en ~/avesyflores-src
  2. rsync → ~/calleavesyflores.manizalescomparte.com/   (sin tocar config.php)
  3. (opcional) patch-config.php si Actions inyecta RESEND_API_KEY
        │
        ▼
https://calleavesyflores.manizalescomparte.com  (Apache + PHP 8.3 + .htaccess)
        │
        ├── index.html + logo.png  (frontend estático)
        └── api.php  ←→  MySQL (7 tablas)
```

**No hay build ni Node en producción.** Los archivos PHP se copian tal cual; Apache sirve
`index.html` y ejecuta `api.php`.

---

## 3. Persistencia de datos

| Capa | Qué hace |
|---|---|
| **MySQL** | Murales, aliados, videos, reseñas, códigos, solicitudes, config admin |
| **`config.php`** (solo en el servidor) | Credenciales BD + correos. **No está en Git.** |
| **Sesión PHP** | Login del panel admin + token CSRF |

Contenido editable desde el panel (murales marcados, reseñas, aliados, etc.) vive en MySQL.
El instalador `setup.php` crea las tablas y la semilla inicial **una sola vez**; después se borra.

---

## 4. Estructura del proyecto

```
aves-mc/
├── index.html              # Frontend completo (landing + admin)
├── logo.png                # Logo nav/footer (fondo transparente)
├── api.php                 # Backend JSON (?action=...)
├── db.php                  # PDO + helpers
├── config.php.example      # Plantilla (copiar a config.php en el servidor)
├── setup.php               # Instalador de un solo uso
├── .htaccess               # HTTPS, seguridad, DirectoryIndex
├── resend.php              # Envío transaccional vía Resend API
├── mail.php                # Correos HTML (Resend → SMTP → mail())
├── scripts/
│   ├── deploy.sh           # EN EL VPS: pull + rsync
│   ├── post-deploy.sh      # EN EL VPS: deploy + verificación (usa Actions)
│   ├── patch-config.php    # EN EL VPS: actualiza secrets en config.php
│   ├── test-mail.php       # EN EL VPS: prueba Resend (CLI)
│   ├── make-logo-transparent.mjs   # utilidad dev (opcional)
│   └── patch-index-logo.mjs
├── .github/workflows/deploy.yml   # GitHub Actions → SSH → post-deploy.sh
├── deploy/                 # Scripts alternativos (bare repo); no usar si ya usas GitHub Actions
├── GUIA-DESPLIEGUE.html    # Guía visual para el cliente (no se publica)
├── COMO-FUNCIONA.md        # Este documento (no se publica)
└── README.md               # Referencia técnica del código
```

---

## 5. Comandos — desarrollo local

```bash
# 1) MySQL local + config.php con credenciales locales
# 2) Servir la carpeta:
php -S localhost:8000
# 3) Una vez: http://localhost:8000/setup.php → Instalar → borrar setup.php
# 4) Abrir http://localhost:8000/
```

El correo con `mail()` normalmente no funciona en local; las solicitudes sí se guardan en MySQL.

---

## 6. Comandos — desplegar a producción

**El despliegue es automático** (una vez configurado GitHub Actions).

```bash
git add .
git commit -m "Descripción del cambio"
git push
```

Eso:
1. Sube el commit a GitHub (`main`).
2. GitHub Actions entra por SSH al VPS como `avesyflores_mzl`.
3. Ejecuta `~/avesyflores-src/scripts/deploy.sh`.
4. En ~10–30 segundos el sitio queda actualizado.

**Ver si el deploy funcionó:** GitHub → repo → pestaña **Actions**. Verde = ok (típico: **10–30 s**).

**Reintentar sin cambios de código:**
```bash
git commit --allow-empty -m "Trigger deploy"
git push
```

También puedes ir a **Actions → Deploy to VPS → Run workflow** (botón manual).

### Si GitHub Actions falla (respaldo manual)

A veces el workflow falla **sin llegar al VPS**. Errores típicos en la pestaña Actions:

| Mensaje | Significado |
|---|---|
| *The job was not acquired by Runner of type hosted…* | GitHub no tuvo runners disponibles (fallo de infraestructura). |
| *Internal server error* | Error interno de GitHub (reintentar más tarde). |
| Run en cola **7–15 min** y luego falla | Mismo problema: el job nunca arrancó. Revisa [githubstatus.com](https://www.githubstatus.com). |

**Eso no significa que el sitio esté roto.** Si Actions falla, despliega tú en el VPS:

```bash
ssh avesyflores_mzl@vps16389.dreamhostps.com

cd ~/avesyflores-src
git pull origin main
bash ~/avesyflores-src/scripts/deploy.sh
```

Verificación rápida:

```bash
git -C ~/avesyflores-src log -1 --oneline
ls ~/calleavesyflores.manizalescomparte.com/resend.php
php ~/avesyflores-src/scripts/test-mail.php
```

Deberías ver el commit reciente, que existe `resend.php`, y `OK: Resend aceptó el envío`.

> **Nota:** el `git push` desde tu PC sube el código a GitHub, pero **no sustituye** el paso de `git pull` + `deploy.sh` en el VPS si Actions no corrió.

### Cuando el push no dispara Actions

Si haces `git push` y en Actions **no aparece un run nuevo**, el código igual quedó en GitHub. Opciones:

1. **Deploy manual** en el VPS (comandos de arriba).
2. **Run workflow** manual en GitHub → Actions.
3. Confirmar que los secrets `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY` siguen configurados.

---

## 7. Datos de infraestructura (referencia)

| Cosa | Valor |
|---|---|
| Dominio | `calleavesyflores.manizalescomparte.com` |
| VPS | `vps16389.dreamhostps.com` |
| Usuario VPS | `avesyflores_mzl` |
| Ruta del código (git clone) en VPS | `/home/avesyflores_mzl/avesyflores-src` |
| Ruta publicada (web root) en VPS | `/home/avesyflores_mzl/calleavesyflores.manizalescomparte.com` |
| Logs Apache | `~/logs/calleavesyflores.manizalescomparte.com/https/error.log` |
| PHP en panel | 8.3 (recomendado) |
| BD MySQL (DreamHost panel) | Crear hostname `mysql.manizalescomparte.com` |
| Repo GitHub | `digitaldev-mc/calleavesyflores` |

### Secrets en GitHub (Settings → Secrets and variables → Actions)

| Secret | Valor para este proyecto |
|---|---|
| `VPS_HOST` | `vps16389.dreamhostps.com` |
| `VPS_USER` | `avesyflores_mzl` |
| `VPS_SSH_KEY` | Llave privada de despliegue (en `~/.ssh/authorized_keys` del usuario) |
| `RESEND_API_KEY` | *(opcional)* API key de Resend (`re_…`). Actions la escribe en `config.php` vía `patch-config.php` |
| `VPS_WORK_PATH` | *(opcional)* `/home/avesyflores_mzl/avesyflores-src` — el script ya usa esa ruta por defecto |

> Puedes reutilizar la misma `VPS_SSH_KEY` que Presupuesto **si** la clave pública correspondiente está en `~/.ssh/authorized_keys` de `avesyflores_mzl`. Si no, genera una llave nueva solo para este usuario.

---

## 8. Entrar al servidor manualmente

```bash
ssh avesyflores_mzl@vps16389.dreamhostps.com
```

Logs de errores:
```bash
tail -f ~/logs/calleavesyflores.manizalescomparte.com/http/error.log
```

Deploy manual (si GitHub Actions falla o no se disparó):

```bash
cd ~/avesyflores-src && git pull origin main && bash ~/avesyflores-src/scripts/deploy.sh
```

Editar credenciales (solo en el servidor; el deploy **no** sobrescribe este archivo):

```bash
nano ~/calleavesyflores.manizalescomparte.com/config.php
```

Valores mínimos de correo en producción:

```php
define('CORREO_DESTINO', 'manizalescomparte@gmail.com');
define('CORREO_ORIGEN', 'notificaciones@manizalescomparte.com');
define('CORREO_NOMBRE', 'La Calle de las Aves y las Flores');
define('RESEND_API_KEY', 're_…');   // dominio verificado en resend.com
define('SITE_URL', 'https://calleavesyflores.manizalescomparte.com');
```

Probar correo desde el VPS:

```bash
php ~/avesyflores-src/scripts/test-mail.php
```

Al enviar una cotización desde la web llegan **dos correos**: uno al equipo (`CORREO_DESTINO`) y copia al cliente.

---

## 9. Qué NO se sube a Git / qué no sobrescribe el deploy

- `config.php` — credenciales de producción (vive solo en el web root del VPS)
- `node_modules/`, `package.json` — utilidades locales de desarrollo
- `GUIA-DESPLIEGUE.html`, `README.md`, `COMO-FUNCIONA.md` — documentación

El script `deploy.sh` usa `rsync --exclude config.php` para **nunca** pisar la configuración del servidor.

---

## 10. Primer despliegue (checklist único)

Seguir en orden la primera vez; después solo `git push`.

### A. Base de datos (panel DreamHost)

1. **Websites → MySQL Databases** → crear BD, por ejemplo:
   - Database: `aves_y_flores`
   - User: `avesyflores_user` + contraseña segura
   - Hostname: `mysql.manizalescomparte.com`
2. Anotar hostname, nombre BD, usuario y contraseña.

### B. Repositorio GitHub

1. Repo en GitHub: `digitaldev-mc/calleavesyflores`.
2. En tu PC, dentro del proyecto:
   ```bash
   git init
   git add .
   git commit -m "Sitio inicial"
   git branch -M main
   git remote add origin git@github.com:digitaldev-mc/calleavesyflores.git
   git push -u origin main
   ```

### C. VPS — deploy key + clonar código

El VPS necesita su propia llave SSH autorizada en GitHub (error típico: `Permission denied (publickey)`).

**En el VPS** (como `avesyflores_mzl`):

```bash
rm -rf ~/avesyflores-src

ssh-keygen -t ed25519 -C "avesyflores_mzl deploy" -f ~/.ssh/id_ed25519_github -N ""

mkdir -p ~/.ssh
chmod 700 ~/.ssh
cat >> ~/.ssh/config << 'EOF'
Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/id_ed25519_github
  IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config

cat ~/.ssh/id_ed25519_github.pub
```

Copia la línea que empieza con `ssh-ed25519…`.

**En GitHub:** repo `calleavesyflores` → **Settings → Deploy keys → Add deploy key**
- Title: `vps16389 avesyflores_mzl`
- Key: pegar la clave pública
- Allow write access: **no** (solo lectura basta para `git pull`)

Luego en el VPS:

```bash
git clone git@github.com:digitaldev-mc/calleavesyflores.git ~/avesyflores-src
chmod +x ~/avesyflores-src/scripts/deploy.sh
```

### D. VPS — config.php en el web root

```bash
mkdir -p ~/calleavesyflores.manizalescomparte.com
cp ~/avesyflores-src/config.php.example \
   ~/calleavesyflores.manizalescomparte.com/config.php
nano ~/calleavesyflores.manizalescomparte.com/config.php
chmod 600 ~/calleavesyflores.manizalescomparte.com/config.php
```

Valores típicos:
```php
define('DB_HOST', 'mysql.manizalescomparte.com');
define('DB_NAME', 'aves_y_flores');
define('DB_USER', 'avesyflores_user');
define('DB_PASS', '…');
define('CORREO_ORIGEN', 'notificaciones@manizalescomparte.com');
```

### E. Primer rsync / deploy manual

```bash
bash ~/avesyflores-src/scripts/deploy.sh
```

### F. Instalador (una sola vez)

1. Abrir `https://calleavesyflores.manizalescomparte.com/setup.php`
2. Definir contraseña de admin (mín. 6 caracteres) → **Instalar**
3. Borrar `setup.php` del web root:
   ```bash
   rm ~/calleavesyflores.manizalescomparte.com/setup.php
   ```

### G. GitHub Actions

En el repo → **Settings → Secrets → Actions**, crear:

- `VPS_HOST` = `vps16389.dreamhostps.com`
- `VPS_USER` = `avesyflores_mzl`
- `VPS_SSH_KEY` = llave privada PEM

Probar: `git commit --allow-empty -m "Trigger deploy" && git push`

### H. Verificación

- [ ] https://calleavesyflores.manizalescomparte.com carga los murales
- [ ] Cotizador y PDF funcionan
- [ ] Login admin funciona
- [ ] GitHub Actions en verde

---

## 11. Roadmap / pendiente

- [ ] Dominio `manizalescomparte.com` verificado en Resend (DNS en Cloudflare).
- [ ] Rotar `RESEND_API_KEY` si alguna vez se expuso en chat o logs.
- [ ] SSL Let's Encrypt si aún no está activo en el subdominio.
- [ ] Respaldos periódicos de la BD MySQL.

---

*Última actualización: agosto 2026 — GitHub Actions + deploy manual de respaldo + Resend.*
