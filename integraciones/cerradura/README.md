# Integración cerradura digital

**Dispositivo:** Nexxt Solutions Smart Wi-Fi Door Lock, deadbolt negro — probable **NHS-D150 BK** (huella, tarjeta RFID, PIN, llave, app). Confirmar con foto de la etiqueta (P-24).
**App:** Nexxt Home → es una app de marca blanca sobre **Tuya**. La app ya guarda historial de accesos ("access activity records/logs").

Requerimientos: `docs/02-requerimientos/RF-07-asistencia-y-cerradura.md`.

## Camino propuesto: Tuya Cloud (escenario A/B)

1. Crear proyecto en **Tuya Developer Platform (IoT Core)**, data center **Western America** (región de cuentas de Latinoamérica — verificar).
2. Vincular la cuenta de la app. Riesgo: las cuentas de apps OEM (Nexxt Home) **no siempre se pueden vincular** al IoT Platform; sí las de **Smart Life / Tuya Smart**.
   - Plan B: re-emparejar la cerradura con la app **Smart Life** (los dispositivos Tuya OEM normalmente aceptan) y administrarla desde ahí. Re-cargar huellas/PIN no hace falta si el reset no lo exige — verificar en el spike.
3. Leer **registros de apertura** por la OpenAPI de cerraduras (consulta de unlock records por dispositivo y rango de fechas) con polling cada 5 min; evaluar Message Service (Pulsar) para tiempo real.
4. Cada registro trae el **método** (huella/tarjeta/PIN/app) y el **ID de usuario de la cerradura** → mapeo a usuario newDesk (RF-07.03).

### Spike técnico (1–2 días, antes de comprometer R2)

- [ ] Foto de etiqueta → modelo exacto.
- [ ] Vincular cuenta (Nexxt Home o Smart Life) al IoT Platform y listar el dispositivo.
- [ ] Traer los registros de apertura de la última semana por API y ver qué campos vienen (usuario, método, hora, ¿sentido?).
- [ ] Confirmar plan de la cuenta Tuya (el plan gratuito de desarrollo tiene cupo de llamadas y vence: ver costo de renovación).
- [ ] Resultado → actualizar G-15 / G-25.

## Limitación funcional importante (G-25)

Una cerradura deadbolt registra **quién abrió desde afuera**. La salida (girar la perilla de adentro) normalmente:
- no se registra, o
- se registra como "apertura manual" **sin usuario**.

Consecuencia: **la cerradura sirve para la entrada, no para la salida.** Propuesta:
- **Entrada** = primera apertura identificada del día.
- **Salida** = lo que llegue primero de: botón "terminar jornada" en newDesk · fin del último timer del día · (si la cerradura lo informa) apertura interior. Se marca qué fuente se usó.

## Diseño (independiente del proveedor)

```
Tuya Cloud ──► TuyaAccessProvider ──► access_events_raw (idempotente, inmutable)
                                            │
                                            ▼
                                     Normalizador ──► access_events ──► attendance_days
```

- Interfaz `AccessControlProvider`: `fetchEvents(since)`, `handleWebhook(request)`, `listUsers()`.
- Un adaptador por marca: cambiar de cerradura = un adaptador nuevo.
- Credenciales Tuya (client id/secret) cifradas en DB, configurables por empresa.
- Monitor de silencio (CA-07.x): la cerradura es a pilas y Wi-Fi 2.4 GHz → caerse es esperable; sin datos ≠ nadie vino.

## Escenarios alternativos (si el spike falla)

| # | Tipo | Cómo |
|---|---|---|
| C | Home Assistant local (integración Tuya) | Agente en la oficina que reenvía eventos a la API de newDesk. |
| D | Export manual desde la app | Importador de archivo. |
| E | Nada accesible | Fichaje desde newDesk (RF-07.08). |
