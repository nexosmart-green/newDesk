# TODO — newDesk

## Next steps

- `feat/monitoreo` ya trae `main` (= `dev_green`, R1 completo): unión hecha el 2026-11-10, decisiones en ADR-0002 §12.
- Carga inicial: clientes y proyectos a mano en newDesk → `monitoring:import --dry-run` → sesión en vivo con Playwright para revisar las parejas → `--link=<slug>:<código>` donde haga falta → importación real (`docs/06-migracion/corte-monitoreo.md` §2).
- Falta ejecutar el corte del monitoreo en producción: ver `docs/06-migracion/corte-monitoreo.md` (bloqueantes en la sección 0).
- Local: PHP 8.3 en `C:\tools\php83\php.exe` (el de XAMPP es 8.2), MariaDB de XAMPP en 3306 (`newdesk`, `newdesk_testing`). Sin Redis local: el test de `/health` falla por eso.
- Reloj del día (RF-05.19): confirmar con el usuario los comportamientos que eligió el front (hora tipeada se aplica al salir del campo, "Agregar parte" llena el hueco más grande, "Repartir más tarde" descarta lo cargado). Local: proyecto de prueba "Cliente Dos" (cdos) y un ▶ del admin corriendo en cdos-1.

Rama de trabajo R1: `dev_green` (pedido del usuario 2026-11-06). Monitoreo: `feat/monitoreo`.

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

- [x] RF-04.12 Mover tareas entre proyectos con sus horas (pedido 2026-11-06) — confirmar defaults P-27
- [x] RF-05.19 Reloj del día: un reloj por día, se reparte entre proyectos al frenarlo (pedido 2026-11-10) — QA-15..18 con procesos reales; probado en vivo (reparto con hueco y tarea nueva). Sin probar en vivo: negativo con ▶ ajeno corriendo y "Repartir más tarde" (cubiertos por tests)
- [x] RF-05.20 Mis horas: todos mis registros, corregir (también de tarea/proyecto) y borrar dentro de la ventana; admin con filtro por persona (pedido 2026-11-10) — QA-19 con procesos reales; probado en vivo (mover entre bolsas, candado, borrar, vista del admin)
- [x] RF-05.20 Mis horas, vista Calendario: total por día, semana y mes; tocar un día abre la lista (pedido 2026-11-12)

## Pendientes / mejoras chicas detectadas

- [x] `GET /holidays` y estado del feed iCal
- [x] Endpoint atómico `PUT /shifts/cells` para la grilla de turnos
- [x] "Sin horas disponibles" antes del clic en ▶ (`project.hours_exhausted`)
- [ ] Tiempo real por WebSocket (Reverb) — hoy polling 30 s + BroadcastChannel entre pestañas (RNF-12 lo admite). Monitoreo ya usa Reverb.
- [ ] P-25 HEIC (Imagick), P-26 fuente de feriados AR, P-23 una/varias empresas
- [ ] `.claude/bug-report.json` cuando exista URL de staging/prod

## Monitoreo (RF-13) — rama `feat/monitoreo`

- [x] F0 Papeles: inbox, RF-13 con CA, ADR-0002, P-03 ✅, P-28..P-31, arquitectura, permisos
- [x] F0 `install:broadcasting --reverb`
- [x] F1 Clientes y proyectos básicos (RF-02.01, RF-03.01, RF-03.03), rol Cliente, `users.client_id`, grupos de rutas equipo/cliente — reemplazados por los de main en la unión
- [x] F2a Servidores: ABM + probar conexión, gateway con pin, `monitoring:poll`, snapshots, métricas, `/health`, eventos Reverb, importador de servers
- [x] F2b Reglas de alerta + motor, API de alertas, chequeo de sitios/SSL, inventario, vulnerabilidades, retención
- [x] F3 Pantallas del equipo (piel monitoreo): dashboard, detalle con pestañas, uPlot, modo en vivo, alertas, ABM servers
- [x] F4 Estado de proyectos (componentes, 10 min, períodos, uptime, incidentes, PageSpeed, RDAP, carpetas) + importador
- [x] F5 Portal de clientes (login por código, páginas de estado, tiempo real)
- [x] F6 Reportes (alta, chat, notas internas, cupos, roadmap, ventanas flotantes, errorwatch) + importador con archivos
- [x] F7 Discord, resumen 9/18 h, avisos de dominio, heartbeat
- [x] F8 MCP `/mcp/monitoreo` + reporte diario
- [x] F9 Runbook del corte: `docs/06-migracion/corte-monitoreo.md`
- [x] Unión con `main` (2026-11-10): modelo de clientes/proyectos de main, un solo sistema de tokens, usuarios cliente fuera del equipo, importación con `--dry-run`/`--link`
- [ ] Sesión en vivo (Playwright) para vincular los proyectos del monitoreo con los cargados a mano
- [ ] F9 Ejecutar el corte en producción (bloqueado: hosting P-28, deploy de newDesk, invitación de usuarios)
- [ ] Probar en Linux el reporte diario por socket unix (en Windows no se puede)
- [ ] Pantalla para editar el Discord ID de los devs (hoy: endpoint + importación)

## Review

- 2026-11-06: Fase 0 documentada; PO respondió las bloqueantes.
- 2026-11-06: R1 implementado en `dev_green`. Backend 141 tests (MySQL `newdesk_testing`, incluye suite Concurrency con procesos reales). Frontend 121 tests + typecheck + lint + build (chunk principal 353 kB). E2E Playwright contra backend real: login (desktop + mobile) y flujo del cronómetro. Smoke real del MCP con curl (initialize, tools/list, whoami, 401 sin token).
- 2026-11-06 (tarde): revisiones independientes de seguridad, del módulo de horas y del front. Corregidos: doble conteo de horas en carrera stop vs corte automático, corte con chequeo viejo, ventana de corrección, borrado solo Admin, brute force del 2FA, escalada de privilegios con users.manage delegado, tokens en logs, reset que no revocaba tokens MCP, 422 antes que 404, diálogo del cronómetro que se reabría. CI ahora corre en dev_green (verde). Backend 149 tests, frontend 125.
