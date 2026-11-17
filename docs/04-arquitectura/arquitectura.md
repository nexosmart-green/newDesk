# Arquitectura

## Stack (estándar NexoSmart — ver ADR-0001)

| Capa | Tecnología |
|---|---|
| Backend | **Laravel 12** (API JSON `{ data, message, errors }`), PHP 8.3+ |
| Frontend | **React 19 + Vite + TypeScript strict** (SPA: app interna, sin SSR) |
| DB | MySQL 8 |
| Cache / colas / locks | Redis |
| Tiempo real | Laravel Reverb (WebSocket) — cronómetro y cortes en vivo |
| Mail | Resend, vía cola, 1/s |
| Procesos | Supervisord (`supervisor:sync`): queue worker, scheduler, Reverb, ingesta cerradura, `monitoring-poller` y `monitoring-worker` (RF-13) |
| Auth | Laravel Sanctum (cookie SPA) + 2FA; tokens personales Sanctum para MCP |
| MCP | `laravel/mcp` (Streamable HTTP en `/mcp`) |
| Tests | PHPUnit/Pest (backend), Vitest + RTL, Playwright E2E |

Un **monolito modular**, no microservicios: equipo chico, un solo dominio, consistencia transaccional crítica en la bolsa de horas.

## Módulos (backend `app/Domain/*`)

```
Identity      usuarios, roles y permisos, invitaciones, 2FA, reset de contraseña, personas activas de la empresa
People        legajo/perfil, foto, habilidades, tarifas, catálogos (puestos, áreas, tipos de empleo), directorio
Companies     alta de empresa, configuración (zona horaria, ventanas, umbrales), logo
Clients       clientes
Projects      proyectos, miembros, visibilidad
Tasks         tareas, estados, categorías, etiquetas, checklist, comentarios, adjuntos/imágenes, códigos
TimeTracking  timers, registros, bolsa de horas (asignaciones, ampliaciones), cortes automáticos, reportes, reloj del día (RF-05.19), Mis horas (RF-05.20)   ⭐
Scheduling    turnos, plantillas, turnos fijos, feriados, calendario de equipo, feed iCal
Notifications notificaciones in-app + email, preferencias, destinatarios
Dashboard     tablero de inicio (solo lecturas: Queries/HomeDashboard)
Mcp           tokens personales MCP (emisión, revocación, listado). Las tools viven en app/Mcp/
Shared        empresa por modelo, auditoría (RecordActivity / ActivityFeed), BusinessRuleViolation, DateRange, Duration
Attendance    marcas crudas, mapeo, jornadas           (R2)
Leaves        licencias                                (R2)
Integrations/AccessControl  adaptadores de cerradura   (R2)
Monitoring    servidores, poller de agentes, alertas, Discord, reporte diario   (RF-13, ADR-0002)
StatusPage    componentes, uptime, incidentes, PageSpeed, portal de clientes    (RF-13)
Support       reportes de clientes, chat, roadmap                              (RF-13)
```

Cada módulo usa las mismas carpetas (`Actions/ Queries/ Support/ Models/ Policies/ Enums/ Events/ Listeners/ Contracts/`);
qué va en cada una está en `convenciones-de-codigo.md`.

## HTTP y MCP

- **API REST** `/api/v1` (`routes/api.php` + `routes/api/<modulo>.php`): `auth:sanctum` (cookie SPA) → `active`
  (`EnsureUserIsActive`: un usuario dado de baja pierde el acceso al instante) → `RejectMcpTokens` (los tokens
  personales solo sirven en `/mcp`) → `throttle:api`; por grupo `module:<clave>` (`EnsureModuleAccess`, módulo
  apagado para el rol) y `two-factor` (`EnsureTwoFactorWhenRequired`, Admin sin 2FA solo llega a /me y al alta de 2FA).
  Global: `RemoveStackHeaders` (RNF-05). Fuera de auth: `/api/health` y `/api/errors` (`/bug-report`).
- **MCP** `/mcp` (`routes/ai.php`): `auth:sanctum` (token personal) → `AuthorizeMcpToken`. `NewDeskServer` + tools
  `<Verbo><Sustantivo>Tool` sobre la base `NewDeskTool` (Template Method). Mismas actions, queries y policies que la web.
- **Envelope** `{ data, message, errors }` en todo: resources que extienden `ApiResource`, `ApiResponse::ok/error`, y el
  render de excepciones en `bootstrap/app.php` (validación → `errors`; `BusinessRuleViolation` → `message` + `data.code`
  y `errors.<field>` si nombra un campo; Policy denegada → 403 con su mensaje y `data.code`, o 404).
