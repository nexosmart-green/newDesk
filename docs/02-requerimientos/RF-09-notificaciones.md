# RF-09 Notificaciones

| ID | Evento | Destinatario | Canal | Prio |
|---|---|---|---|---|
| RF-09.01 | Te asignaron una tarea | Asignado | App + email | M |
| RF-09.02 | Te mencionaron en un comentario | Mencionado | App + email | M |
| RF-09.03 | Tarea vence mañana / venció | Asignados | App (resumen diario por email) | M |
| RF-09.04 | Proyecto al 80% / 100% de horas | Admin + líder | App + email | M |
| RF-09.05 | Tu timer se cortó (sin horas / olvidado) | Usuario | App + email | M |
| RF-09.06 | Pedido de ampliación de horas | Admin | App + email | S |
| RF-09.07 | Te asignaron/cambiaron un turno | Colaborador | App + email | M |
| RF-09.08 | Pedido de licencia / respuesta | Aprobador / solicitante | App + email | S (R2) |
| RF-09.09 | Integración cerradura sin datos | Admin | Email | M (R2) |

- Campana en el header con no leídas; marcar todas como leídas.
- Preferencias por usuario: qué eventos le llegan por email (los de bloqueo de horas no se pueden apagar para Admin).
- Email vía **Resend**, por cola, rate limit 1/s (estándar NexoSmart). Canal Slack/WhatsApp ⚠P-09.
