# Integrar monitoreo de servidores
- Pidió: dirección NexoSmart
- Fecha: 2026-11-06
- Prioridad que le da quien pide: alta

## Pedido original

> dentro de este proyecto tenemos que integrar monitoreo de servidores.
>
> tengo un proyecto dedicado excluisivamente a eso. debe funcionar exactamente igual, mismos estilos, mismo todo. debe funcionar con el mismo agente que corre en los servidores y hace el relevamiento de todos los servidores.
>
> el proyecto es este: C:\xampp\htdocs\clientes\monitoreo
>
> obvio en rama aparte eh

## Respuestas en la sesión de planificación (2026-11-06)

- Alcance: **todo monitoreo** (servidores, alertas, estado de proyectos, portal de clientes, reportes, roadmap, reporte diario, Discord, MCP).
- Se reescribe en Laravel; el agente Go no se toca.
- Estilos de monitoreo **solo en el módulo**.
- Clientes y proyectos **unificados** con los de newDesk; se hace una versión básica de RF-02/RF-03 en la misma rama.
- Equipo: login de newDesk tal cual. Clientes: código de 6 dígitos al mail.
- Migración: todo, con historial.
- Links viejos de `monitoreo.nexosmart.com`: redirigen a la misma página en newDesk.
- Hosting de newDesk: sin definir (P-25).

→ Absorbido en RF-13 (RF-13.01–RF-13.13), RF-02.04 y P-03 ✅. ADR-0002. Preguntas P-25..P-28.
