# Plan — Asistente de IA (RF-13)

Spec: `docs/02-requerimientos/RF-13-asistente-ia.md`. Rama `dev_green`. Investigación del 2026-11-16 (abajo).

## Fases

### Fase 1 — Herramientas de gestión (RF-13.07) — sin IA todavía

Plan detallado (2026-11-16, skills `mcp-builder` + `mcp-server-dev` aplicados). Lo que ya había: 20 tools sobre
`NewDeskTool` (Template Method), `McpLookup` (resuelve + autoriza `view`), `McpPresenter`, tests por HTTP real
(`mcpCall`/`mcpResult`). Hoy "escritura" = tool sin `#[IsReadOnly]`; no hay forma de describirla sin ejecutarla.

**Decisiones de diseño**
- **Nombres:** se mantiene el estilo actual sin prefijo (`list_clients`, no `newdesk_list_clients`): Claude Code ya los ve
  como `mcp__newdesk__*` y los 20 existentes son contrato. Descripciones que dicen qué devuelven, qué NO hacen y cuál es la
  tool hermana (ej. `list_projects` = donde soy miembro ↔ `search_projects` = todos los que puedo ver).
- **Menos tools, mismo poder:** el reporte de horas va en una sola tool con `group_by` (proyecto / cliente / persona) en vez
  de tres; "bolsas por agotarse" es un filtro de `search_projects` y "vencidas del equipo" un filtro de `list_tasks`.
  Lectura y escritura siempre en tools separadas. Quedan 16 nuevas → 36 en total (ver riesgo abajo).
- **Escrituras en dos pasos (preparar → ejecutar)**, base nueva `NewDeskWriteTool extends NewDeskTool`:
  `prepare(Request, User): PreparedWrite` valida, resuelve y autoriza **sin escribir** y devuelve un resumen humano
  ("Cargar 20 h a bhn — motivo: Presupuesto #123"); `execute(PreparedWrite, User): Response` llama a la Action.
  En Fase 1 `handle()` hace los dos seguidos (comportamiento idéntico al de hoy). En Fase 2, con token de asistente,
  `handle()` guarda la propuesta con los argumentos crudos y al confirmar **vuelve a correr `prepare()`** (re-valida y
  re-autoriza con el rol de ese momento) y recién ahí `execute()`. Así no hace falta un metadato aparte: el tipo de la
  clase dice si escribe. Las 11 tools de escritura existentes se migran a esta base (mecánico, sus tests no cambian).
- **Reglas de validación compartidas web/MCP:** se extraen de los FormRequest a `Domain/<Módulo>/Support/<X>Rules`
  (`ClientRules::save()`, `ProjectRules::save($partial)`) y los usan el FormRequest y la tool. Cero reglas copiadas.
- **Queries nuevas mínimas:** `HoursReport::byClient()` y `HoursReport::byPerson()` junto a `overview()`/`project()`
  (misma base `entries()`, mismo período `ReportPeriod`, solo registros cerrados). Sin endpoint web en esta fase (no hay
  pantalla en RF-10); se prueban contra `overview()` para el mismo rango: total por cliente = total por persona = total
  por proyecto (base de CA-13.06).
- **Listados paginados** (`limit` default 25, máx 50, `offset`; respuesta con `total`, `has_more`, `next_offset`).
- **Annotations:** `#[IsReadOnly]` en lecturas; `#[IsDestructive]` en `remove_project_member`, `reject_extension_request`
  y `change_*_status`; `#[IsIdempotent]` en los `change_*_status`.
- **Datos:** `McpPresenter` suma `client()`, `person()`, `extensionRequest()`, `projectRow()`; nunca tarifas, teléfonos ni
  datos personales más allá de nombre, email, puesto y área (RF-12 Seguridad). Títulos/comentarios van como datos.

**Tools nuevas** (todas: módulo del rol + la misma policy/Gate que la web)

