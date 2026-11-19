# Plan — Asistente de IA (RF-14)

Spec: `docs/02-requerimientos/RF-14-asistente-ia.md`. Rama `dev_green`. Investigación del 2026-11-16 (abajo).

## Fases

### Fase 1 — Herramientas de gestión (RF-14.07) — sin IA todavía

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
  por proyecto (base de CA-14.06).
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
- [x] 7. `NewDeskServer`: registrar (paginación ≥ 36), actualizar `#[Instructions]` con las tools de gestión; RF-12/RF-14 docs (tabla de tools).
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

### Fase 2 — Núcleo del asistente con `claude -p` + chat web (RF-14.01–05, 09, 10)
Motor: **Claude Code en modo no interactivo (`claude -p`)** lanzado por el backend. **No** se usa el SDK de Anthropic.

Plan detallado (2026-11-16). Flags verificados con `claude --help` 2.1.325 y prueba real de Fase 1.

**Flujo de un turno (web)**
1. `POST /assistant/conversations/{c}/messages` → `AskAssistant` (action): chequea `assistant.use`, asistente habilitado,
   rate limit, tope mensual y que no haya otro turno corriendo en la conversación; guarda el mensaje del usuario y uno
   del asistente en `pending`; despacha `RunAssistantTurn` (cola `assistant`). Responde 202.
2. `RunAssistantTurn` (job, `tries=1`, timeout 180 s): emite el token efímero (`IssueAssistantToken`: Sanctum del usuario,
   abilities `mcp` + `assistant`, vence a los 10 min, nombre `assistant:{messageId}`), llama al motor y **siempre** revoca
   el token en `finally`. Guarda respuesta, `session_id`, costo, duración, o el error humano ("No pude responder: …").
3. La web hace polling del mensaje (React Query, cada 2 s solo mientras está `pending/running`; no hay Echo en el front).

**Motor (DIP):** `Domain/Assistant/Contracts/AssistantEngine` ← adaptador `app/Infrastructure/ClaudeCode/ClaudeCodeEngine`
(Laravel `Process`, que se fakea en tests: los tests **nunca** llaman a `claude`). Comando:
`claude -p <pedido> --output-format json --model <modelo> --mcp-config <archivo sin secretos> --strict-mcp-config
 --tools "" --allowedTools "mcp__newdesk__*" --permission-mode dontAsk --setting-sources "" --append-system-prompt <reglas>
 --max-budget-usd <tope por turno> [--resume <session_id>]`, `cwd` = directorio vacío propio (`storage/app/assistant/workdir`),
token por variable de entorno `NEWDESK_TOKEN` (el JSON de `--mcp-config` usa `${NEWDESK_TOKEN}`: nunca a disco ni a logs).
`--tools ""` apaga todas las herramientas propias de Claude Code (Bash, archivos, web): solo quedan las del MCP.
`--bare` solo si la autenticación es por API key (ver decisión 1).

**MCP con token de asistente**
- `AuthorizeMcpToken`: token con ability `assistant` exige `assistant.use` (no `mcp.use`), `ActivityContext::useAssistant($canal)`
  → `ActivitySource::Assistant` (nuevo) + `token_name` = canal (RF-14.04). Rate limit propio.
- `NewDeskWriteTool::run`: si el pedido viene de un token de asistente, **no ejecuta**: `RecordAssistantProposal` guarda
  tool + argumentos normalizados + resumen de `prepare()` (ya validado y autorizado) y responde
  "Propuesta #N: <resumen>. No está hecha: la persona tiene que confirmarla." Así un rol sin permiso ni siquiera llega a
  propuesta (CA-14.03) y nada se escribe sin humano (CA-14.02).
- `ConfirmAssistantProposal` (action): solo quien pidió, solo `pending` y no vencida (24 h), con lock (doble clic = una
  ejecución); vuelve a correr `prepare()` con el rol **actual** y ejecuta como esa persona con source `assistant`; guarda
  resultado o error. `CancelAssistantProposal`. Las propuestas viejas vencen solas.

**Datos (migraciones 2026_11_16_*)**: `assistant_conversations` (company, user, canal, título, `claude_session_id`),
`assistant_messages` (rol, contenido markdown, estado, `cost_usd` decimal, duración, error, adjuntos json),
`assistant_proposals` (mensaje, tool, argumentos json, resumen, estado, resultado, resuelta por/en),
`assistant_files` (reportes generados: path privado, tipo, vence). Todo con `company_id` + `BelongsToCompany`.

