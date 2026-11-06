# Sistema actual (as-is): desk.nexosmart.es

## Qué es

**Froiden Worksuite** (Laravel + Blade + jQuery/Bootstrap). Evidencia: `/vendor/froiden-helper/helper.css` en el HTML público. El `<title>` dice **"Default Company"** → la instancia nunca se terminó de configurar (y expone el producto).

Implicancias:
- Los datos viven en una **MySQL con esquema Worksuite conocido** (`users`, `employee_details`, `projects`, `project_members`, `tasks`, `task_users`, `project_time_logs`, `attendances`, `leaves`, `clients`/`client_details`, `designations`, `teams`, `taskboard_columns`, etc.) → la migración es mapeable (ver `docs/06-migracion`).
- Se puede **medir el uso real** de cada tab contando filas por tabla (G-01).

## Proyecto → tabs actuales

| Tab | ¿Se usa? (según el pedido) | Decisión propuesta |
|---|---|---|
| Descripción General | Sí | **Resumen** con horas vendidas/consumidas/restantes |
| Miembros | Sí | **Miembros** |
| Archivos | No | Fuera (validar G-01) |
| Eventos | No | Fuera (validar G-01) |
| Tareas | Sí | **Tareas** con vista lista ⇄ tablero |
| Tablero de Tareas | Probable | Fusionado en Tareas (toggle) |
| Gráfico de Gantt | No | Fuera |
| Comentarios | No (a nivel proyecto) | Fuera; comentarios **por tarea** sí |
| Nota | No | Fuera (o campo "notas" en Resumen) |
| Gráfico BurnDown | No | Reemplazado por barra de consumo de horas en Resumen |
| Actividad | Dudoso | **Actividad** (log de auditoría, barato de tener) |
| Tickets | No | Fuera |

**Propuesta: 12 tabs → 5:** Resumen · Tareas · Horas · Miembros · Actividad.

## Tabla de tareas actual (captura `bhnews`)

Columnas: Código (`bhn-60`), Temporizador (▶), Tarea + proyecto, Completado en, Menciones sin leer, Comentarios sin leer, Fecha inicio, Fecha vencimiento (roja si vencida), Horas registradas, Asignado a (avatar), Estado (`Roadblocks - detenido`), Acción (⋮). Filtros: Estado (default "Ocultar completadas"), Asignado a, Hitos, búsqueda. Exportar.

Observaciones:
- Las 3 tareas visibles están **vencidas y "detenidas"** — el sistema no alerta ni obliga a re-planificar.
- Solo 1 de 3 tareas muestra ▶: el botón aparece solo para el asignado → bien, se mantiene.
- Código con prefijo por proyecto (`bhn-`) → se mantiene (útil en commits y conversaciones).
- Cronómetro global en el header (`00:11:09` + ■ stop) → se mantiene, es la mejor idea de la UI actual.

## Perfil de empleado actual

| Campo Worksuite | Decisión | Motivo |
|---|---|---|
| ID de empleado | Mantener | |
| Nombre completo | Mantener (nombre + apellido separados) | Hoy está "NexoSmart Green Juan": el campo se usa mal |
| Designación | **Puesto** | Dev, Diseño, Marketing, CM… (catálogo) |
| Departamento | **Área** | Hoy todos "Nexosmart" → no aporta; ver P-08 |
| Género | Opcional | Dato sensible, no requerido |
| Aniversario de trabajo | Calculado de fecha de ingreso | |
| Fecha de nacimiento | Mantener | Cumpleaños en el calendario |
| Email, Móvil | Mantener | |
| Id Slack | **Quitar** salvo P-09 | |
| Tarifa por hora (€) | Mantener, **solo visible para Admin** | Base de rentabilidad; moneda P-10 |
| Dirección | Mantener | |
| Habilidades | **Estructurado**: habilidad + nivel 0–100 | Hoy está como texto en "Acerca de" |
| Idioma | Idioma de la interfaz | |
| Fin de período de prueba | Mantener | |
| Período de notificación (inicio/fin) | Quitar (P-11) | Figura de RRHH de otros países; acá es "preaviso" |
| Estado civil | **Quitar** | Dato no necesario (minimización — Ley 25.326 / RGPD) |
| Aniversario de matrimonio | **Quitar** | Ídem |
| Tipo de empleo | Mantener como catálogo | Relación de dependencia / monotributo / freelance / pasante |
| Día de ingreso / salida | Mantener | |
| Acerca de | Mantener (bio) | |
| Informar a | **Reporta a** | Jerarquía para aprobaciones |
| Equipo de informes | Calculado (a quién le reportan) | |
| Llegadas tarde / licencias tomadas | Calculado desde Asistencia (R2) | |

Agregar: **foto de perfil** (con recorte), **contacto de emergencia** (P-12), **CUIL** (P-12).
