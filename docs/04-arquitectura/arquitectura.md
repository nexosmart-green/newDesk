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
Identity      usuarios, roles, perfil, catálogos (puestos, áreas, habilidades)
Clients       clientes y contactos
Projects      proyectos, miembros, actividad
Tasks         tareas, estados, comentarios, adjuntos, secuencia de códigos
TimeTracking  timers, registros, bolsa de horas (asignaciones), cortes, reloj del día (RF-05.19)   ⭐
Scheduling    turnos, plantillas, feriados, feed iCal
Attendance    marcas crudas, mapeo, jornadas           (R2)
Leaves        licencias                                (R2)
Notifications notificaciones in-app + email
Mcp           servidor MCP (laravel/mcp): tools finas que llaman a los servicios de dominio; auth Sanctum token
Integrations/AccessControl  adaptadores de cerradura   (R2)
Monitoring    servidores, poller de agentes, alertas, Discord, reporte diario   (RF-13, ADR-0002)
StatusPage    componentes, uptime, incidentes, PageSpeed, portal de clientes    (RF-13)
Support       reportes de clientes, chat, roadmap                              (RF-13)
```

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

6. **Una sola capa de reglas.** Controllers HTTP y tools MCP son adaptadores delgados sobre los mismos servicios/actions de dominio (`StartTimer`, `StopTimer`, `CreateTask`, `AllocateHours`…) + mismas policies. Prohibido poner reglas de negocio en un controller o en una tool.

## Ver

- `modelo-de-datos.md`
- `adr/`
