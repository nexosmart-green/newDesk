# Modelo de datos (borrador v0)

Tiempos en UTC. Duraciones en **segundos enteros**. Borrado lógico (`deleted_at`) en entidades de negocio.

```
users (id, first_name, last_name, email, password, role, status[active|inactive],
       avatar_path, locale, timezone, two_factor_*, last_login_at)

employee_profiles (user_id PK/FK, employee_code, job_title_id, area_id, employment_type_id,
       reports_to_user_id, phone, address, birth_date, gender NULL, about,
       hired_at, left_at, probation_ends_at, cuil NULL, emergency_contact NULL)

hourly_rates (id, user_id, amount, currency, valid_from, valid_to NULL)       -- solo Admin
job_titles (id, name) · areas (id, name) · employment_types (id, name)
skills (id, name) · user_skills (user_id, skill_id, level 0..100)

clients (id, legal_name, trade_name, tax_id, email, phone, notes, status)
client_contacts (id, client_id, name, email, phone, role)

projects (id, client_id NULL, name, code_prefix UNIQUE, description, type[bag|monthly|internal],
          status[active|paused|finished|cancelled], leader_user_id, currency,
          starts_on, ends_on, consumed_seconds (cache), task_sequence)
project_members (project_id, user_id, added_at)
hour_allocations (id, project_id, seconds (+/-), reason, price NULL, reference NULL,
                  period YYYY-MM NULL (monthly), created_by, created_at)      -- solo INSERT
hour_extension_requests (id, project_id, requested_by, message, status, resolved_by, resolved_at)

task_statuses (id, name, color, kind[open|blocked|closed], position)
tasks (id, project_id, number, code (=prefix-number), title, description, status_id,
       priority, starts_on, due_on, estimate_seconds NULL, completed_at, created_by)
       UNIQUE(project_id, number)
task_assignees (task_id, user_id)
task_comments (id, task_id, user_id, body, created_at) · comment_mentions (comment_id, user_id, read_at)
attachments (id, attachable_type, attachable_id, path, mime, size, uploaded_by)
checklist_items (id, task_id, text, done, position)

time_entries (id, user_id, task_id, project_id (denorm), started_at, ended_at NULL,
              seconds NULL, source[timer|manual|migration], channel[web|mcp],
              comment NULL (obligatorio al cerrar, ≥10 chars), comment_pending bool (corte automático),
              stop_reason[user|switch|budget_exhausted|forgotten|task_closed|project_closed|user_deactivated|admin],
              active_user_id GENERATED (IF ended_at IS NULL, user_id, NULL) UNIQUE)

shift_templates (id, name, starts_at TIME, ends_at TIME, color, mode[office|remote|hybrid])
shift_assignments (id, user_id, date, shift_template_id, starts_at, ends_at, mode, recurrence_id NULL)
                  UNIQUE(user_id, date, starts_at)
shift_recurrences (id, user_id, shift_template_id, weekdays, from, to NULL)
holidays (id, date, name, scope[national|company])
calendar_feed_tokens (user_id, token_hash, revoked_at)

-- R2
access_devices (id, provider, external_id, name, location)
access_identities (id, device_id, external_user_id, user_id NULL)          -- mapeo cerradura ↔ usuario
access_events_raw (id, device_id, external_event_id NULL, payload JSON, hash UNIQUE, received_at)
access_events (id, raw_id, user_id NULL, occurred_at, direction[in|out|unknown], direction_inferred,
               source[device|manual], created_by NULL, reason NULL)
attendance_days (user_id, date, first_in, last_out, office_seconds, shift_assignment_id,
                 late_seconds, status[present|late|absent|leave|holiday])  -- recalculable
leave_types (id, name, yearly_quota_days NULL, requires_attachment)
leave_requests (id, user_id, leave_type_id, from, to, half_day, reason, status, approver_id)

notifications (Laravel estándar) · notification_preferences (user_id, event, email bool)
personal_access_tokens (Sanctum: tokenable, name, token hash, last_used_at, expires_at) -- tokens MCP, prefijo nd_
mcp_settings (role_id, enabled)  -- o permiso mcp.use en el rol

activity_log (id, subject_type, subject_id, project_id NULL, actor_id, event, before JSON, after JSON, created_at)
```

## Invariantes (tests obligatorios)

1. Un usuario tiene como máximo 1 `time_entry` con `ended_at NULL`.
2. Para proyectos `bag`/`monthly`: `Σ seconds(time_entries) ≤ Σ hour_allocations` (+ tolerancia R-1, recortada al cerrar). **Excepción RF-05.19:** el reparto del reloj del día y los ▶ que quedaron `uncapped` (R-6) pueden dejarla en negativo; se avisa a Admin + líder.
3. `projects.consumed_seconds = Σ seconds` de sus registros cerrados (reconciliación nocturna).
4. `tasks.number` correlativo sin huecos por proyecto.
5. `hour_allocations` y `access_events_raw` nunca se actualizan ni borran.
6. Todo `time_entry` cerrado tiene `comment` no vacío o `comment_pending = true`; un usuario con `comment_pending` no puede abrir otro timer.
7. Un usuario tiene como máximo 1 `day_clock` sin repartir (`split_at NULL`, corriendo o pendiente): UNIQUE sobre la columna generada `unsplit_user_id`. Con uno así no puede abrir un ▶ ni otro día (RF-05.19).