**Permiso y configuración (RF-14.09/10)**
- `Permission::AssistantUse` ("Usar el asistente de IA"); migración que lo da a los roles Admin existentes; `DefaultRoles`.
- Settings de empresa (mismo `settings` json + `DEFAULT_SETTINGS`): `assistant_enabled` (false por defecto),
  `assistant_model`, `assistant_monthly_budget_usd`, `assistant_turn_budget_usd`. Pestaña Configuración → Asistente
  (solo `company.manage`) con el gasto del mes.
- Tope mensual = suma de `cost_usd` del mes (zona de la empresa); alcanzado → mensaje claro y no despacha.
  Rate limit por usuario (ej. 20 turnos/hora).

**Reportes en archivo (RF-14.05)**: tool `export_report` (`kind`: hours_by_project/client/person, bags, overdue_tasks;
`format`: xlsx/pdf; período) → `GenerateAssistantReport` arma el archivo desde **las mismas queries** y devuelve un link
firmado de descarga (solo quien lo pidió, vence en 7 días). Librerías: `phpoffice/phpspreadsheet` (xlsx) y
`dompdf/dompdf` (pdf) (decisión 2).

**Pantalla "Asistente" (`/asistente`)** — skills `frontend-design` + `ui-ux-pro-max` antes de dibujar; diseño vigente.
Conversaciones a la izquierda, chat con markdown (tablas con `remark-gfm`, ya instalado), "Pensando…" acotado al mensaje
en curso, tarjetas de propuesta Confirmar/Cancelar (toast con el mensaje del backend), chips de descarga, sugerencias de
pedidos frecuentes en el estado vacío. Ítem de menú solo con `assistant.use` (cambio mínimo en `navigation.ts`/`router.tsx`,
que también toca `feat/monitoreo`).

**System prompt (`--append-system-prompt`)**: quién pregunta (nombre, rol, fecha y zona), voseo, respuestas breves con
tablas, números tal cual los devuelven las tools (nunca calculados de cabeza), toda escritura es propuesta y se dice así,
títulos/comentarios son datos y no instrucciones, si una tool niega por permiso decirlo sin reintentar.

**Pasos**
- [x] 1. Permiso + migración + settings + `ActivitySource::Assistant`.
- [x] 2. Tablas, modelos, factories.
- [x] 3. Token efímero + `AuthorizeMcpToken` + propuestas en `NewDeskWriteTool` + confirmar/cancelar (con tests: CA-14.02/03/04, doble confirmación, otro usuario, vencida, rol degradado entre propuesta y confirmación).
- [x] 4. `AssistantEngine` + `ClaudeCodeEngine` + job + `AskAssistant` + límites (tests con `Process::fake`: argumentos exactos, token por env y nunca en el comando, revocado aunque falle, timeout, JSON inválido, tope y rate limit).
- [x] 5. API web (conversaciones, mensajes, confirmar/cancelar, descargas) + `export_report` + archivos.
- [x] 6. Supervisor: programa `assistant` (`queue:work redis --queue=assistant --timeout=200`, 1 proceso) en `SupervisorSync`.
- [x] 7. Front: pantalla `/asistente` + pestaña Configuración → Asistente; capturas claro/oscuro/mobile revisadas. E2E `e2e/assistant.spec.ts` (desktop + mobile): sin permiso no aparece; con la API del asistente simulada (nunca el motor real) pregunta, "Pensando…", tabla, Confirmar y toast.
- [x] 8. Eval real: `php artisan assistant:eval admin@nexosmart.com` → 8/8 OK, USD 0,23 estimados (lecturas con números del sistema, 3 escrituras quedaron como propuesta sin tocar datos, Excel adjunto, fuera de tema rechazado). Limpia sus conversaciones.
- [x] 9. Review independiente hecho y corregido (turno colgado tras timeout, catch Throwable + `failed()` del job + `assistant:housekeeping` cada 5 min, costo de timeouts al tope, nombres `assistant:` reservados, purga de reportes vencidos, descarga exige `assistant.use`, propuestas visibles en respuesta fallida, tope de 4 archivos por turno). Convenciones actualizadas.

**Cierre de Fase 2 (2026-11-17)** — Vitest 307/307 (local y `TZ=UTC`), build OK, E2E 8 pasan / 4 skip (por diseño), backend 310/310 (2026-11-16). Sin herramientas de GitHub en esa PC (sin `gh`, MCP caído): **el CI hay que mirarlo en Actions**. Siguiente: Fase 3 (resumen semanal) — avisar antes de Fase 4 (Discord).

