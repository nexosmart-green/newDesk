# RF-13 Monitoreo de servidores y proyectos

Origen: `requerimientos-inbox/procesados/2026-11-06-monitoreo.md`. Decisión técnica: ADR-0002.

**Regla general:** el módulo se comporta **igual que el panel `monitoreo`** (repo `nexosmart-violet/monitoreo`) en su versión del 2026-11-06. Ante cualquier duda de comportamiento, la referencia es su código y sus decisiones `D-xxx` (`docs/decisions.md` de ese repo), salvo las diferencias listadas al final. El agente Go que corre en los servidores **no se modifica**.

| ID | Requerimiento | Prio | Referencia |
|---|---|---|---|
| RF-13.01 | ABM de servidores (nombre, IP, puerto SSH, puerto del agente, token, huella TLS), probar conexión, forzar actualización. Estados: en línea, agente caído, caído, desconocido, deshabilitado. | M | D-003, D-013 |
| RF-13.02 | Consulta periódica a cada agente por sección con los intervalos de monitoreo (health 30 s, metrics 1 min o 5 s en vivo, services/pm2/supervisor 1 min, security/ports 5 min, cron/projects/sites 10 min, system/databases/vulnerabilities 1 h, dependencies/large_files 6 h, packages 12 h). | M | D-008, D-033, D-106 |
| RF-13.03 | Dashboard de servidores con buscador de proyectos del inventario; detalle del servidor con sus pestañas (resumen, proyectos, sitios, procesos, servicios, cron, dependencias, paquetes, bases, puertos, seguridad) y gráficos de métricas 1h/24h/7d/30d/1y. | M | D-031, D-035, D-036 |
| RF-13.04 | Alertas: las 15 reglas de monitoreo con sus umbrales y antirrebote; listado de abiertas y resueltas. | M | D-017, D-024, D-095, D-107 |
| RF-13.05 | Chequeo HTTP de los sitios de cada servidor cada 5 min y vencimiento SSL. | M | D-017, D-075, D-085 |
| RF-13.06 | Estado de proyectos: componentes (http, health, pm2, supervisor, timer), estado por minuto, tramos degradado/caído, uptime, incidentes y notas, tiempos de respuesta, carpetas vinculadas, sugerencias, PageSpeed semanal, vencimiento de dominio, vulnerabilidades de dependencias, devs principal y secundarios. | M | D-039..D-045, D-053, D-071, D-121 |
| RF-13.07 | Portal de clientes: el cliente entra con un código por mail y ve sus proyectos como Web/App/CMS con su marca, uptime, tiempos de respuesta y aviso de incidente. | M | D-037, D-038, D-045, D-063 |
| RF-13.08 | Reportar un problema: alta con captura y chequeo en vivo, chat con imágenes en tiempo real, notas internas, estados, leídos, cola del equipo con contador, ventanas flotantes y roadmap personal. | M | D-041, D-049..D-051, D-064..D-066 |
| RF-13.09 | Avisos: Discord por bot (canal por proyecto y `#resumen`), resumen por mail a las 9 y 18 h (Argentina), heartbeat externo, errores nuevos del `/api/errors` de cada proyecto. | M | D-054, D-059..D-061, D-073 |
| RF-13.10 | MCP `monitoreo` con las mismas 12 herramientas, con el token personal de newDesk (RF-12). | M | D-050, D-051, D-092 |
| RF-13.11 | "Generar reporte" del día con el Claude del servidor. | S | D-074 |
| RF-13.12 | Migración desde la base `monitoreo` con historial, y convivencia con el panel Go hasta el corte. | M | ADR-0002 |
| RF-13.13 | Las pantallas del módulo y el portal se ven igual que monitoreo (Inter, acento grafito, claro/oscuro). El resto de newDesk mantiene su diseño. | M | D-031 |

## Criterios de aceptación

### Servidores y agente
- **CA-13.01** El token del agente nunca sale del backend: ninguna respuesta de la API lo devuelve, y en la base se guarda cifrado y atado a su tabla y columna.
- **CA-13.02** Dado un servidor cuya huella TLS no coincide, cuando se lo consulta, entonces la conexión se corta **antes** de enviar el token y el servidor muestra "la huella no coincide".
- **CA-13.03** Dado un servidor cuyo health falla 2 veces seguidas, cuando el puerto SSH responde, entonces queda "agente caído"; si tampoco responde, queda "caído".
- **CA-13.04** Una sección que falla se reintenta a los 2 min × 2^(fallos−1), nunca más tarde que su intervalo. Un 404 (agente viejo sin esa sección) no suma reintentos.
- **CA-13.05** Un servidor ocupa como mucho 2 de los 6 lugares de consulta simultánea; el health no ocupa lugar (D-106).
- **CA-13.06** Con una pestaña del detalle abierta, las métricas de ese servidor se actualizan cada 5 s; hasta 30 s después de cerrarla vuelven a 1 min.
- **CA-13.07** Cambiar IP, puerto o huella de un servidor exige volver a cargar el token.