## Monitoreo (RF-13, ADR-0002)

- `monitoring:poll` (supervisord `monitoring-poller`): daemon que consulta a cada `monitor-agent` por HTTPS con pin de clave pública (`app/Infrastructure/Agent/CurlAgentGateway`), guarda `monitoring_server_snapshots` y `monitoring_metrics_raw`, y emite por Reverb en `private-monitoring.{companyId}` (`server.status`, `snapshot`, `metrics`, `alert`).
- Señales web → daemon por Cache (`PollerSignals`): recargar servers, refrescar sección, latido del modo en vivo (`POST /api/v1/monitoring/servers/{id}/live`), tick que mira `/health`.
- API espejo del Go en `/api/v1/monitoring/*` (equipo) y `/api/v1/portal/*` (clientes). Front en `frontend/src/features/monitoreo` con la piel bajo `[data-skin="monitoreo"]`.
- `monitoring:import` lee la base `monitoreo` (conexión `monitoreo_legacy`, sesión read-only) y conserva IDs. Reusa los clientes y proyectos que newDesk ya tiene (`--dry-run` muestra las parejas, `--link=<slug>:<código>` las corrige).
- Clientes y proyectos son los de RF-02/03: monitoreo solo agrega columnas al proyecto (`Project::STATUS_PAGE`) y los usuarios del portal (`users.client_id`, rol Cliente, `User::team()` los deja fuera del equipo).
- Dos servidores MCP con el mismo token personal (Conexiones de IA): `/mcp` (`NewDeskServer`) y `/mcp/monitoreo` (`MonitoreoServer`, además pide `monitoring.view`), los dos detrás de `AuthorizeMcpToken`.
- Frontend: una entrada "Monitoreo" en el sidebar del shell; adentro, el layout y la piel de monitoreo. `/portal` es un árbol aparte para usuarios cliente.

## Puntos de diseño críticos

1. **Timer en servidor.** `time_entries` con `ended_at NULL` = timer activo. Índice único parcial lógico: un usuario no puede tener dos `ended_at NULL` (se garantiza con lock por usuario en Redis + verificación en transacción, porque MySQL no tiene índices parciales: columna generada `active_user_id = IF(ended_at IS NULL, user_id, NULL)` con UNIQUE).
2. **Bolsa por ledger.** `hour_allocations` solo inserta. Disponibles se calcula; se cachea en `projects.consumed_seconds` actualizado en la misma transacción que cierra un registro (y se reconcilia con un job nocturno que alerta si difiere).
3. **Corte automático.** Job cada minuto: proyectos con timers activos donde `bolsa − consumido_cerrado − Σ(now − started_at activos) ≤ 0` → cerrar timers recortando el último tramo; evento por WebSocket.
4. **Iniciar timer** = transacción con `SELECT … FOR UPDATE` sobre el proyecto (regla R-2).
   **Reloj del día** (RF-05.19): `day_clocks` (≤1 sin repartir por persona, UNIQUE `unsplit_user_id`). Repartir = una transacción que bloquea el día y después los proyectos por id (R-6), crea las tareas nuevas como savepoint y escribe registros `source=day_clock`; si deja la bolsa en negativo, los ▶ que corrían quedan `uncapped` (ni corte por bolsa ni recorte).
5. **Ingesta de cerradura** desacoplada: adaptador por proveedor → tabla `access_events_raw` (idempotente por `(device_id, external_id)` o hash) → normalizador → jornadas. La caída del proveedor nunca rompe el resto del sistema.

6. **Una sola capa de reglas.** Controllers HTTP y tools MCP son adaptadores delgados sobre los mismos servicios/actions de dominio (`StartTimer`, `StopTimer`, `CreateTask`, `AllocateHours`…) + mismas policies (`ProjectPolicy::allocateHours`, `HourExtensionRequestPolicy`, `TimeEntryPolicy::completeComment`). Prohibido poner reglas de negocio o chequeos de permiso ad hoc en un controller o en una tool.
7. **Cortes automáticos** = una action por causa (`CutTaskTimers`, `CutProjectTimers`, `CutUserTimer`, `CutExhaustedBagTimers`, `CutForgottenTimers`) sobre un único `Support/TimerCutter` (lock del proyecto, más viejo primero, re-chequeo R-3).
8. **Fechas locales → UTC.** Lo que tipea la persona es local a su zona; se convierte en `LocalTimeRange` / `LocalDateRange` / `ReportPeriod`. Rangos de días con tope por pantalla: `DateRange` (error en el campo `to`).

## Ver

- `modelo-de-datos.md`
- `adr/`
