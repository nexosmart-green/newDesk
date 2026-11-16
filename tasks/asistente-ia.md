# Plan — Asistente de IA (RF-13)

Spec: `docs/02-requerimientos/RF-13-asistente-ia.md`. Rama `dev_green`. Investigación del 2026-11-16 (abajo).

## Fases

### Fase 1 — Herramientas de gestión (RF-13.07) — sin IA todavía
- [ ] Tools nuevas sobre acciones existentes (mismo patrón `NewDeskTool`, policies, módulo, `#[IsReadOnly]` en lecturas):
  `hours_overview` (todos los proyectos), `bags_running_out`, `hours_by_client`, `hours_by_person`, `overdue_tasks_team`,
  `list_clients` / `create_client` / `update_client` / `change_client_status`, `create_project` / `update_project` /
  `change_project_status` / `manage_project_members`, `list_extension_requests` / `approve_extension` / `reject_extension`,
  `list_people`, `team_shifts`.
- [ ] Marcar las de escritura como tales (metadato propio `writes = true`) para que el asistente pida confirmación.
- [ ] Tests de paridad web/MCP por cada tool.

### Fase 2 — Núcleo del asistente + chat web (RF-13.01–05, 09, 10)
- [ ] Permiso `assistant.use` (solo Admin por defecto; migración que lo otorga al rol Admin existente).
- [ ] Dominio `Assistant`: conversaciones y mensajes persistidos; adaptador que expone las tools MCP como tools de Claude
      (una sola definición); loop con el SDK oficial de PHP (tool runner); confirmación de escrituras (propuesta pendiente →
      confirmar/cancelar); `source = assistant` en Actividad; tope mensual de gasto + rate limit; prompt caching del system prompt + tools.
- [ ] Reportes en archivo (Excel/PDF) generados por el backend desde las mismas queries.
- [ ] Pantalla "Asistente" (skills de diseño), con tarjetas de confirmación, tablas y descargas.
- [ ] Eval con pedidos reales del dueño (`/claude-api build-eval`) antes de darlo por bueno.

### Fase 3 — Resumen semanal en texto (RF-13.06)
- [ ] Job lunes 9:00 (hora de la empresa): arma los números con queries existentes → Claude redacta el resumen → Discord + mail a Admins + chat web.
- [ ] Sin IA disponible (tope alcanzado / caída): sale el resumen con números en plantilla fija.

### Fase 4 — Discord (RF-13.08)
- [ ] App de Discord (coordinar con `feat/monitoreo`, que ya manda avisos a Discord: misma app/configuración).
- [ ] Endpoint de Interactions en Laravel (verificación de firma Ed25519), respuesta diferida + cola (límite de 3 s de Discord).
- [ ] `/newdesk vincular`, `/newdesk <pedido>`, botones Confirmar/Cancelar (solo quien pidió), adjuntos.
- [ ] Fase 2 de Discord (después): DMs / texto libre con un proceso gateway bajo Supervisord.

## Investigación (2026-11-16) — resumen

- **Por qué asistente propio y no solo claude.ai:** permisos del usuario logueado, confirmación de escrituras en nuestra UI, Discord, funciona sin exponer el MCP a internet ni dar cuenta de Claude a cada uno. claude.ai (conector org + tareas programadas en la nube) queda como complemento: requiere OAuth en el MCP (RF-12.08) y plan Team.
- **Managed Agents** (Anthropic hostea loop + agenda): viable — MCP con token en vault (`static_bearer`), MCP pide aprobación por defecto (`always_ask`), deployments con cron. Descartado por ahora: el asistente necesita UI propia y Discord igual, y el loop en PHP es más simple de mantener acá.
- **Skills/plugins para construirlo (instalados 2026-11-16):** `claude-api`, `xlsx`/`pdf`/`dataviz` (ya estaban); skill `mcp-builder` (oficial anthropics/skills, en `~/.agents/skills`: diseño de tools + evals); plugin oficial `mcp-server-dev` (deploy remoto, auth, patrones de tools). `laravel-best-practices` NO existe en el marketplace oficial (dato erróneo del relevamiento). Discord: API oficial (Interactions), sin dependencias de terceros.
- **Plugin oficial `discord` (no instalado):** puente Discord ↔ una sesión de Claude Code con MCP. Sirve para prototipar en un día (bot → Claude Code con el MCP de newDesk), pero todos actúan con el token del dueño de la sesión, una sola conversación compartida y depende de una PC con Claude Code abierto: no reemplaza RF-13.08.
- **Plugin oficial `mcp-tunnels` (no instalado):** túnel de Anthropic para exponer un MCP privado a claude.ai sin abrirlo a internet; útil si se hace RF-13.11.
- **Riesgos:** datos sensibles (clientes, horas por persona) fuera del sistema → solo canal/mails configurados por Admin; costo → tope mensual; prompt injection desde datos (títulos/comentarios) → las escrituras siempre pasan por confirmación humana.