| Tool | Tipo | Dominio que usa | Autorización (= web) |
|---|---|---|---|
| `hours_report` (`group_by`: project/client/person, `from`, `to`) | lectura | `HoursReport::overview/byClient/byPerson` | módulo reports + `projects.manage` |
| `search_projects` (`running_out`, `status`, `client`, `search`) | lectura | `ProjectList` (`visibleTo`, `runningOut` = misma definición que Inicio) | módulo projects |
| `list_tasks` (`project`, `assignee`, `overdue`, `status`, `search`) | lectura | `TaskList` con `mine=false` | módulo projects + visibilidad de la query |
| `list_clients` (`status`, `search`) | lectura | `ClientList` | módulo clients + `ClientPolicy::viewAny` |
| `list_people` (`search`, `area`, `skill`) | lectura | `PeopleDirectory` | igual que `GET /people` |
| `team_shifts` (`from`, `to`, máx 31 días) | lectura | `TeamCalendar::audienceOf` + `__invoke` | módulo calendar + audiencia no vacía |
| `list_extension_requests` | lectura | `HourExtensionRequest::pending()` | `HourExtensionRequestPolicy::viewAny` |
| `create_client` / `update_client` | escritura | `SaveClient` | `ClientPolicy::create/update` |
| `change_client_status` | escritura | `ChangeClientStatus` | `ClientPolicy::update` |
| `create_project` / `update_project` | escritura | `CreateProject` / `UpdateProject` | `ProjectPolicy::create/update` |
| `change_project_status` | escritura | `ChangeProjectStatus` (reabrir pide `projects.reopen` en la action) | `ProjectPolicy::update` |
| `add_project_member` / `remove_project_member` | escritura | `AddProjectMember` / `RemoveProjectMember` | `ProjectPolicy::manageMembers` |
| `approve_extension_request` (horas, motivo) / `reject_extension_request` | escritura | `ApproveHourExtension` / `RejectHourExtension` | `HourExtensionRequestPolicy::approve/reject` |

**Pasos**
- [x] 1. `NewDeskWriteTool` + `PreparedWrite` (value object: tool, argumentos validados, resumen) y migrar las 11 tools de escritura existentes; suite MCP actual en verde sin tocar sus tests.
- [x] 2. `ClientRules` / `ProjectRules` extraídas de los FormRequest (tests web de clientes y proyectos en verde).
- [x] 3. `HoursReport::byClient()` / `byPerson()` + tests de la query (sumas cruzadas con `overview()`, entradas abiertas y borradas excluidas, otra empresa excluida).
- [x] 4. Lecturas: `hours_report`, `search_projects`, `list_tasks`, `list_clients`, `list_people`, `team_shifts`, `list_extension_requests` + presenter.
- [x] 5. Escrituras de clientes y proyectos (6 tools).
- [x] 6. Ampliaciones (2 tools de escritura).
- [x] 7. `NewDeskServer`: registrar (paginación ≥ 36), actualizar `#[Instructions]` con las tools de gestión; RF-12/RF-13 docs (tabla de tools).
- [x] 8. Tests (un archivo por grupo en `tests/Feature/Mcp/`):
  - paridad web/MCP en escrituras: mismo resultado en DB, misma `Activity` (source `mcp`), **mismo mensaje de error** que la web ante la misma regla (ej. reabrir sin `projects.reopen`, cliente con CUIT inválido, ampliación ya resuelta);
  - permisos: Colaborador recibe el error de rol y no cambia nada; módulo apagado → mensaje de módulo; otra empresa → "no existe o no tenés acceso";
  - lecturas: mismos números que el endpoint web equivalente (`/reports/hours`, `/projects?running_out=1`, `/clients`, `/team-calendar`, `/extension-requests`);
  - arquitectura: cada tool registrada es `#[IsReadOnly]` **o** `NewDeskWriteTool` (ninguna escritura sin el paso preparar); `prepare()` de cada escritura no toca la DB.
- [x] 9. `vendor/bin/pint`, `php artisan test` (una suite por vez), prueba manual con `claude mcp` contra el server local (lista de tools y 3-4 llamadas reales con admin), review independiente con agente, commit + push a `dev_green`.

