# Migración desde Worksuite

**Post-R1** (P-18: "luego del proyecto"). R1 arranca con carga inicial limpia: el dueño da de alta proyectos activos con sus horas (P-20). Este plan aplica cuando se decida traer el histórico; requiere el dump de la MySQL (P-19).

## Mapeo (a validar contra el dump real — la versión de Worksuite cambia nombres)

| Worksuite | newDesk | Notas |
|---|---|---|
| `users` + `employee_details` | `users` + `employee_profiles` | Nombre: separar nombre/apellido a mano para los casos raros ("NexoSmart Green Juan"). Contraseñas: **no** se migran → invitación para definir nueva. |
| `designations` | `job_titles` | |
| `teams` (departamentos) | `areas` | P-08 |
| `employee_details.hourly_rate` | `hourly_rates` (vigente desde ingreso) | P-10 moneda |
| `employee_skills`/texto en `about_me` | `skills` + `user_skills` | Parsear patrón `Nombre – NN%` |
| `client_details` | `clients` | |
| `projects` | `projects` | Prefijo: de `project_short_code` o generado. Tipo y horas: de la planilla P-20; sin dato → `internal` (no bloquea) y se avisa. |
| `project_members` | `project_members` | |
| `taskboard_columns` | `task_statuses` | Mapear cada columna a `kind` (open/blocked/closed) a mano. |
| `tasks` + `task_users` | `tasks` + `task_assignees` | Conservar número/código (`bhn-60`). |
| `task_comments` | `task_comments` | |
| `project_time_logs` (+ `project_time_log_breaks`) | `time_entries` `source=migration` | Descontar breaks. Logs abiertos → cerrar al momento del freeze. |
| `attendances` | `access_events` `source=manual` | Si hay datos útiles (R2). |
| `leaves` | `leave_requests` | R2. |
| Archivos, eventos, tickets, notas, discusiones | **No se migran** | Quedan en Worksuite read-only 90 días + export en ZIP. |

## Plan de corte

1. **Ensayo** en staging con dump fresco → QC de totales (QA-14). Repetir hasta que cierre en 0.
2. Comunicar fecha de corte con 1 semana.
3. **Freeze**: viernes 18:00, Worksuite en solo lectura (o se cortan los timers y se bloquea login de no-admins).
4. Dump final → migración → QC → carga de bolsas (P-20).
5. Invitaciones a todo el equipo. Lunes: newDesk en `desk.nexosmart.es`.
6. Worksuite accesible en subdominio interno, solo lectura, 90 días; luego backup frío y apagado.

## Rollback

Si el QC no cierra o hay un bug bloqueante el lunes: DNS de vuelta a Worksuite (no se tocó), y se migran las horas cargadas en newDesk en ese lapso a mano. Por eso la DB de Worksuite **no se modifica** nunca durante la migración.
