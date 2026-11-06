# RF-12 Servidor MCP (Claude Code y otros clientes)

Origen: pedido del PO 2026-11-06 — "que tenga MCP, que cada rol lo pueda sacar con el token y configurar con conexión a Claude Code, y pueda realizar comentarios al finalizar cronómetro (obligatorio), crear tareas y demás".

## Objetivo

Que cada persona trabaje con newDesk **desde Claude Code** (u otro cliente MCP) sin abrir la web: ver sus tareas, crear y asignar tareas, iniciar/detener el cronómetro **con comentario obligatorio**, comentar, consultar horas disponibles. Lo que puede hacer por MCP es **exactamente** lo que su rol puede hacer en la web: mismas policies, ningún atajo.

## Requerimientos

| ID | Requerimiento | Prio |
|---|---|---|
| RF-12.01 | Endpoint MCP HTTP (Streamable HTTP) en `https://<dominio>/mcp`, implementado con el paquete oficial `laravel/mcp`. | M |
| RF-12.02 | **Token personal**: desde "Mi perfil → Conexiones de IA" cada usuario genera un token (nombre, vencimiento opcional 30/90/365 días / sin vencimiento). Se muestra **una sola vez**; se guarda hasheado. Lista de tokens con último uso; revocar en un clic. | M |
| RF-12.03 | Los permisos del token = permisos del **rol actual** del usuario, evaluados en **cada request** (si le cambian el rol o lo dan de baja, el token pierde/gana permisos al instante; usuario inactivo → 401). | M |
| RF-12.04 | La pantalla muestra el comando listo para copiar: `claude mcp add --transport http newdesk https://<dominio>/mcp --header "Authorization: Bearer <token>"` + instrucciones para `.mcp.json` de proyecto (con variable de entorno, nunca el token commiteado). | M |
| RF-12.05 | El dueño puede habilitar/deshabilitar el MCP por rol (permiso `mcp.use`) y ver/revocar los tokens de cualquiera. | M |
| RF-12.06 | Toda acción hecha por MCP queda en Actividad con `source = mcp` y el nombre del token. | M |
| RF-12.07 | Rate limit por token (default 60 req/min) y respuestas de error claras para el modelo (ej. "Sin horas disponibles en bhnews: pedí una ampliación"). | M |
| RF-12.08 | OAuth 2.1 (conector desde claude.ai / Claude Desktop sin pegar token), como se hizo en cuonti. | C (R2) |

## Tools (R1)

Todas validan con las mismas policies que la API web. Las de escritura usan los mismos servicios de dominio (no lógica duplicada en el MCP).

| Tool | Qué hace | Permiso |
|---|---|---|
| `whoami` | Usuario, rol, permisos, timer activo, comentarios pendientes | — |
| `list_my_tasks` | Mis tareas abiertas cross-proyecto (filtros: proyecto, vencidas, estado) | — |
| `list_projects` | Proyectos donde soy miembro, con horas cargadas/consumidas/disponibles | — |
| `get_project` | Detalle + disponibles + miembros | miembro |
| `get_task` | Detalle de tarea por código (`bhn-60`) con comentarios | miembro |
| `create_task` | Crear tarea (título, descripción, asignados, vencimiento, prioridad); opción `start_timer` | miembro |
| `assign_task` | Asignar/desasignar miembros | `tasks.assign` |
| `update_task_status` | Cambiar estado | miembro |
| `comment_task` | Comentar (con @menciones) | miembro |
| `start_timer` | Iniciar en una tarea. Si hay otro corriendo, **exige `comment` para cerrar el anterior** | asignado |
| `stop_timer` | Detener el activo. **`comment` obligatorio** (ver RF-05.16) | — |
| `get_active_timer` | Timer activo y tiempo corrido | — |
| `complete_pending_comment` | Completar el comentario de un registro cortado automáticamente | dueño del registro |
| `log_time` | Carga manual (fecha, desde-hasta, tarea, comentario) dentro de la ventana permitida | asignado |
| `my_timesheet` | Mis horas por día en un rango | — |
| `my_shifts` | Mis turnos en un rango | — |
| `allocate_hours` | Cargar/ampliar horas de un proyecto (motivo obligatorio) | `hours.allocate` |
| `project_hours_report` | Horas por persona/tarea de un proyecto | `projects.manage` |

## Criterios de aceptación

- **CA-12.03** Un Colaborador con token llama `allocate_hours` → error de permiso; el mismo usuario promovido a Gestor, **sin regenerar el token**, puede.
- **CA-12.02** El token no se puede volver a ver; en la DB solo hay hash. Token revocado → 401 en el siguiente request.
- **CA-12.x** `stop_timer` sin `comment` (o vacío / < 10 caracteres) → error de validación y el timer **sigue corriendo**.
- **CA-12.y** `start_timer` en proyecto sin horas devuelve el mismo motivo que la web (CA-05.05).
- **CA-12.z** Todas las reglas de RF-05 (un timer por usuario, cortes, concurrencia) valen igual por MCP: se prueban los casos QA-01..03 también vía MCP.

## Seguridad

- Token en header `Authorization: Bearer`; nunca en query string.
- Token = Sanctum personal access token con prefijo identificable (`nd_…`) para que los escáneres de secretos lo detecten si se commitea.
- El MCP **no** expone tarifas, datos personales ajenos ni tools de administración de usuarios en R1.
