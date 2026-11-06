# RF-06 Turnos y calendario

| ID | Requerimiento | Prio |
|---|---|---|
| RF-06.01 | Plantillas de turno: nombre, hora entrada, hora salida, color, modalidad (Oficina / Remoto / Híbrido). Ej.: "Mañana 9–13", "Completo 9–18". | M |
| RF-06.02 | Asignar turnos por persona y día desde una grilla semanal (personas × días), con copiar semana anterior y asignación masiva (rango de fechas + días de la semana). | M |
| RF-06.03 | Turno recurrente ("lun a vie, Completo, desde 01/12") que genera los días automáticamente. | S |
| RF-06.04 | **Mi calendario**: cada colaborador ve sus turnos, vencimientos de sus tareas, licencias y feriados (vista semana y mes). | M |
| RF-06.05 | Calendario de equipo (Admin/Líder): quién está hoy, quién está de licencia. | S |
| RF-06.06 | Feriados nacionales (AR, P-10 ✅) cargados + días no laborables propios de la empresa. | S |
| RF-06.07 | Exportar mi calendario como **feed iCal** (suscribible desde Google Calendar / celular). | S |
| RF-06.08 | Notificar al colaborador cuando le asignan o cambian un turno. | M |
| RF-06.09 | Pedido de cambio de turno por el colaborador, aprobado por Admin. | C |

- **CA-06.02** Asignar un turno a alguien que ese día tiene licencia aprobada muestra advertencia (no bloquea: decide el Admin).
- **CA-06.07** El feed iCal usa un token personal revocable; no expone datos de otros.
- **Base para R2:** el turno es lo que define la "llegada tarde" en RF-07.
