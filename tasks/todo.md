# TODO — newDesk

## Next steps (monitoreo, rama `feat/monitoreo`)

- Fase actual: ver checklist "Monitoreo (RF-13)" abajo. Plan completo: ADR-0002 + RF-13.
- Local: PHP 8.3 en `C:\tools\php83\php.exe` (el de XAMPP es 8.2), MariaDB de XAMPP en 3306 (`newdesk`, `newdesk_testing`). Sin Redis local: el test de `/health` falla por eso.

## Fase 0 — Descubrimiento

- [x] Estructura, relevamiento, RF-01..RF-12 + RNF, gaps, arquitectura, QA, backlog, handoff
- [ ] P-23 (una o varias empresas) — tiene default
- [ ] SP-01 spike cerradura Nexxt/Tuya
- [ ] Wireframes de pantallas clave

## Base de arquitectura (pedido 2026-11-06: "front y back + arquitectura base antes de módulos, pushear")

- [x] backend Laravel 12 + Pest + Sanctum + laravel/mcp + Reverb + predis + Resend
- [x] Capas `app/Domain/<Modulo>` (Actions/Models/Policies/Enums) + `Shared` (BelongsToCompany, RecordActivity, BusinessRuleViolation)
- [x] `companies` + `company_id`, roles editables con permisos granulares (enum `Permission` = Gate)
- [x] Auth Sanctum SPA (login/logout/me, throttle, usuario inactivo → 401)
- [x] Envelope `{ data, message, errors }` en todas las respuestas de error y éxito
- [x] `/health`, `/errors` (ERRORS_TOKEN), canal `errors`, sin headers de stack, raíz `{"status":"ok"}`
- [x] `supervisor:sync`, `newdesk:install`
- [x] frontend React 19 + Vite + TS strict + Tailwind v4 + React Query + router, login + layout + tema oscuro/claro
- [x] CI GitHub Actions (back: pint + pest con MySQL/Redis; front: lint, typecheck, vitest, build)
- [x] `docs/04-arquitectura/convenciones-de-codigo.md` (clean code / SOLID / apto IA)

## E0 Fundaciones (resto) — rama `feat/e0-foundations`

- [ ] Invitación por email + definir contraseña (RF-01.01) + recuperar contraseña
- [ ] 2FA (obligatorio Admin)
- [ ] ABM de roles y permisos (API + UI), usuario con rol
- [ ] RF-11 configuración de empresa (API + UI)
- [ ] Test de policy 403/404
- [ ] `.claude/bug-report.json` cuando exista URL de staging/prod

## Monitoreo (RF-13) — rama `feat/monitoreo`

- [x] F0 Papeles: inbox, RF-13 con CA, ADR-0002, P-03 ✅, P-25..P-28, arquitectura, permisos
- [ ] F0 `install:broadcasting --reverb`
- [ ] F1 Clientes y proyectos básicos (RF-02.01, RF-03.01, RF-03.03), rol Cliente, `users.client_id`, grupos de rutas equipo/cliente
- [ ] F2a Servidores: ABM + probar conexión, gateway con pin, `monitoring:poll`, snapshots, métricas, `/health`, eventos Reverb, importador de servers
- [ ] F2b Reglas de alerta + motor, API de alertas, chequeo de sitios/SSL, inventario, vulnerabilidades, retención
- [ ] F3 Pantallas del equipo (piel monitoreo): dashboard, detalle con pestañas, uPlot, modo en vivo, alertas, ABM servers
- [ ] F4 Estado de proyectos (componentes, 10 min, períodos, uptime, incidentes, PageSpeed, RDAP, carpetas) + importador
- [ ] F5 Portal de clientes (login por código, páginas de estado, tiempo real)
- [ ] F6 Reportes (alta, chat, notas internas, cupos, roadmap, ventanas flotantes, errorwatch) + importador con archivos
- [ ] F7 Discord, resumen 9/18 h, avisos de dominio, heartbeat
- [ ] F8 MCP `/mcp/monitoreo` + reporte diario
- [ ] F9 Corte: importación completa, redirects, agentes, supervisor, `MONITORING_NOTIFY=true`

## Siguientes: E1 → E2 → E3 → E4 ⭐ → E6/E5/E7 → E13 MCP → E8

## Review

- 2026-11-06: Fase 0 documentada; PO respondió las bloqueantes.
- 2026-11-06: base de arquitectura lista. Back 14 tests verdes (MySQL `newdesk_testing`), front 4 tests + build + lint verdes. Smoke real con curl: `/` ok sin headers de stack, `/api/health` 200, CSRF → login 200 → `/me` 200.
