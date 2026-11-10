# Worklog — newDesk

## 2026-11-06 — Monitoreo: planificación y Fase 0

- Pedido: integrar el panel `monitoreo` (Go + React) a newDesk, igual en todo y con el mismo agente.
- Decisiones con el usuario: todo el alcance; reescribir en Laravel; estilos de monitoreo solo en el módulo; clientes y proyectos unificados (versión básica de RF-02/RF-03 en esta rama); login de equipo = newDesk, clientes = código por mail; migración con historial; links viejos redirigen. Hosting sin definir (P-25).
- Hecho: rama `feat/monitoreo`, inbox procesado, RF-13 con CA-13.01..25, ADR-0002, P-03 ✅ + P-25..P-28, permisos en la matriz, módulos en `arquitectura.md`, checklist en `tasks/todo.md`.
- Verificado en el código Go: cifrado de secretos (`internal/panel/secret/secret.go`: HMAC(clave,"monitoreo:encrypt"), AES-256-GCM, nonce 12, AAD "tabla.columna:id" con fallback sin AAD) y huella TLS (`tlsutil.Fingerprint`: SHA-256 del DER, hex minúsculas).
- Archivos: `docs/02-requerimientos/{README,RF-02-clientes,RF-13-monitoreo}.md`, `docs/03-gaps/preguntas-abiertas.md`, `docs/04-arquitectura/{arquitectura.md,adr/0002-…}`, `requerimientos-inbox/`, `tasks/`.

## 2026-11-06 — Monitoreo F1, F2a y F3

- F1: clientes (RF-02.01, CA-02.01) y proyectos (RF-03.01/03, CA-03.01, CA-03.03) básicos, `users.client_id`, roles Desarrollador y Cliente, permisos `monitoring.view/manage`, grupo de rutas `team` (CA-13.14).
- F2a (agente de backend, revisado): servers con token cifrado por columna (`EncryptedFor`), gateway curl con pin SPKI derivado de la huella Go, daemon `monitoring:poll`, métricas + retención, eventos Reverb, `/health` con poller, programas supervisor, importador de servers. Probado contra el agente real (fixtures) en local: pin correcto ok; huella o pin equivocados cortan antes de mandar el token (servidor espía: 0 bytes).
- Decisión: canal `monitoring.{companyId}` en vez de `monitoring` (multi-empresa).
- F3 (agente de front, revisado): dashboard, detalle con todas las pestañas, alertas y ABM de servers con la piel de monitoreo; comparación visual lado a lado con Playwright.
- Tests: back 77 ok (+1 que falla solo por falta de Redis local), front 63 ok, typecheck/lint/build ok.

## 2026-11-06 — Monitoreo F2b + pantallas de proyectos, clientes y reportes

- F2b (agente de backend, revisado): 15 reglas con umbrales y antirrebote del Go, estado en Cache con TTL, motor `ApplyAlertResults` (locks por server+regla, resumen de avalancha D-107), evento `alert`, chequeo de sitios (IP pública fijada con `CURLOPT_RESOLVE`, reintentos, 429 = arriba, vencimiento SSL), `GET alerts`, `GET servers/{id}/sites`, `GET inventory/projects`, retención (historial de sitios 30 d; alertas no se purgan, igual que el Go), importador de alertas y sitios.
- Decisión: el chequeo de sitios corre como job en la cola `monitoring` (en PHP el DNS y las esperas de 10 s bloquean y frenarían el poller).
- Pendiente para F7: `AlertChanged` se transmite en el momento; si Reverb cae, los listeners de ese evento no corren. En F7 el aviso a Discord/mail se separa de la transmisión. También separar el worker de `discord` (1 proceso, orden) del de `monitoring` (más procesos).
- Front (agente, revisado): Proyectos, Clientes, admin de proyecto, Reportes con chat, roadmap, ventanas flotantes y "Generar reporte", con código de proyecto agregado. Confirmación de borrar cliente corregida a "¿Borrar el cliente y sus usuarios?" (CA-02.01).
- Tests: back 124 ok (+1 Redis local), front 127 ok.

## 2026-11-06 — Monitoreo F4 y F5 (backend + front del portal)

- F5 front: portal de clientes (login por código, inicio, estado con historial, reportar, lista y chat) fuera del layout de newDesk, piel monitoreo con su propio tema; guards entre equipo y portal.
- F5 back: login por código para clientes (D-076/077/079/081/082/087/114), sesión 30 días (remember-me), grupo `/api/v1/portal` con middleware `client`, `/me` con `client`, canal `portal.client.{id}`, barrido de rutas (equipo vs cliente). El chequeo de activo ahora usa `canLogin()` (cliente inactivo = sus usuarios afuera) y corre antes del binding de modelos (no filtra existencia).
- F4 back: módulo `StatusPage` (componentes, regla de 10 min, períodos, uptime, incidentes y notas, tiempos de respuesta, carpetas, sugerencias, PageSpeed, RDAP, vulnerabilidades), endpoints `portal-admin/*` y del cliente, mails al cliente solo con `MONITORING_NOTIFY`, importador de clientes/proyectos/usuarios/historial con `import_mappings`.
- Decisiones: slug único por empresa y `login` reservado; mails de usuarios cliente en minúsculas; devs secundarios = miembros (P-29).
- Tests: back 213 ok (+1 Redis local), front 157 ok.