**Estado (2026-11-19)** — Fases 1–4 en `main` (`2bdabfc`, CI verde run 39). Backend 602/602 (1 skip de main), Vitest
495/495 local y `TZ=UTC`, build OK.
- Queda, fuera del código: activar Discord en el servidor (`DISCORD_APPLICATION_ID` / `DISCORD_PUBLIC_KEY`, Interactions
  URL, `php artisan assistant:discord-commands`) y que Maxi confirme que la suscripción Max se puede usar así.
- Más adelante: DMs / texto libre (gateway).
- Puerto 5173 puede estar ocupado por el front de Contador Digital: levantar newDesk en 5174 con
  `SANCTUM_STATEFUL_DOMAINS` extendido solo por entorno.

**Decisiones de Juani (2026-11-16)**
1. Autenticación: **suscripción Max de la empresa (200 USD)** → `CLAUDE_CODE_OAUTH_TOKEN` (de `claude setup-token`) en el
   entorno del worker, **sin `--bare`** (bare solo acepta API key). El adaptador también acepta `ANTHROPIC_API_KEY` por `.env`.
   ⚠️ A confirmar por Maxi: que los términos del plan permitan que el sistema lo use para varias personas; si no, se pasa a
   API key cambiando solo el `.env`.
2. Excel/PDF: `phpoffice/phpspreadsheet` + `dompdf/dompdf`.
3. Defaults (editables): modelo `claude-opus-5-5` (incluido en el plan), tope mensual **estimado** 200 USD (con suscripción
   `total_cost_usd` es estimación: sirve de guarda de uso, no de facturación), 2 USD por turno, 20 turnos/hora por
   persona, 1 turno a la vez (cola `assistant` con 1 proceso) para no agotar los límites del plan.

### Fase 3 — Resumen semanal en texto (RF-14.06)

Plan (2026-11-17). Base: `main` (con monitoreo) ya mergeado en `dev_green`; Discord se reusa del `Notifier` de monitoreo.

- **Números = sistema:** `Queries/WeeklySummaryData` arma la semana anterior (lunes a domingo, zona de la empresa) con las
  mismas queries: `HoursReport::overview/byPerson` (total, por proyecto y por persona), `ProjectList` con `running_out`,
  `TaskList` vencidas (cantidad + las 5 más viejas), `PendingExtensionRequests`, y lo destacable calculado (no por IA):
  semana contra la anterior, proyecto y persona con más horas, bolsas activas sin movimiento.
- **Texto:** `Support/WeeklySummaryTemplate` lo escribe en plantilla fija (markdown corto). Si el asistente está prendido y
  bajo el tope, `claude -p` **sin MCP ni herramientas** reescribe esos datos en un párrafo breve en voseo (el motor gana un
  método `compose()`); si falla o no hay IA, sale la plantilla (RF-14.09). El costo se suma al tope mensual.
- **Guardado:** tabla `assistant_weekly_summaries` (empresa, semana, texto, `ai|template`, costo), única por empresa+semana
  → el job es idempotente (reintentos o dos servidores no lo mandan dos veces).
- **Salida (canales detrás de una interfaz, CA-14.09):** Discord → `Notifier::summary()` (canal `DISCORD_SUMMARY_CHANNEL_ID`
  de monitoreo, cola `discord`); mail → a la lista que configure el Admin (`assistant_summary_emails`; vacía = usuarios
  activos con rol Admin); web → una conversación "Resumen de la semana del …" para cada persona con `assistant.use`.
- **Cuándo:** `assistant:weekly-summary` cada hora; manda a cada empresa cuyo lunes 9:00 local ya pasó y que todavía no
  tiene el resumen de esa semana (mismo patrón que `tasks:notify-due`). Interruptor `assistant_weekly_summary_enabled`.
- **A pedido:** tool MCP de lectura `weekly_summary` (`projects.manage` + módulo reportes) con los mismos datos, para
  "resumime la semana" en el chat.
- **Configuración:** en Configuración → Asistente: prender/apagar el resumen y los mails destinatarios.
- [x] 1. `WeeklySummaryData` + tests (CA-14.06: horas == reporte web del mismo rango; empresa ajena; semana sin datos).
- [x] 2. Plantilla + `compose()` del motor + fallback + costo al tope (tests con motor fake).
- [x] 3. Tabla, acción `SendWeeklySummary`, canales (Discord fake, Mail::fake, web) + idempotencia + CA-14.09.
- [x] 4. Comando programado + settings + pestaña (front) + tool `weekly_summary`.
- [x] 5. Suites, eval real de una redacción, review, commit + push.

