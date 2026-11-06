# RF-03 Proyectos

| ID | Requerimiento | Prio |
|---|---|---|
| RF-03.01 | ABM proyecto: nombre, cliente, **prefijo de código** (único, ej. `bhn`), descripción, fecha inicio, fecha fin estimada, estado (Activo / En pausa / Finalizado / Cancelado), responsable. | M |
| RF-03.02 | Tipo de proyecto (P-05 ✅): **Bolsa de horas** (tope duro, horas cargadas a mano) o **Interno** (sin tope, ej. NexoSmart propio). Sin abono mensual ni facturación. | M |
| RF-03.03 | Miembros: agregar/quitar usuarios. Solo los miembros ven el proyecto y pueden recibir tareas. | M |
| RF-03.04 | Tabs: **Resumen · Tareas · Horas · Miembros · Actividad**. | M |
| RF-03.05 | Resumen: barra de horas (vendidas / consumidas / restantes, verde < 80% < amarillo < 100% ≤ rojo), tareas abiertas/vencidas, últimos movimientos. | M |
| RF-03.06 | Actividad: log inmutable de lo que pasó (tarea creada, estado cambiado, horas ampliadas, miembro agregado) con quién y cuándo. | S |
| RF-03.07 | Listado de proyectos con filtros (cliente, estado, "con horas por agotarse") y columna de % consumido. | M |
| RF-03.08 | Proyecto Finalizado/Cancelado: no admite nuevas tareas ni cronometrar. Reabrir lo hace Admin. | M |

La bolsa de horas en sí está en **RF-05**.

## Criterios de aceptación

- **CA-03.01** Dos proyectos no pueden tener el mismo prefijo. El prefijo no se puede cambiar una vez que hay tareas (los códigos ya circulan).
- **CA-03.03** Un colaborador que no es miembro y abre la URL del proyecto recibe 404 (no 403: no confirmamos que existe).
- **CA-03.08** Dado un proyecto Finalizado con un timer corriendo, cuando se finaliza, entonces el timer se detiene y se guarda el tramo.
