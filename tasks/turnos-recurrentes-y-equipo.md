# Turnos recurrentes (RF-06.03) y calendario de equipo (RF-06.05) — 2026-11-12

Últimos ítems de R1 (E5) que faltaban. Rama `dev_green`.

**Convivencia con `feat/monitoreo`:** esa rama modifica `Scheduling/Actions/AssignShifts.php`, `SetShiftCells.php`,
`Scheduling/Queries/Calendar.php`, `frontend/src/features/calendar/CalendarViews.tsx`, `features/hours/HoursCalendar.tsx`,
además de `router.tsx`, `navigation.ts`, `index.css`, `lib/api.ts`, fixtures. **No editar esos archivos**: usar las clases
existentes desde código nuevo. Migraciones `2026_11_12_0002xx_`.

## RF-06.03 Turno recurrente
"Lun a vie, Completo, desde 01/12" genera los días solo.

Modelo `shift_recurrences (id, company_id, user_id, shift_template_id, weekdays json [1..7 ISO], starts_on, ends_on null,
skip_holidays bool default true, created_by, timestamps)`. Columna nueva nullable `shift_assignments.recurrence_id`
(FK nullOnDelete) para saber qué días generó cada regla.

- Al crear: genera asignaciones desde `starts_on` hasta `min(ends_on, hoy + 8 semanas)` en la zona de la empresa.
- Comando `shifts:extend-recurrences` diario (registrado en el schedule como los demás) mantiene el horizonte de 8 semanas.
  Idempotente.
- Nunca pisa: si la persona ya tiene un turno ese día (manual u otra regla), ese día se saltea. Feriados (nacionales y de
  la empresa) se saltean si `skip_holidays`.
- Respuesta de creación: la regla + `generated` (días creados) + `skipped: [{date, reason: 'holiday'|'taken'}]` (máx 20 en la respuesta, total en `skipped_count`).
- **Finalizar**: `PUT /shift-recurrences/{id}/end {ends_on}` (≥ hoy). Borra los días futuros (> ends_on) que generó esa
  regla; los pasados quedan. **Borrar** = finalizar hoy + borrar la regla (los días pasados generados quedan con
  `recurrence_id` null).
- Notifica a la persona una vez por operación usando el evento existente `ShiftsChanged` (CA RF-06.08).
- Template borrado (soft) → la regla deja de generar (el comando la saltea) y se muestra como "plantilla eliminada".
- Persona dada de baja → no se generan días nuevos.

Endpoints (`module:calendar`, permiso `shifts.manage`, otra empresa → 404):
- `GET /api/v1/shift-recurrences?user_id=` → `[{id, user:{id,full_name}, template:{id,name,color,starts_at,ends_at,mode}|null, weekdays, starts_on, ends_on, skip_holidays, created_at}]`
- `POST /api/v1/shift-recurrences {user_ids: int[], shift_template_id, weekdays: int[], starts_on, ends_on?, skip_holidays?}`
  → una regla por persona; 201 `{data: [{recurrence, generated, skipped_count, skipped}]}`. Validaciones con mensajes claros:
  weekdays 1..7 sin repetir y no vacío, starts_on ≥ hoy − 0 (no hacia atrás), ends_on ≥ starts_on, usuarios activos de la empresa.
- `PUT /api/v1/shift-recurrences/{id}/end {ends_on}` → regla actualizada + `removed` (días borrados).
- `DELETE /api/v1/shift-recurrences/{id}` → `removed`.
- Actividad: `shift_recurrence.created|ended|deleted` (sin proyecto).

## RF-06.05 Calendario de equipo
"Quién está hoy" (licencias son R2: no se incluyen todavía).

`GET /api/v1/team-calendar?from=Y-m-d&to=Y-m-d` (`module:calendar`; rango ≤ 31 días, 422 claro):
- Con `shifts.manage`: todas las personas activas de la empresa. Sin ese permiso: yo + quienes me reportan
  (`reports_to`). Si no reporta nadie a mí y no tengo el permiso → 403 "El calendario de equipo es para quien gestiona turnos o tiene gente a cargo."
- Respuesta: `{timezone, people: [{id, full_name, avatar, job_title}], days: [{date, holiday: {name, scope}|null,
  shifts: [{user_id, starts_at:'HH:mm', ends_at:'HH:mm', mode, template_name, color}]}]}`. Sin N+1.

## Frontend (skills `frontend-design:frontend-design` + `ui-ux-pro-max`, paleta desk)
- **Turnos → panel "Turnos fijos"** (dentro de la página de Turnos existente, componente nuevo): lista de reglas por
  persona (chips de días L M M J V S D, plantilla con color, desde/hasta, "Sin fin"), botón "Nuevo turno fijo" →
  diálogo (personas multi, plantilla, días con toggles + atajos "Lun a vie", desde, hasta opcional, saltear feriados) →
  toast con "Se generaron N días" y, si hubo salteados, detalle ("3 feriados, 2 ya tenían turno"). Finalizar (fecha) y
  borrar con confirmación que explica qué pasa con los días ya cargados. La grilla semanal se refresca.
- **Calendario → pestaña "Equipo"** (`/calendario?vista=equipo`, solo si el usuario puede: tiene `shifts.manage` o gente a
  cargo; ocultar si el endpoint da 403): arriba "Hoy" con tarjetas: **trabajando ahora** (turno en curso según hora local),
  **más tarde**, **ya terminó**, **sin turno**, y feriado si corresponde; abajo grilla semana (personas × días) con el
  turno de cada día (color de plantilla, horario, modalidad con ícono). Navegar semanas. 375px: lista por día.
- Siempre: skeletons, loading en botones, toasts con mensaje del backend, errores bajo el campo, vacíos con acción,
  "Reintentar". Tests también con `TZ=UTC`.

## Checklist
- [x] Backend + tests Pest
- [x] Frontend + tests Vitest (local y UTC)
- [x] Integración, E2E, capturas, review
- [x] Commit/push `dev_green`, CI verde, merge a `main`

## Review (2026-11-12)
- Backend 236 tests (18 nuevos), frontend 236 (14 nuevos) local y TZ=UTC; pint, typecheck, lint y build verdes.
- Desvíos: columna extra `shift_recurrences.generated_until` (el comando retoma desde ahí: idempotente y nunca rellena un día
  liberado a mano); `shifts:extend-recurrences` no notifica (correr el horizonte no cambia el turno de nadie); finalizar con
  una fecha posterior vuelve a generar; finalizar antes del inicio → 422 "borralo"; `team-calendar` agrega `id` en cada turno
  y `job_title` va como texto.

## Integración (2026-11-12)
- Backend 236 tests, frontend 236 (local y TZ=UTC), E2E 6/6 (nuevo `e2e/shifts-team.spec.ts`).
- Review independiente: corregido el job nocturno (re-lee cada regla con lock, una falla no corta la corrida, `insertOrIgnore` ante un turno manual concurrente) y el "hoy" del calendario de equipo avanza solo pasada la medianoche (`useNow` compartido).
- Seed dev: plantillas de turno (`ShiftTemplateSeeder`) y rol **Coordinación** con `shifts.manage` + usuaria coordinador@nexosmart.com / password con Juan a cargo (`ShiftCoordinatorSeeder`). El Gestor base no gestiona turnos y el Admin pide 2FA.
