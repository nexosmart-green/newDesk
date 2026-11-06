# Visión y alcance

## Problema

Worksuite es un ERP genérico: 12 tabs por proyecto, decenas de módulos, campos de RRHH pensados para otra realidad. En NexoSmart **la mayoría de las tabs no se usan**. Lo que sí importa —horas por proyecto y que no se pasen— **no está resuelto**: hoy se puede seguir cronometrando aunque el proyecto ya consumió lo vendido.

## Visión

> Una herramienta donde los dueños saben en todo momento **cuántas horas quedan en cada proyecto, quién las está usando y en qué**, y donde cada colaborador ve **qué tiene que hacer hoy, en qué turno y cuánto trabajó**.

## Usuarios

| Rol | Necesita |
|---|---|
| **Dueño / Admin** | Alta de clientes y proyectos, cargar bolsas de horas, asignar gente, ver consumo y rentabilidad, armar turnos, ver asistencia. |
| **Gestor** (quien tenga permiso, P-02) | Cargar horas a proyectos, gestionar miembros. |
| **Colaborador** (dev, diseño, marketing, CM…) | Crear y asignar tareas, ver las suyas, cronometrar, ver su calendario y turnos, mantener su perfil. |
| **Cliente** (a confirmar, P-03) | ¿Ver avance y horas consumidas de su proyecto? |

## Alcance MVP (R1)

1. **Usuarios y perfil** — datos personales, puesto, área, foto, habilidades, a quién reporta.
2. **Clientes**.
3. **Proyectos** — con miembros y **bolsa de horas** que carga a mano el dueño o quien tenga permiso (no hay facturación).
4. **Tareas** — lista y tablero, estados, asignación, vencimientos.
5. **Cronómetro y horas** — iniciar al crear, un timer activo por persona, **bloqueo al agotar horas**.
6. **Turnos y calendario** — asignar turnos, cada uno ve el suyo.
7. **Notificaciones** básicas (asignación, vencimiento, horas al 80/100%).
8. **Configuración de empresa** (moneda default ARS, zona horaria, tolerancias).

## R2

- **Migración** de datos desde Worksuite (P-18: después del proyecto).
- **Asistencia** con **integración de la cerradura Nexxt (Tuya)**: ingreso por cerradura, egreso por app/timer (G-25).
- Licencias / ausencias.
- Reportes (horas por persona/proyecto/cliente, rentabilidad).

## Fuera de alcance (confirmado por el PO — G-01)

Archivos, Eventos, Gráfico de Gantt, Gráfico BurnDown, Nota, Tickets, Comentarios a nivel proyecto. Comentarios **por tarea** sí entran (son donde ocurre la conversación de trabajo).

## Métricas de éxito

- 0 proyectos con horas consumidas > horas cargadas sin una ampliación registrada.
- ≥ 90% de las horas cargadas por cronómetro (no a mano) al mes 2.
- Todo el equipo usando newDesk y Worksuite apagado en ≤ 3 meses desde R1.