## 2026-11-06 — Monitoreo F6 (reportes)

- Módulo `Support`: reportes del cliente (multipart, chequeo en vivo dentro del presupuesto, foto de los servers D-050, cupos 5/h y 20/día en DB D-091, 200 MB de imágenes D-090, firma de bytes), chat con imágenes y notas internas (nunca al canal del cliente, CA-13.18), cola del equipo con contador, roadmap personal (409 si el orden quedó viejo), retención `support:retention` (imágenes 180 d, reportes 1 año, huérfanos), mail al equipo solo con `MONITORING_NOTIFY`, evento `ReportSubmitted` para Discord (F7), importador con `--storage-dir`.
- Fix transversal: `HttpResponseException` (respuesta ya armada, p. ej. 429 de un limiter) ya no se vuelve 500.
- Nota de deploy: timeouts de PHP-FPM/nginx ≥ 30 s (chequeo en vivo) y `upload_max_filesize`/`post_max_size` ≥ 16 MB.
- Tests: back 228 ok (+1 Redis local).

## 2026-11-06 — Monitoreo F7, F8 y runbook de corte

- F7: gateway de Discord (bot REST + mudo), cola `discord-priority`/`discord` con manejo de 429, notifier con el ruteo del Go (canal del proyecto vs `#resumen`, mención al principal, escape D-078), diff de vulnerabilidades por scan, avisos de reportes, vigilancia de `/api/errors`, resumen 9/18 h, heartbeat, endpoints de canales/miembros/prueba/creación y Discord ID de devs. `AlertChanged` pasó a evento de dominio y la transmisión a Reverb (`AlertBroadcast`) es aparte y best effort: los avisos no dependen de Reverb (test con Reverb fallando). Supervisor: `monitoring-worker` (2) y `monitoring-discord` (1).
- F8: servidor MCP `/mcp/monitoreo` con las 12 tools del Go (mismas descripciones, esquemas, instrucciones y `<datos_del_cliente>`), tokens personales Sanctum solo para `/mcp/*` + pestaña Tokens, reporte diario por socket unix (sin probar en Linux todavía).
- F9: runbook manual `docs/06-migracion/corte-monitoreo.md` (bloqueantes, variables, convivencia, día del corte, redirects nginx, verificación, vuelta atrás).
- Tests finales: back 273 ok + 1 skip (socket unix en Windows) + 1 que falla solo por falta de Redis local; front 160 ok; typecheck, lint y build ok.
- `/bug-report` no se pudo correr: newDesk no tiene URL de staging/prod todavía (no hay `.claude/bug-report.json`).

## 2026-11-10 — Unión de `main` (dev_green, R1) en `feat/monitoreo`

- Pedido: traer lo de dev_green y unificarlo con monitoreo, en la rama `feat/monitoreo` (no en main ni dev_green). Merge de `origin/main` e5047b9 en un worktree aparte (`newDesk-merge`), 32 conflictos.
- Decisiones (ADR-0002 §12): clientes/proyectos de main; monitoreo solo edita lo suyo (slug, color, logo, dominio, errores, Discord, portal activo, devs); un proyecto no se borra desde monitoreo (pedido del usuario); un solo sistema de tokens (Conexiones de IA) para `/mcp` y `/mcp/monitoreo`, 60/min por token; usuarios cliente fuera de todo lo del equipo (`User::team()`, `{user}`/`{clientUser}`, reset de contraseña, rol Cliente bloqueado y no `is_system`); Desarrollador con los módulos de Colaborador; preguntas P-25..P-29 de monitoreo → P-28..P-32.
- Importación: `monitoring:import --dry-run` muestra las parejas proyecto-monitoreo ↔ proyecto-desk y `--link=<slug>:<código>` las corrige; los proyectos cargados a mano solo reciben los campos del monitoreo (pedido: cargar clientes primero y vincular en una sesión en vivo con Playwright).
- Frontend: sidebar de main con una entrada "Monitoreo"; monitoreo abre a pantalla completa con su menú y "Volver a newDesk" al pie (pedido del usuario con captura). Usuario cliente → `/portal` antes del chequeo de 2FA.
- Tests: el `TestCase` de main congela el reloj; un test de monitoreo tomaba un `Cache::lock` de 1 s sin soltarlo y colgaba la suite (corregido); el admin de los tests de monitoreo pasa a `adminUser()` (2FA obligatorio de main).
- Falla en main sin la unión (no tocado, para el otro dev): `ReviewRegressionTest` "a person cannot move an old entry into the window by correcting it". `/health` sigue fallando local solo por falta de Redis.
- `origin/main` avanzó a eaea73c (UI) durante la unión: va en una segunda unión.