**Riesgos / a decidir**
- 37 tools ≈ 4-6k tokens de esquemas por turno. Aceptable para `claude -p`; si en la eval de Fase 2 elige mal, se agrupan
  (ej. miembros en una sola tool) antes de pasar a search+execute.
- `hours_report` por persona muestra horas de otros: queda detrás de `projects.manage` (igual que el reporte por proyecto).
- Decidido con Juani: sí van endpoints web `GET /reports/hours/clients` y `/reports/hours/people` (misma query, sin pantalla todavía).

**Revisión Fase 1 (2026-11-16)**
- Hecho: 17 tools nuevas (37 en total), `NewDeskWriteTool` + `PreparedWrite` con las 11 escrituras previas migradas,
  `ClientRules`/`ProjectRules`, `HoursReport::byClient/byPerson` + 2 rutas web, `PendingExtensionRequests` (query que
  estaba en el controller), `McpPage` (`?int $page` en ClientList/ProjectList/TaskList/PeopleDirectory), guards
  `EnsureProjectHasBag` y `PendingExtensionLock::ensurePending` usados por la acción y por `prepare()`.
- Review independiente (agente): sin agujeros de autorización ni escrituras en `prepare()`. Corregido lo que marcó:
  `create_task` con cronómetro exige el módulo de horas como la web; `prepare()` anticipa "proyecto interno" y "pedido ya
  resuelto"; entrada normalizada como la web (trim + '' → null) en `NewDeskTool`; `stop_timer` valida el comentario;
  `byClient/byPerson` excluyen proyectos borrados.
- Verificado: `php artisan test` 282/282 (MCP 50/50, incluye contrato "toda tool es lectura o escritura preparable" y
  "prepare no escribe; execute sí"). Prueba real: `claude -p` (2.1.325) con `--mcp-config` + `--strict-mcp-config` +
  `--allowedTools "mcp__newdesk__*"` contra el server local con token efímero de admin: llamó `hours_report`,
  `search_projects`, `list_tasks`, `list_extension_requests` y respondió con los números del sistema (USD 0,20, 6 turnos).
  `${NEWDESK_TOKEN}` en el JSON de `--mcp-config` se expande desde el entorno → el token no se escribe en disco (Fase 2).

### Fase 2 — Núcleo del asistente con `claude -p` + chat web (RF-13.01–05, 09, 10)
Motor: **Claude Code en modo no interactivo (`claude -p`)** lanzado por el backend. **No** se usa la API de Anthropic ni el SDK de PHP.
- [ ] Permiso `assistant.use` (solo Admin por defecto; migración que lo otorga al rol Admin existente).
- [ ] Token de asistente efímero por pedido: token Sanctum del usuario que pregunta con ability `assistant`, vence en minutos, se revoca al terminar. El MCP aplica los permisos de ese usuario (RF-12.03).
- [ ] Escrituras con ese token → tabla `assistant_proposals` (acción, payload validado, usuario, canal, estado) y la tool responde "propuesta #N, esperando confirmación". Confirmar/Cancelar desde la web o Discord ejecuta la **misma Action de dominio** como ese usuario. Ninguna escritura sin humano.
- [ ] `AssistantRunner` (Support) arma y corre el proceso (Symfony Process) en un job de la cola `assistant` (concurrencia 1–2, timeout):
      `claude -p "<pedido>" --output-format json --model <ASSISTANT_MODEL> --mcp-config <json con el MCP de newDesk, URL local + token por variable de entorno> --strict-mcp-config --allowedTools "mcp__newdesk__*" --permission-mode <el que niegue todo lo demás> --append-system-prompt "<rol, voseo, reglas>" --max-budget-usd <tope por pedido> [--resume <session_id de la conversación>]`
      en un directorio de trabajo vacío propio, con `--bare` / `--setting-sources` para que no cargue CLAUDE.md, hooks ni plugins del servidor. Verificar cada flag con `claude --help` de la versión instalada.
