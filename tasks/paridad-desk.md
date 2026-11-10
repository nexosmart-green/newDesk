# Paridad con desk — puntos 1 a 7 (2026-11-10)

Origen: relevamiento en vivo de desk.nexosmart.es (cuenta Colaborador). Lo que la gente usa hoy en desk y newDesk no tenía.
Rama `dev_green`. Backend y frontend trabajan en paralelo sobre este contrato; si algo cambia, se actualiza acá.

## Alcance

1. **Horas registradas por tarea** — en lista, tablero y detalle; tab "Horas" con los registros de la tarea.
2. **Todas las tareas** — lista transversal a los proyectos visibles para quien mira, con filtros.
3. **Panel de inicio** — contadores (pendientes / vencidas / vencen hoy, proyectos activos), horas de la semana por día, próximas a vencer, bolsas por agotarse (si puede verlas).
4. **Historial de horas** — mis registros por rango libre, paginado, con total y export CSV; quien tiene `time.manage` puede elegir persona.
5. **Categoría y etiquetas de tarea** — catálogos por empresa; una categoría y N etiquetas por tarea; filtros.
6. **Descripción con formato** — markdown (GFM) con imágenes pegadas/arrastradas; render seguro (sin HTML crudo).
7. **Detalle de tarea** — "Creada por", "Creada el" con hora, historial de cambios de la tarea.

Fuera de esta tanda: subtareas (RF-04.08) y adjuntos (RF-04.09) — van después.

## Contrato de API (envelope `{data, message, errors}`; listas paginadas Laravel `{data, links, meta}`)

### Task (TaskResource) — campos nuevos
- `logged_seconds: int` — suma de las entradas de la tarea (las corriendo cuentan hasta ahora). Sin N+1 en listas.
- `category: {id, name} | null`
- `labels: [{id, name, color}]` (color hex `#rrggbb`)
- Solo en detalle (`tasks.show`): `created_by: {id, full_name} | null`, `created_at: ISO8601`.
- `description` sigue siendo string; ahora es **markdown**.

### 1. Horas de una tarea — `module:time`
`GET /api/v1/tasks/{task}/time-entries?page=` → paginado 50, más nuevo primero.
Ítem: `{id, user: {id, full_name, avatar}, started_at, ended_at, seconds, is_running, comment, comment_pending, source}`.
`meta.total_seconds` = total de la tarea. Autorización: poder ver la tarea (404 si no). Nunca tarifas.

### 2. Todas las tareas — `module:projects`
`GET /api/v1/tasks` con los mismos filtros que `/me/tasks` (`status_ids[]`, `include_closed`, `assignee_id`, `overdue`, `search`, `project_id`, `per_page`) + `category_id`, `label_id`. Solo proyectos visibles (misma regla que hoy: ve todo quien `seesAllProjects`, el resto sus proyectos).
`/me/tasks` y `/projects/{project}/tasks` aceptan también `category_id` y `label_id`.

### 3. Panel — sin módulo (cada bloque se omite si el rol no tiene el módulo)
`GET /api/v1/me/dashboard` →
```json
{
  "tasks": {"open": 54, "overdue": 38, "due_today": 2, "due_soon": [Task, ...máx 5, abiertas, asignadas a mí, por due_on asc]},
  "projects": {"active": 19},
  "week": {"from": "2026-11-09", "to": "2026-11-15", "total_seconds": 0, "days": [{"date": "2026-11-09", "seconds": 0}]},
  "bags_running_out": [{"id": 1, "name": "", "code_prefix": "", "allocated_seconds": 0, "consumed_seconds": 0}]
}
```
`tasks` (módulo projects) = asignadas a mí, abiertas. `projects.active` = proyectos activos visibles (miembro o ve todos). `week` (módulo time) = lunes a domingo local del usuario, mis horas. `bags_running_out` solo con `hours.allocate` o `projects.manage`: activos con consumo ≥ 80 % (máx 5, más consumidos primero). Bloque sin permiso → `null`.

### 4. Historial de horas — `module:time`
`GET /api/v1/me/time-entries?from=Y-m-d&to=Y-m-d&project_id=&search=&user_id=&page=` → paginado 50, más nuevo primero.
Ítem: `{id, user: {id, full_name}, task: {code, title}, project: {id, name}, started_at, ended_at, seconds, is_running, comment, comment_pending, source}`. `meta.total_seconds` = total del filtro completo (no de la página).
`user_id` ajeno solo con `time.manage` (si no, 403 con mensaje claro). Rango máx 366 días (422 claro). `search` busca en comentario, código y título de tarea.
`GET /api/v1/me/time-entries/export?...mismos filtros` → `text/csv` (UTF-8 con BOM, separador `;` para Excel AR), columnas: Fecha, Inicio, Fin, Duración (h:mm), Horas (decimal con coma), Persona, Proyecto, Tarea, Título, Comentario, Origen.

