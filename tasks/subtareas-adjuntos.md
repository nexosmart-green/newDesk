# Subtareas, adjuntos y comentarios con formato (2026-11-12)

Tanda siguiente a `tasks/paridad-desk.md`. RF-04.08 (checklist), RF-04.09 (adjuntos) y comentarios en markdown.
Rama `dev_green`. Backend y frontend en paralelo sobre este contrato.

**Convivencia con `feat/monitoreo`** (módulo del otro dev, sin mergear): esa rama toca router, navigation, MyTasksPage,
timer, `User.php`, `McpPresenter`, `routes/api/time.php`, `index.css`, `lib/api.ts`. Esta tanda **no toca esos archivos**
salvo que sea imprescindible (y entonces con cambios mínimos). Migraciones con prefijo `2026_11_12_`.

## 1. Subtareas / checklist (RF-04.08)
Modelo: `task_checklist_items (id, company_id, task_id, text varchar(500), position, done_at null, done_by null, created_by, timestamps)`.

- `TaskResource` en **listas y detalle**: `checklist_progress: {done: int, total: int}` (sin N+1: `withCount`). En desk se ve como "5/5" en la fila.
- **Detalle** (`tasks.show`): `checklist: [{id, text, position, done: bool, done_at, done_by: {id, full_name}|null}]` ordenado por `position`.
- `POST /api/v1/tasks/{task}/checklist {text}` → 201 item (al final). Máx 100 ítems por tarea (422 claro).
- `PUT /api/v1/checklist-items/{item} {text?, done?}` → item. `done: true` guarda quién y cuándo.
- `DELETE /api/v1/checklist-items/{item}` → 200.
- `PUT /api/v1/tasks/{task}/checklist/order {ids: int[]}` → lista ordenada (ids deben ser exactamente los de la tarea; si no, 422).
- Autorización: igual que editar la tarea (`TaskPolicy::update`); otra empresa / sin visibilidad → 404. Módulo `projects`.
- `activity_log` con subject = la tarea: `checklist.added`, `checklist.checked`, `checklist.unchecked`, `checklist.renamed`, `checklist.removed` (con el texto).
- MCP: el detalle de tarea incluye `checklist` (texto + hecho) y una tool nueva `update_checklist` (acciones: add, check, uncheck por texto o número de ítem). Registrar la tool en `NewDeskServer` con `module()` = projects.

## 2. Adjuntos (RF-04.09)
Modelo: `task_attachments (id, uuid unique, company_id, task_id, uploaded_by, original_name, path, mime, size, created_at)` en disco privado.

- `GET /api/v1/tasks/{task}/attachments` → `[{id, uuid, name, mime, size, url, is_image, uploaded_by: {id, full_name}, created_at, can_delete}]` más nuevo primero.
- `POST /api/v1/tasks/{task}/attachments` multipart `file` → 201 ítem. Tipos (por contenido, `finfo`): imágenes jpeg/png/webp/gif, pdf, docx, xlsx, pptx, odt, ods, txt, csv, zip. Máx **20 MB** (422 "El archivo puede pesar hasta 20 MB."). HEIC → 422 "Convertí la imagen a JPG o PNG." Otros → 422 "Ese tipo de archivo no se puede adjuntar." Máx 50 adjuntos por tarea.
- `GET /api/v1/task-attachments/{uuid}` → el archivo. Imágenes y PDF `inline`; el resto `attachment`. Siempre `Content-Disposition` con el nombre original (RFC 5987), `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox; default-src 'none'`, `Cache-Control: private`. Autoriza ver la tarea (404 si no).
- `DELETE /api/v1/task-attachments/{uuid}` → quien lo subió o `projects.manage`; borra el archivo del disco.
- `TaskResource` detalle: `attachments_count`. Listas: `attachments_count` también (withCount; desk muestra el clip).
- Subir/borrar registran actividad (`attachment.added` / `attachment.removed`, con el nombre).
- Mover tarea: los adjuntos cuelgan de la tarea, no hay que mover nada; test que lo confirme.
- Rate limit de subida razonable (p.ej. 30/min por usuario).

## 3. Comentarios con formato
- Backend: sin cambios de modelo (el cuerpo ya es texto). Verificar que las menciones siguen funcionando con markdown y que las imágenes `/api/v1/task-images/{uuid}` pegadas en comentarios se suben con el endpoint de imágenes del proyecto existente y que `MoveTask` también mueve las imágenes referenciadas en **comentarios** (no solo descripción).
- Frontend: comentarios renderizados con `MarkdownView` + `remark-breaks` (salto simple = salto de línea, como un chat). Menciones `@Nombre` destacadas. El campo de comentario permite pegar/arrastrar imágenes (reusar la lógica de subida del editor de descripción) y tiene una barra mínima (negrita, cursiva, lista, código, link) o al menos atajos Ctrl+B/I/K, sin romper el autocompletado de menciones.

## Frontend
Skills obligatorios: `frontend-design:frontend-design` y `ui-ux-pro-max`. Paleta y componentes existentes.
- Detalle de tarea: bloque **Subtareas** en la tab Detalle (debajo de la descripción): barra de progreso "3 de 5", tildar con optimismo (rollback + toast si falla), agregar con Enter, editar inline, borrar con confirmación liviana (undo toast o confirm), reordenar (drag con handle + botones subir/bajar accesibles por teclado).
- Tab nueva **Archivos** (con contador): zona de arrastrar/soltar + botón, varias subidas en paralelo con barra de progreso por archivo, validación previa de tipo/tamaño con el mensaje, grilla con miniaturas para imágenes (abrir en visor/lightbox) e ícono por tipo para el resto, nombre, tamaño legible, quién y cuándo, descargar, borrar (si `can_delete`) con confirmación.
- Listas/tablero: badge de checklist "3/5" (verde cuando completo) y clip con cantidad de adjuntos cuando > 0.
- Comentarios: render markdown + editor con imágenes (ver §3).
- Siempre: skeletons, botones con loading, toasts con el mensaje del backend, errores bajo el campo, vacíos con acción, "Reintentar". 375px.
- Correr tests también con `TZ=UTC` (lección 2026-11-10).

## Checklist
- [x] Backend + tests Pest
- [x] Frontend + tests Vitest (local y TZ=UTC)
- [x] Integración, E2E, capturas, review independiente
- [x] Commit/push `dev_green`, CI verde, merge a `main`

## Review (2026-11-12)
- Backend 218 tests, frontend 222 (local y TZ=UTC), E2E 5/5 (nuevo `e2e/subtasks-attachments.spec.ts`).
- PDFs sin CSP `sandbox` (Chrome no abre su visor con esa cabecera); el contenido se valida al subir y va con nosniff.
- Review independiente: sin graves. Corregido: .txt/.csv con texto que libmagic nombra distinto (JSON, logs, vacío) se aceptan, markup sigue rechazado; nombres sin caracteres bidi; tildar con lock de fila (sin doble log); rollback por ítem en el checklist.
- Fechas unificadas en `dd/mm/aaaa hh:mm` (comentarios incluidos).
- Producción: límites de subida en php.ini/Nginx y backup de `storage/app/private` (puesta-en-marcha §1).
