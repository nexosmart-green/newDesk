# RF-04 Tareas

| ID | Requerimiento | Prio |
|---|---|---|
| RF-04.01 | Cualquier miembro del proyecto (incluido Colaborador, P-04 ✅) crea tareas y las asigna a miembros. Campos: título, descripción (markdown + imágenes pegadas), asignados (1..n miembros), fecha inicio, vencimiento, prioridad, estimación en horas (opcional). Código autogenerado `<prefijo>-<n>` correlativo por proyecto. | M |
| RF-04.02 | **"Crear e iniciar"**: botón que crea la tarea y arranca el cronómetro para quien la crea (que queda como asignado). Si ya tenía un timer corriendo, pide el comentario de cierre del anterior (RF-05.16). | M |
| RF-04.03 | Estados configurables por la empresa (no por proyecto), con orden y color. Base: Pendiente · En curso · Bloqueado · En revisión · Completada. Cada estado tiene un "tipo" (abierta / bloqueada / cerrada) que usan las reglas. | M |
| RF-04.04 | Vista **Lista** (como hoy, sin columnas de menciones/comentarios sin leer separadas: un solo indicador) y vista **Tablero** (kanban drag & drop) sobre los mismos datos. | M |
| RF-04.05 | Filtros: estado (default: ocultar cerradas), asignado, vencidas, búsqueda por código o título. Persisten por usuario. | M |
| RF-04.06 | Comentarios por tarea con @menciones (notifican). | M |
| RF-04.07 | Vencidas resaltadas; vista "Mis tareas" cross-proyecto (lo que el colaborador abre a la mañana). | M |
| RF-04.08 | Subtareas / checklist dentro de la tarea. | S |
| RF-04.09 | Adjuntos en la tarea (imágenes, PDFs). Reemplaza la tab Archivos. | S |
| RF-04.10 | Exportar lista a CSV/Excel. | C |
| RF-04.11 | Hitos (milestones). Hoy existe el filtro; ⚠ G-01 si se usa. | W |
| RF-04.12 | **Mover una tarea a otro proyecto** (pedido 2026-11-06: "si alguien se equivoca y carga las horas las tiene que tener"). La tarea toma un código nuevo del proyecto destino; el código viejo sigue resolviendo a la tarea. Sus **horas, comentarios y asignados (los que sean miembros del destino) viajan con ella**: las horas dejan de consumir la bolsa de origen y pasan a consumir la de destino. Queda en Actividad de ambos proyectos. P-27 ✅ | M |

## Criterios de aceptación

- **CA-04.01** Códigos correlativos sin huecos ni duplicados aunque dos personas creen tareas en el mismo segundo (secuencia por proyecto con lock, no `max()+1`).
- **CA-04.02** Dado que el usuario ya tiene un timer corriendo en otra tarea, cuando hace "Crear e iniciar", entonces se le pide el comentario del timer anterior; con el comentario, el anterior se detiene y guarda y arranca el nuevo (ver RF-05.02).
- **CA-04.02b** Dado un proyecto sin horas disponibles, cuando se hace "Crear e iniciar", entonces la tarea se crea pero el timer no arranca y se muestra el motivo.
- **CA-04.07b** (pedido 2026-11-10) Desde "Mis tareas", "Nueva tarea" pide el proyecto (los activos que la persona ve; si hay uno solo, viene elegido), ofrece sus miembros como asignados con la persona marcada si es miembro, y permite **Crear** o **▶ Crear e iniciar** con las reglas de CA-04.02/02b. La tarea creada aparece en la lista con su ▶.
- **CA-04.03** Pasar una tarea a un estado de tipo "cerrada" detiene cualquier timer corriendo sobre ella.
- **CA-04.12a** Dada `bhn-7` con 3 h registradas en BH News, cuando se mueve a Otro Cliente, entonces pasa a `otr-N` (siguiente correlativo del destino), BH News consume 3 h menos, Otro Cliente 3 h más, y `bhn-7` sigue abriendo la tarea.
- **CA-04.12b** Si el destino es una bolsa sin horas suficientes para las horas que trae la tarea, el movimiento se rechaza entero con el motivo (como R-4); no se mueve a medias.
- **CA-04.12c** No se puede mover una tarea con un cronómetro corriendo, ni a un proyecto Finalizado/Cancelado.
- **CA-04.12d** Una tarea sin horas la mueve cualquier miembro de ambos proyectos. **Con horas**: la puede mover quien registró esas horas (todas las de la tarea son suyas) o quien tenga `hours.allocate`; si tiene horas de otras personas, solo `hours.allocate`. (P-27 ✅ PO 2026-11-06)