**Revisión Fase 3 (2026-11-17)** — review independiente corregido: reserva del registro antes de redactar (una carrera
no paga la IA dos veces) y reparto por canal con reintento horario solo de lo que falló; `try/catch` por empresa;
proyectos borrados fuera de vencidas y de ampliaciones pendientes (también arregla la web); control de la redacción de la
IA más fuerte (cada "nombre + horas" tal cual, mismos números y cantidad de veces, sin links ni menciones, máx. 1800
caracteres); copia web solo para `assistant.use` + `projects.manage` + módulo reportes, nunca clientes; Discord opt-in por
empresa (`assistant_summary_discord`: el canal de resumen de monitoreo es de todo el server); nombres y títulos aplanados
(sin saltos, markdown, links ni menciones). Redacción real con `claude -p`: OK, mismos números, USD 0,04.
Pendiente conocido: el resumen queda congelado el lunes; una carga manual retroactiva posterior cambia el reporte web de esa
semana (CA-14.06 vale al momento del envío).

### Fase 4 — Discord (RF-14.08)

Plan (2026-11-17, Juani: reusar el bot de monitoreo). Monitoreo ya tiene la app/bot (token, guild, colas `discord`) y
`users.discord_user_id` (lo carga un Admin, `SetDiscordUserId`); no tiene endpoint de interacciones.
- **Misma app:** se suman `DISCORD_APPLICATION_ID` y `DISCORD_PUBLIC_KEY`; el endpoint de interacciones de la app pasa a ser
  `POST /api/discord/interactions` (firma Ed25519 verificada en un middleware; sin firma válida, 401).
- **Vincular = la misma columna `discord_user_id`** (vía `SetDiscordUserId`, con actividad): en el asistente web "Conectar
  Discord" da un código de 10 min; `/newdesk vincular codigo:…` lo canjea. Si el Admin ya cargó el id, no hace falta.
- **`/newdesk pedir pedido:…`:** respuesta diferida efímera (3 s de Discord) → `AskAssistant` con canal discord (sigue la
  conversación de Discord de las últimas 2 h) → al terminar el turno, un listener edita el mensaje original con la respuesta,
  los botones Confirmar/Cancelar de cada propuesta y los reportes como adjuntos. El token de la interacción vive 15 min:
  se guarda cifrado en caché, nunca en la base.
- **Efímero, no hilo:** desvío consciente de la spec ("respuesta en hilo"): en un canal compartido mostraría clientes y
  horas a todos (CA-14.09). Solo lo ve quien pidió.
- **Botones:** solo los resuelve quien pidió (policy `resolve`, CA-14.08); el mensaje se actualiza con cómo terminó.
- **Sin vincular / sin permiso / asistente apagado / tope:** mensaje efímero claro, no se ejecuta nada (CA-14.08).
- `php artisan assistant:discord-commands` registra `/newdesk` en el guild con el token del bot.
- [x] 1. Config + middleware de firma + endpoint + PING.
- [x] 2. Vinculación (código web + `/newdesk vincular`).
- [x] 3. `/newdesk pedir` + relay de la respuesta (edición del original, botones, adjuntos) + botones.
- [x] 4. Registro del comando, botón "Conectar Discord" en la web, docs (`.env.example`, cómo configurar el portal).
- [x] 5. Suites, review, commit + push.

**Revisión Fase 4 (2026-11-17)** — sin bypass de firma ni suplantación por botones. Corregido: el token de la
interacción ya no puede llegar a un log (errores del cliente sin URL); el resumen de cada propuesta siempre entra (se
recorta solo el texto) y pasadas 5 se manda a la web; el token se guarda antes de encolar el turno; el relay va en la cola
`discord` de monitoreo y no bloquea el worker del asistente (reintenta solo 429/5xx); una cuenta de Discord = un usuario
en todas las empresas; el asistente solo confía en vínculos hechos por la persona (`discord_verified_at`, que
`SetDiscordUserId` borra si el id cambia: un admin de monitoreo no puede actuar como otro); rate limit por cuenta;
canje del código con lock.
- [x] Después: DMs / texto libre con un proceso gateway bajo Supervisord. → Fase 5.

## Fase 5 — Mensajes directos al bot (2026-11-19, "seguí con todo")

