# TODO — newDesk

Rama de trabajo R1: `dev_green` (pedido del usuario 2026-11-06).

## R1 — estado

- [x] Base de arquitectura (back + front + CI + convenciones clean code/SOLID/apto IA)
- [x] E0 Fundaciones: auth SPA, invitación, reset, 2FA (obligatorio Admin), roles/permisos editables, RF-11
- [x] E1 Personas (RF-01)
- [x] E2 Clientes y proyectos (RF-02, RF-03)
- [x] E3 Tareas (RF-04) — CA-04.01 con procesos reales en paralelo
- [x] E4 Horas ⭐ (RF-05) — QA-01..08 verdes, QA-03 también con procesos reales; revisión independiente aplicada
- [x] E6 Notificaciones (RF-09 R1)
- [x] E5 Turnos y calendario (RF-06, sin cambio de turno)
- [x] E7 Reporte de horas (RF-10.01)
- [x] E13 MCP (RF-12) — 18 tools, tokens `nd_`, QA-01..03 vía MCP
- [ ] E8 Puesta en marcha — guía lista (`docs/09-operacion/puesta-en-marcha.md`); falta servidor de staging, `.claude/bug-report.json`, backups (RNF-09)

## Pendientes / mejoras chicas detectadas

- [x] `GET /holidays` y estado del feed iCal
- [x] Endpoint atómico `PUT /shifts/cells` para la grilla de turnos
- [x] "Sin horas disponibles" antes del clic en ▶ (`project.hours_exhausted`)
- [ ] Tiempo real por WebSocket (Reverb) — hoy polling 30 s + BroadcastChannel entre pestañas (RNF-12 lo admite)
- [ ] P-25 HEIC (Imagick), P-26 fuente de feriados AR, P-23 una/varias empresas

## Review

- 2026-11-06: Fase 0 documentada; PO respondió las bloqueantes.
- 2026-11-06: R1 implementado en `dev_green`. Backend 141 tests (MySQL `newdesk_testing`, incluye suite Concurrency con procesos reales). Frontend 121 tests + typecheck + lint + build (chunk principal 353 kB). E2E Playwright contra backend real: login (desktop + mobile) y flujo del cronómetro. Smoke real del MCP con curl (initialize, tools/list, whoami, 401 sin token).
- 2026-11-06 (tarde): revisiones independientes de seguridad, del módulo de horas y del front. Corregidos: doble conteo de horas en carrera stop vs corte automático, corte con chequeo viejo, ventana de corrección, borrado solo Admin, brute force del 2FA, escalada de privilegios con users.manage delegado, tokens en logs, reset que no revocaba tokens MCP, 422 antes que 404, diálogo del cronómetro que se reabría. CI ahora corre en dev_green (verde). Backend 149 tests, frontend 125.
