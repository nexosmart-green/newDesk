# ADR-0002 — Monitoreo dentro de newDesk: reescribir el panel en Laravel, mismo agente

- Estado: **Aceptado** (2026-11-06)
- Fecha: 2026-11-06

## Contexto

El monitoreo de servidores es un proyecto aparte (`monitoreo`): un panel Go + React que consulta por HTTPS a un agente Go instalado en cada servidor. Se pidió integrarlo a newDesk "exactamente igual" y con el mismo agente (RF-13).

El agente no envía nada: responde `GET https://<ip>:9100/v1/<sección>` solo a IPs de su whitelist, con `Authorization: Bearer <token>` (un solo token por agente) y un certificado autofirmado que el panel reconoce por su huella SHA-256.

## Opciones

| | Reescribir en Laravel | Dejar el panel Go como motor y que newDesk lo muestre |
|---|---|---|
| Código | Mucho (~17k líneas Go + 10k TS) | Poco |
| Sistemas en producción | Uno | Dos, con dos bases y auth servicio-a-servicio |
| Clientes/proyectos | Los mismos de newDesk | Duplicados |
| Convenciones | Las de newDesk | Las de otro repo |

## Decisión

Se reescribe el panel en newDesk; el agente no cambia. Puntos de diseño:

1. **Poller:** un proceso largo `php artisan monitoring:poll` supervisado por `supervisor:sync`, que consulta en paralelo con Guzzle curl-multi. Mismos intervalos, backoff y lugares (6 en total, 2 por servidor) que el Go. El resto de lo periódico va al scheduler y a colas Redis.
2. **Pin TLS:** curl `CURLOPT_PINNEDPUBLICKEY` (pin de la clave pública), calculado una vez con un handshake PHP que se verifica contra la huella SHA-256 del certificado que guarda el Go (hex en minúsculas, sin separadores). El token nunca se manda antes de validar el pin.
3. **Estado de alertas** (rachas, contadores) en Redis con TTL, para que cualquier proceso pueda evaluar reglas.
4. **Tiempo real:** Reverb reemplaza a SSE. El modo en vivo es un latido del navegador que deja una clave con TTL en Cache.
5. **Secretos:** cast cifrado atado a tabla y columna (`EncryptedFor`), para que un valor copiado a otra columna no se descifre (H9 de la auditoría de monitoreo).
6. **Clientes y proyectos unificados:** los clientes de monitoreo son `clients` (RF-02) y los proyectos son `projects` (RF-03) con columnas de monitoreo. El dev principal es el responsable; los secundarios, miembros.
7. **Login:** el equipo usa el de newDesk; los clientes, un código de 6 dígitos por mail (rol Cliente, rutas en un grupo aparte).
8. **Estilos:** la piel de monitoreo vive bajo `[data-skin="monitoreo"]`, que redefine las variables de color solo en ese subárbol.
9. **Migración:** importador de solo lectura sobre la base `monitoreo` que conserva los IDs de servidores, alertas, componentes y reportes (los links viejos siguen andando tras el redirect). Los cruces de clientes, proyectos y usuarios quedan en `import_mappings`.
10. **Convivencia:** hasta el corte, newDesk consulta a los agentes con el mismo token pero con `MONITORING_NOTIFY=false` (sin Discord, mails ni heartbeat).

## Consecuencias

- Es un port grande (~10 fases). Cada fase queda en `feat/monitoreo` con sus tests.
- Si newDesk no corre en el Servidor 5, hay que sumar su IP a `allowed_ips` y ufw de cada agente (solo config). P-25.
- Los tokens MCP `mon_` se pierden: cada dev genera uno nuevo.
- Long-running PHP: el poller se reinicia solo por edad o memoria, y `/health` controla que siga vivo.
