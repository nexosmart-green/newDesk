# Worklog — newDesk

## 2026-11-06 — Monitoreo: planificación y Fase 0

- Pedido: integrar el panel `monitoreo` (Go + React) a newDesk, igual en todo y con el mismo agente.
- Decisiones con el usuario: todo el alcance; reescribir en Laravel; estilos de monitoreo solo en el módulo; clientes y proyectos unificados (versión básica de RF-02/RF-03 en esta rama); login de equipo = newDesk, clientes = código por mail; migración con historial; links viejos redirigen. Hosting sin definir (P-25).
- Hecho: rama `feat/monitoreo`, inbox procesado, RF-13 con CA-13.01..25, ADR-0002, P-03 ✅ + P-25..P-28, permisos en la matriz, módulos en `arquitectura.md`, checklist en `tasks/todo.md`.
- Verificado en el código Go: cifrado de secretos (`internal/panel/secret/secret.go`: HMAC(clave,"monitoreo:encrypt"), AES-256-GCM, nonce 12, AAD "tabla.columna:id" con fallback sin AAD) y huella TLS (`tlsutil.Fingerprint`: SHA-256 del DER, hex minúsculas).
- Archivos: `docs/02-requerimientos/{README,RF-02-clientes,RF-13-monitoreo}.md`, `docs/03-gaps/preguntas-abiertas.md`, `docs/04-arquitectura/{arquitectura.md,adr/0002-…}`, `requerimientos-inbox/`, `tasks/`.
