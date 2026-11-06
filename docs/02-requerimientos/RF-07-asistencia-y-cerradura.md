# RF-07 Asistencia y cerradura digital (R2)

Ver también `integraciones/cerradura/README.md`.

| ID | Requerimiento | Prio |
|---|---|---|
| RF-07.01 | Recibir de la cerradura las **marcas** (persona, fecha-hora, sentido entrada/salida si lo informa, dispositivo). Cerradura **Nexxt Smart Wi-Fi (Tuya)** vía Tuya Cloud API. | M (R2) |
| RF-07.02 | Guardar cada marca **cruda e inmutable** (tal cual vino) + su versión normalizada. Reprocesable. Idempotente (la misma marca dos veces = una). | M (R2) |
| RF-07.03 | Vincular usuario de la cerradura ↔ usuario de newDesk (pantalla de mapeo; marcas sin vincular quedan en "sin asignar", no se pierden). | M (R2) |
| RF-07.04 | Armar la **jornada**: entrada = primera apertura identificada; salida = botón "terminar jornada" / fin del último timer / apertura interior si se informa (G-25). Se guarda la fuente de cada dato. | M (R2) |
| RF-07.05 | **Llegada tarde** = entrada > inicio del turno + tolerancia (default 10 min ⚠P-14). Contador por mes en el perfil (hoy "Marca de asistencia tardía"). | M (R2) |
| RF-07.06 | Ausencia = día con turno de modalidad Oficina, sin marca y sin licencia. | S |
| RF-07.07 | Fichaje manual / corrección por Admin con motivo (la marca original no se borra). | M (R2) |
| RF-07.08 | Remotos: "marcar inicio de jornada" desde la app (o tomar el primer timer del día ⚠P-15). | S |
| RF-07.09 | Panel de asistencia del día (quién entró, quién falta, quién llegó tarde). | S |
| RF-07.10 | Cruce horas en oficina vs horas cronometradas (detectar jornadas con mucha diferencia). | C |

## Criterios de aceptación

- **CA-07.02** Si la cerradura reenvía 50 marcas ya recibidas, la tabla no crece.
- **CA-07.03** Una marca de un código de cerradura no vinculado aparece en "sin asignar"; al vincularlo, se imputa retroactivamente.
- **CA-07.04** Las aperturas identificadas se toman como entradas (deadbolt: la salida desde adentro no se identifica). Varias aperturas en el día (volvió de almorzar) no generan salidas falsas.
- **CA-07.x** Si la integración deja de recibir marcas por más de X horas en día hábil, alerta a Admin (falta de datos sin error ≠ "nadie vino").
