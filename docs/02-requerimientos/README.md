# Requerimientos

## Convención

- `RF-xx` módulo funcional; `RF-xx.yy` requerimiento puntual. `RNF-yy` no funcional.
- Prioridad MoSCoW: **M** (Must, MVP) · **S** (Should) · **C** (Could) · **W** (Won't por ahora).
- Cada requerimiento tiene **criterios de aceptación** (CA) en formato Dado/Cuando/Entonces cuando la regla no es trivial. QA prueba contra los CA, no contra la interpretación de nadie.
- Lo que depende de una pregunta abierta dice `⚠ P-xx`.

## Índice

| Módulo | Archivo | Release |
|---|---|---|
| RF-01 Usuarios, roles y perfil | `RF-01-usuarios-y-perfil.md` | R1 |
| RF-02 Clientes | `RF-02-clientes.md` | R1 |
| RF-03 Proyectos | `RF-03-proyectos.md` | R1 |
| RF-04 Tareas | `RF-04-tareas.md` | R1 |
| RF-05 Cronómetro y bolsa de horas ⭐ | `RF-05-cronometro-y-horas.md` | R1 |
| RF-06 Turnos y calendario | `RF-06-turnos-y-calendario.md` | R1 |
| RF-07 Asistencia y cerradura digital | `RF-07-asistencia-y-cerradura.md` | R2 |
| RF-08 Licencias y ausencias | `RF-08-licencias.md` | R2 |
| RF-09 Notificaciones | `RF-09-notificaciones.md` | R1 |
| RF-10 Reportes | `RF-10-reportes.md` | R2 |
| RF-11 Configuración de empresa | `RF-11-configuracion-empresa.md` | R1 |
| RF-12 Servidor MCP (Claude Code) | `RF-12-mcp-claude-code.md` | R1 |
| RNF | `RNF-no-funcionales.md` | Transversal |

## Matriz de permisos (base)

Permisos granulares agrupados en roles editables por el dueño (P-02 ✅ confirmado). "Gestor" = rol de ejemplo con permiso de cargar horas.

| Acción | Admin | Gestor | Colaborador | Cliente ⚠P-03 |
|---|---|---|---|---|
| ABM clientes | ✔ | — | — | — |
| ABM proyectos | ✔ | ✔ | — | — |
| **Cargar / ampliar horas** | ✔ | ✔ | — | — |
| Asignar miembros | ✔ | ✔ | — | — |
| Crear y asignar tareas | ✔ | ✔ | proyectos donde es miembro (P-04 ✅) | — |
| Cronometrar | ✔ | ✔ | tareas asignadas | — |
| Editar/borrar horas de otros | ✔ | — | — | — |
| Ver tarifas y rentabilidad | ✔ | — | — | — |
| Armar turnos | ✔ | con permiso | — | — |
| Ver asistencia de todos | ✔ | su equipo | la propia | — |
| Ver avance del proyecto | ✔ | ✔ | ✔ | el propio |
| Usar MCP (token personal) | ✔ | ✔ | ✔ (configurable, `mcp.use`) | — |