### Alertas y sitios
- **CA-13.08** Umbrales: CPU > 90 %, memoria > 90 %, swap > 80 %, disco 85 % aviso / 95 % crítico, SSL 14 días aviso / 3 días crítico, más de 200 logins SSH de root fallidos por hora. Servicios y Supervisor caídos recién tras 2 consultas y 90 s (D-024).
- **CA-13.09** Un sitio está caído solo con 5xx o sin respuesta; sin respuesta se reintenta 2 veces a los 10 s; un 429 cuenta como funcionando (D-017, D-075).
- **CA-13.10** El chequeo de sitios solo conecta con IPs públicas (D-085).
- **CA-13.11** Si un servidor abre más de 10 alertas a la vez, se avisa un resumen y no una por una (D-107).

### Estado de proyectos y portal
- **CA-13.12** Un componente pasa a "Caído" (o "Degradado") recién tras 10 min seguidos de resultados malos, contados desde el primero de ellos; un corte más corto no deja rastro (D-071, D-121).
- **CA-13.13** El cliente solo ve sus proyectos, como partes Web/App/CMS: nunca IPs, puertos, nombres de servidor ni componentes técnicos (D-045).
- **CA-13.14** Un usuario cliente recibe 403 en cualquier ruta fuera del portal; un usuario del equipo no usa las rutas del portal (D-037).
- **CA-13.15** Login de cliente: código de 6 dígitos válido 10 min, solo en el navegador que lo pidió, máximo 5 por hora por cuenta, dirección idéntica a la guardada; una dirección desconocida recibe la misma respuesta que una conocida (D-076, D-077, D-082, D-087). La sesión dura 30 días.
- **CA-13.16** Cuando un componente vuelve de caído, el cliente recibe un mail de "ya funciona" sin la duración de la caída (D-063).

### Reportes
- **CA-13.17** Un cliente puede crear como mucho 5 reportes por hora y 20 por día, y guardar hasta 200 MB de imágenes (D-090, D-091).
- **CA-13.18** Una nota interna nunca llega al cliente, ni por la API ni en tiempo real (D-051).
- **CA-13.19** El roadmap de reportes es personal: nadie ve el de otro (D-066).

### Avisos y MCP
- **CA-13.20** Mientras newDesk convive con el panel Go (`MONITORING_NOTIFY=false`), no sale ningún aviso: ni Discord, ni mails de monitoreo, ni heartbeat.
- **CA-13.21** En Discord, todo texto que viene de un cliente o de una app va escapado y en una sola línea (D-078).
- **CA-13.22** En el MCP, el texto que escribió un cliente llega entre `<datos_del_cliente>` y `</datos_del_cliente>`; las listas traen por defecto solo los proyectos del dueño del token (principal o secundario) y los que no tienen devs (D-092, D-053).

### Migración
- **CA-13.23** La importación lee la base `monitoreo` sin escribirla, conserva los IDs de servidores, alertas, componentes y reportes, y se puede volver a correr desde cero con el mismo resultado.
- **CA-13.24** Tras importar, cada servidor responde con el mismo token que usaba el panel Go (descifrado y vuelto a cifrar).

### Listados
- **CA-13.25** Los listados del módulo muestran 10 por página, o todo junto si son menos de 20 (D-032).

## Diferencias aceptadas con el panel Go

1. El equipo entra con el login de newDesk (email + contraseña), no con código + TOTP.
2. Las pantallas del equipo viven bajo `/monitoreo/...`; el portal sigue en `/portal/...`.
3. El claro/oscuro del módulo sigue el botón de newDesk; el portal mantiene el de monitoreo.
4. Crear cliente o proyecto pide también el código del proyecto y el estado (RF-03.01).
5. Usuarios del equipo y sesiones son los de newDesk; en el módulo quedan servidores y usuarios de cada cliente.
6. Los tokens MCP `mon_` no se migran: cada dev genera uno nuevo en newDesk.
7. Los clientes piden código una vez después del corte (las sesiones no se migran).
8. El modo en vivo se apaga hasta 30 s después de cerrar la pestaña.
9. Ver monitoreo depende del permiso `monitoring.view`, no de ser miembro del proyecto (CA-03.03 aplica a las demás pestañas del proyecto).
