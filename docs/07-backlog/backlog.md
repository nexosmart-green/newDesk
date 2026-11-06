# Backlog

## Fase 0 — Descubrimiento

- [x] Estructura del proyecto y documentos base
- [x] Relevamiento as-is desde el pedido y la captura
- [x] Requerimientos RF-01..RF-11 + RNF con CA
- [x] Gap analysis + preguntas abiertas
- [x] PO respondió las 🔴 (2026-11-06): proyecto nuevo, colaborador crea/asigna, horas manuales sin facturación, ARS configurable, migración post-R1, cerradura Nexxt (Tuya)
- [x] P-02 roles editables con permisos (confirmado)
- [ ] P-23 (¿una o varias empresas?) — tienen default, no bloquean
- [ ] Wireframes: Mis tareas, Proyecto (5 tabs), cronómetro header, grilla de turnos, Mi calendario
- [ ] Quick win: nombre de empresa en Worksuite (G-22)

## R1 — MVP (estimado 5–6 semanas)

| Épica | Contenido | Sprint |
|---|---|---|
| E0 Fundaciones | Scaffolding Laravel 12 + React/Vite, CI, staging, Supervisord, `/health` `/errors` (`/bug-report setup`), auth + roles/permisos + policies, `companies` + RF-11 | S1 |
| E1 Personas | RF-01 perfiles, foto, catálogos, habilidades | S1–S2 |
| E2 Clientes y proyectos | RF-02, RF-03 (5 tabs, miembros, actividad) | S2 |
| E3 Tareas | RF-04 lista + tablero, estados, comentarios/menciones, Mis tareas | S2–S3 |
| E4 Horas ⭐ | RF-05 completo + reglas de concurrencia + suite QA-01..08 | S3–S4 |
| E5 Turnos y calendario | RF-06 (sin cambio de turno) | S4 |
| E6 Notificaciones | RF-09 R1 | S3–S4 |
| E7 Reporte de horas | RF-10.01 | S4 |
| E13 MCP Claude Code | RF-12: tokens personales, pantalla Conexiones de IA, tools R1, tests de paridad web/MCP | S4–S5 |
| E8 Puesta en marcha | Carga inicial (usuarios, clientes, proyectos activos + horas), invitaciones, apagar Worksuite | S5 |

## Spike paralelo (no bloquea R1)

- [ ] **SP-01 Cerradura Nexxt/Tuya** (1–2 días): ver checklist en `integraciones/cerradura/README.md`. Define el esfuerzo de E9.

## R2

- E9 Asistencia + cerradura (RF-07) — según resultado de SP-01
- E10 Licencias (RF-08)
- E11 Reportes completos (RF-10)
- E12 Migración del histórico de Worksuite (`06-migracion`)
- PWA instalable

## R3 / ideas

- Portal de clientes (P-03)
- Notificaciones por WhatsApp
- Pedido de cambio de turno
