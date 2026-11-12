# Puesta en marcha (E8)

Checklist para levantar newDesk en staging/producción y hacer la carga inicial. Rama de trabajo R1: `dev_green`.

## 1. Servidor

| Componente | Versión / nota |
|---|---|
| PHP | 8.3 con `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `gd` (WebP) — Imagick+libheif solo si se resuelve P-25 (HEIC) |
| MySQL | 8.x, `utf8mb4` |
| Redis | colas, caché, locks del cronómetro (`timer:user:*`), sesiones |
| Supervisord | **nunca crontab** (RNF-11) |
| Nginx | `backend/public` como root; SPA (`frontend/dist`) servida aparte o en el mismo host bajo `/` con fallback a `index.html` |
| HTTPS | obligatorio (`APP_URL=https://…`, `URL::forceHttps()` en prod) |
| Subidas | adjuntos de tareas hasta 20 MB (RF-04.09): `upload_max_filesize = 21M` y `post_max_size = 22M` en php.ini, `client_max_body_size 22m;` en Nginx. Sin esto el usuario ve un 413 en vez del mensaje claro. |
| Disco | `storage/app/private` guarda imágenes de descripciones y adjuntos: incluirlo en los backups (§7). |

## 2. Backend

```bash
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# Completar .env: APP_ENV=production, APP_DEBUG=false, APP_URL, FRONTEND_URL, DB_*, REDIS_*,
# SESSION_DOMAIN, SANCTUM_STATEFUL_DOMAINS, MAIL_MAILER=resend, RESEND_API_KEY,
# MAIL_FROM_ADDRESS=noreply@…, MAIL_REPLY_TO_ADDRESS, ERRORS_TOKEN=$(openssl rand -hex 32)
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan event:cache
php artisan supervisor:sync          # queue (default,mail), scheduler (timers:cut c/minuto, tasks:notify-due, hours:reconcile), reverb
```

Después de cada deploy: `php artisan supervisor:sync` (reinicia los workers; si no, siguen con código viejo).

## 3. Frontend

```bash
cd frontend && npm ci && npm run build   # sale en frontend/dist
```

El SPA llama a `/api`, `/sanctum` y `/mcp` en el mismo dominio (cookies de Sanctum). Si back y front van en dominios distintos, ajustar `SESSION_DOMAIN` y `SANCTUM_STATEFUL_DOMAINS`.

## 4. Observabilidad (`/bug-report`)

- `GET /api/health` → 200 con DB/caché/Redis; 503 si algo cae.
- `GET /api/errors` con `Authorization: Bearer $ERRORS_TOKEN`.
- En la máquina de desarrollo crear `.claude/bug-report.json` (está en `.gitignore`) con la URL de prod y el token.

## 5. Carga inicial

1. `php artisan newdesk:install --company="NexoSmart" --first-name=… --last-name=… --email=…` → crea empresa, roles base (Admin/Gestor/Colaborador), catálogos y estados de tarea, y el primer Admin. La contraseña generada se muestra una sola vez.
2. El Admin ingresa, **activa 2FA** (obligatorio, `/seguridad`).
3. Configuración → Empresa: nombre visible, logo, moneda, zona horaria, reglas de horas (días hacia atrás, timer olvidado, umbrales).
4. Configuración → Usuarios: invitar a cada persona con su rol (le llega un email para crear su contraseña; el link vence en 7 días).
5. Configuración → Catálogos / Roles: ajustar puestos, áreas, habilidades y permisos.
6. Clientes → Proyectos activos → **Cargar horas** con motivo (bolsa inicial) → agregar miembros.
7. Turnos: plantillas y grilla de la primera semana. Feriados nacionales del año (P-26).
8. Avisar al equipo: "Mi perfil → Conexiones de IA" para usar newDesk desde Claude Code.

## 6. Conectar Claude Code (cada persona)

En newDesk: **Mi perfil → Conexiones de IA → Generar token** (se muestra una vez). Luego:

```bash
claude mcp add --transport http newdesk https://<dominio>/mcp --header "Authorization: Bearer <token>"
```

Para un `.mcp.json` de proyecto, nunca commitear el token: usar `"Authorization": "Bearer ${NEWDESK_TOKEN}"` y exportar `NEWDESK_TOKEN`.

## 7. Backups (RNF-09)

Dump diario de MySQL con retención de 30 días y **restore probado** (programarlo como programa de Supervisord o en el proveedor; no crontab). Pendiente de definir destino (infra).

## 8. Apagar Worksuite

Solo después de: equipo completo usando newDesk ≥ 2 semanas, horas del período cargadas, y la decisión de migración histórica (E12, R2). La DB de Worksuite no se modifica: solo dump de lectura.