### 5. Categorías y etiquetas — `module:projects`
- `GET /api/v1/task-categories` → `[{id, name, tasks_count}]` ordenadas por nombre.
- `POST /api/v1/task-categories {name}` — cualquiera que pueda crear tareas (alta inline desde el form). Nombre único por empresa (422: "Ya existe la categoría X.").
- `PUT /api/v1/task-categories/{id} {name}`, `DELETE /api/v1/task-categories/{id}` — requieren `projects.manage`. Borrar deja las tareas sin categoría.
- Etiquetas igual: `/api/v1/task-labels` con `{name, color}`; borrar las desasigna.
- `POST/PUT /tasks` y `/projects/{project}/tasks` aceptan `category_id: int|null` y `label_ids: int[]` (de la misma empresa; si no, 422). Cambios quedan en `activity_log`.
- Seed dev: categorías de desk (General, Front-Diseño, Backend-API, Tarea QA, Diseño, Ticket) y un par de etiquetas.

### 6. Imágenes en la descripción — `module:projects`
- `POST /api/v1/projects/{project}/task-images` multipart `image` (jpeg/png/webp/gif, ≤ 5 MB; HEIC → 422 "Convertí la imagen a JPG o PNG.") → `201 {data: {url}}`. Autoriza crear tareas en el proyecto.
- `GET /api/v1/task-images/{uuid}` → la imagen (disco privado), autoriza ver el proyecto; 404 si no. `Cache-Control: private, max-age=86400`.
- El markdown referencia la `url` devuelta (ruta relativa `/api/v1/task-images/{uuid}`).

### 7. Historial de la tarea — `module:projects`
`GET /api/v1/tasks/{task}/activity?page=` → mismo `ActivityResource` que el de proyecto, filtrado a la tarea (subject = task). Verificar que crear, editar, cambiar estado, asignar, mover y categoría/etiquetas registran actividad con subject = la tarea.

### MCP
`McpPresenter` de tareas incluye `logged_seconds`, `category`, `labels`. Sin herramientas nuevas.

## Frontend (skills `frontend-design` + `ui-ux-pro-max`, paleta desk)
- Ruta `/` = **Panel**; "Mis tareas" pasa a `/mis-tareas`; nueva `/tareas` = Todas las tareas. Sidebar: Inicio, Mis tareas, Tareas, …
- Lista/tablero: columna/badge de horas registradas, categoría y etiquetas (chips con color), filtros categoría/etiqueta.
- Detalle: tabs Detalle · Comentarios · Horas · Historial; "Creada por X el dd/mm/aaaa hh:mm"; total de horas; selector de categoría (con "Crear …") y etiquetas.
- Descripción: editor markdown con barra (negrita, cursiva, título, lista, checklist, link, código, imagen), Escribir/Vista previa, pegar/arrastrar imagen → sube con progreso → inserta. Render con `react-markdown` + `remark-gfm`, sin `rehype-raw`.
- Mi semana: tab "Historial" con rango, proyecto, búsqueda, persona (si `time.manage`), total, paginado, "Exportar CSV".
- Configuración → Catálogos: ABM de categorías y etiquetas (con color).
- Siempre: skeletons al cargar, botones con `loading`, toast de éxito/error con mensaje del backend, estados vacíos con acción, error con "Reintentar", errores de campo bajo el campo.

## Checklist
- [x] Backend 1–7 + tests Pest
- [x] Frontend 1–7 + tests Vitest
- [x] Integración: suites completas, typecheck, lint, build, E2E
- [x] Screenshots revisadas (desktop + mobile)
- [x] Review independiente
- [x] Commit/push `dev_green`, CI verde, merge a `main`

## Review (2026-11-10)
- Backend 196 tests, frontend 190 tests, typecheck/lint/build/pint limpios, E2E 4/4 (nuevo `e2e/parity.spec.ts`).
- Review independiente: sin hallazgos graves. Corregidos 7 medios/bajos con test: "vencida" por día local del usuario (no UTC), notificación de vencimientos → `/mis-tareas`, pegar texto de Excel/Word no sube la imagen, nombre de archivo con `$&`, imágenes viajan al mover la tarea, conteos de catálogos se refrescan, imágenes remotas se muestran como link (sin pixel de rastreo).
- Pendiente siguiente tanda: subtareas (RF-04.08) y adjuntos (RF-04.09); comentarios en markdown.
