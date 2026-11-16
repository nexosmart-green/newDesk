# Plan — Asistente de IA (RF-13)

Spec: `docs/02-requerimientos/RF-13-asistente-ia.md`. Rama `dev_green`. Investigación del 2026-11-16 (abajo).

## Fases

### Fase 1 — Herramientas de gestión (RF-13.07) — sin IA todavía
- [ ] Tools nuevas sobre acciones existentes (mismo patrón `NewDeskTool`, policies, módulo, `#[IsReadOnly]` en lecturas):
  `hours_overview` (todos los proyectos), `bags_running_out`, `hours_by_client`, `hours_by_person`, `overdue_tasks_team`,
  `list_clients` / `create_client` / `update_client` / `change_client_status`, `create_project` / `update_project` /
  `change_project_status` / `manage_project_members`, `list_extension_requests` / `approve_extension` / `reject_extension`,
  `list_people`, `team_shifts`.
- [ ] Marcar las de escritura como tales (metadato propio `writes = true`): con un token de asistente **no ejecutan**, crean una propuesta (Fase 2).
- [ ] Tests de paridad web/MCP por cada tool.

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