Objetivo: hablarle al asistente por DM, en texto libre, sin `/newdesk`. Discord solo entrega mensajes por el gateway
(WebSocket), así que hace falta un proceso que quede conectado. Sin intents privilegiados: el contenido de un DM al bot
llega igual (solo `DIRECT_MESSAGES`, `1 << 12`).

- `GatewaySession` (dominio, pura, testeable): el protocolo — HELLO → IDENTIFY o RESUME, heartbeat con jitter y
  detección de conexión zombi (sin ACK), op 1/7/9, READY (session_id + resume_gateway_url), códigos de cierre que no
  se reintentan (4004 token, 4010–4014). Nunca loguea payloads (llevan el token).
- `DiscordGatewayRunner` (infra, `ratchet/pawl` sobre ReactPHP, ya presente por Reverb): solo transporta frames y
  timers; reconecta con espera creciente (1 s → 60 s). Comando `assistant:discord-gateway`, programa de Supervisord
  solo si el bot y la app están configurados.
- Por cada `MESSAGE_CREATE` de un DM (sin `guild_id`, autor no bot) encola `AnswerDiscordDirectMessage` en la cola
  `discord`: el loop nunca hace HTTP ni toca la base.
- El job: mismas reglas que `/newdesk pedir` (vínculo verificado, `assistant.use`, tope, 20/h) + rate limit por cuenta;
  "escribiendo…" mientras tanto; continúa la conversación de Discord de las últimas 2 h; respuesta como mensaje
  nuevo en el DM (texto, botones, adjuntos) desde el mismo relay; los botones ya funcionan por el endpoint de
  interacciones.
- Tests: sesión del gateway (cada op y cierre), job (no vinculado, sin permiso, pedido, propuestas con botones,
  adjuntos), relay a DM, programa de Supervisor condicionado.

- [x] 1. `ratchet/pawl`, `GatewaySession` + tests.
- [x] 2. Runner + comando + Supervisor.
- [x] 3. Job del DM + relay a canal + cliente (`createMessage`, `typing`) + tests.
- [x] 4. Docs (RF-14), commit + push. Suite completa del backend: cortada por RAM, falta re-correrla.

**Revisión Fase 5** — sin bugs de protocolo ni fugas del token. Aplicado de las notas: con token o intents rechazados el
proceso espera 5 min antes de salir (Supervisor no reidentifica en loop); reconecta si HELLO no llega en 30 s; si el job
falla, la persona recibe "No pude procesar tu mensaje".

## Investigación (2026-11-16) — resumen

- **Por qué asistente propio y no solo claude.ai:** permisos del usuario logueado, confirmación de escrituras en nuestra UI, Discord, funciona sin exponer el MCP a internet ni dar cuenta de Claude a cada uno. claude.ai (conector org + tareas programadas en la nube) queda como complemento: requiere OAuth en el MCP (RF-12.08) y plan Team.
- **Decisión de Juani (2026-11-16): el motor es `claude -p`** (Claude Code headless), no la API de Anthropic. Ventajas: reusa el MCP tal cual (mismas tools y permisos), sin SDK nuevo en el backend.
- **Managed Agents** (Anthropic hostea loop + agenda): viable — MCP con token en vault (`static_bearer`), MCP pide aprobación por defecto (`always_ask`), deployments con cron. Descartado: el asistente necesita UI propia y Discord igual.
- **Skills/plugins para construirlo (instalados 2026-11-16):** `claude-api`, `xlsx`/`pdf`/`dataviz` (ya estaban); skill `mcp-builder` (oficial anthropics/skills, en `~/.agents/skills`: diseño de tools + evals); plugin oficial `mcp-server-dev` (deploy remoto, auth, patrones de tools). `laravel-best-practices` NO existe en el marketplace oficial (dato erróneo del relevamiento). Discord: API oficial (Interactions), sin dependencias de terceros.
- **Plugin oficial `discord` (no instalado):** puente Discord ↔ una sesión de Claude Code con MCP. Sirve para prototipar en un día (bot → Claude Code con el MCP de newDesk), pero todos actúan con el token del dueño de la sesión, una sola conversación compartida y depende de una PC con Claude Code abierto: no reemplaza RF-14.08.
- **Plugin oficial `mcp-tunnels` (no instalado):** túnel de Anthropic para exponer un MCP privado a claude.ai sin abrirlo a internet; útil si se hace RF-14.11.
- **Riesgos:** datos sensibles (clientes, horas por persona) fuera del sistema → solo canal/mails configurados por Admin; costo → tope mensual; prompt injection desde datos (títulos/comentarios) → las escrituras siempre pasan por confirmación humana.
