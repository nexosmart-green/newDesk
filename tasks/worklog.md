# Worklog — newDesk

## 2026-11-06 — Monitoreo: planificación y Fase 0

- Pedido: integrar el panel `monitoreo` (Go + React) a newDesk, igual en todo y con el mismo agente.
- Decisiones con el usuario: todo el alcance; reescribir en Laravel; estilos de monitoreo solo en el módulo; clientes y proyectos unificados (versión básica de RF-02/RF-03 en esta rama); login de equipo = newDesk, clientes = código por mail; migración con historial; links viejos redirigen. Hosting sin definir (P-25).
- Hecho: rama `feat/monitoreo`, inbox procesado, RF-13 con CA-13.01..25, ADR-0002, P-03 ✅ + P-25..P-28, permisos en la matriz, módulos en `arquitectura.md`, checklist en `tasks/todo.md`.
- Verificado en el código Go: cifrado de secretos (`internal/panel/secret/secret.go`: HMAC(clave,"monitoreo:encrypt"), AES-256-GCM, nonce 12, AAD "tabla.columna:id" con fallback sin AAD) y huella TLS (`tlsutil.Fingerprint`: SHA-256 del DER, hex minúsculas).
- Archivos: `docs/02-requerimientos/{README,RF-02-clientes,RF-13-monitoreo}.md`, `docs/03-gaps/preguntas-abiertas.md`, `docs/04-arquitectura/{arquitectura.md,adr/0002-…}`, `requerimientos-inbox/`, `tasks/`.

## 2026-11-06 — Monitoreo F1, F2a y F3

- F1: clientes (RF-02.01, CA-02.01) y proyectos (RF-03.01/03, CA-03.01, CA-03.03) básicos, `users.client_id`, roles Desarrollador y Cliente, permisos `monitoring.view/manage`, grupo de rutas `team` (CA-13.14).
- F2a (agente de backend, revisado): servers con token cifrado por columna (`EncryptedFor`), gateway curl con pin SPKI derivado de la huella Go, daemon `monitoring:poll`, métricas + retención, eventos Reverb, `/health` con poller, programas supervisor, importador de servers. Probado contra el agente real (fixtures) en local: pin correcto ok; huella o pin equivocados cortan antes de mandar el token (servidor espía: 0 bytes).
- Decisión: canal `monitoring.{companyId}` en vez de `monitoring` (multi-empresa).
- F3 (agente de front, revisado): dashboard, detalle con todas las pestañas, alertas y ABM de servers con la piel de monitoreo; comparación visual lado a lado con Playwright.
- Tests: back 77 ok (+1 que falla solo por falta de Redis local), front 63 ok, typecheck/lint/build ok.