- [ ] Conversaciones: guardar el `session_id` de Claude Code por conversación para `--resume`; mensajes y costo (`total_cost_usd` del JSON) persistidos → tope mensual (RF-13.09) y rate limit por usuario.
- [ ] Autenticación de Claude Code en el servidor: cuenta/clave de la empresa configurada para el usuario del sistema que corre los workers (nunca en el repo). Confirmar que el tipo de cuenta permite este uso automatizado.
- [ ] Reportes en archivo (Excel/PDF) generados por el backend desde las mismas queries (tool que devuelve un link de descarga).
- [ ] Pantalla "Asistente" (skills de diseño), con tarjetas de confirmación, tablas y descargas; estado "pensando…" mientras corre el job.
- [ ] Eval con pedidos reales del dueño antes de darlo por bueno (set de pedidos + respuestas esperadas, corrido contra `claude -p`).

### Fase 3 — Resumen semanal en texto (RF-13.06)
- [ ] Job lunes 9:00 (hora de la empresa): arma los números con queries existentes → `claude -p` (sin tools, solo redactar a partir de los datos) → Discord + mail a Admins + chat web.
- [ ] Sin IA disponible (tope alcanzado / caída): sale el resumen con números en plantilla fija.

### Fase 4 — Discord (RF-13.08)
- [ ] App de Discord (coordinar con `feat/monitoreo`, que ya manda avisos a Discord: misma app/configuración).
- [ ] Endpoint de Interactions en Laravel (verificación de firma Ed25519), respuesta diferida + cola (límite de 3 s de Discord).
- [ ] `/newdesk vincular`, `/newdesk <pedido>`, botones Confirmar/Cancelar (solo quien pidió), adjuntos.
- [ ] Fase 2 de Discord (después): DMs / texto libre con un proceso gateway bajo Supervisord.

## Investigación (2026-11-16) — resumen

- **Por qué asistente propio y no solo claude.ai:** permisos del usuario logueado, confirmación de escrituras en nuestra UI, Discord, funciona sin exponer el MCP a internet ni dar cuenta de Claude a cada uno. claude.ai (conector org + tareas programadas en la nube) queda como complemento: requiere OAuth en el MCP (RF-12.08) y plan Team.
- **Decisión de Juani (2026-11-16): el motor es `claude -p`** (Claude Code headless), no la API de Anthropic. Ventajas: reusa el MCP tal cual (mismas tools y permisos), sin SDK nuevo en el backend.
- **Managed Agents** (Anthropic hostea loop + agenda): viable — MCP con token en vault (`static_bearer`), MCP pide aprobación por defecto (`always_ask`), deployments con cron. Descartado: el asistente necesita UI propia y Discord igual.
- **Skills/plugins para construirlo (instalados 2026-11-16):** `claude-api`, `xlsx`/`pdf`/`dataviz` (ya estaban); skill `mcp-builder` (oficial anthropics/skills, en `~/.agents/skills`: diseño de tools + evals); plugin oficial `mcp-server-dev` (deploy remoto, auth, patrones de tools). `laravel-best-practices` NO existe en el marketplace oficial (dato erróneo del relevamiento). Discord: API oficial (Interactions), sin dependencias de terceros.
- **Plugin oficial `discord` (no instalado):** puente Discord ↔ una sesión de Claude Code con MCP. Sirve para prototipar en un día (bot → Claude Code con el MCP de newDesk), pero todos actúan con el token del dueño de la sesión, una sola conversación compartida y depende de una PC con Claude Code abierto: no reemplaza RF-13.08.
- **Plugin oficial `mcp-tunnels` (no instalado):** túnel de Anthropic para exponer un MCP privado a claude.ai sin abrirlo a internet; útil si se hace RF-13.11.
- **Riesgos:** datos sensibles (clientes, horas por persona) fuera del sistema → solo canal/mails configurados por Admin; costo → tope mensual; prompt injection desde datos (títulos/comentarios) → las escrituras siempre pasan por confirmación humana.
