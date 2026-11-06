# Corte: del panel `monitoreo` (Go) a newDesk

RF-13.12 · ADR-0002. Pasos manuales, en orden. Nada se corre con scripts. Cada paso en un servidor compartido se confirma antes de ejecutarlo.

## 0. Bloqueantes antes de empezar

| Qué | Por qué | Estado |
|---|---|---|
| Decidir dónde corre newDesk (P-25) | Si no es el Servidor 5 (51.222.205.245), hay que sumar su IP en cada agente (paso 2.3) y crear su propio socket de Claude para el reporte diario | ⏳ |
| newDesk desplegado con Redis, Reverb, `supervisor:sync` (poller, workers `monitoring` y `discord`, scheduler, Reverb) | Sin el poller no hay datos; sin Reverb no hay tiempo real | ⏳ |
| Los usuarios del equipo pueden entrar a newDesk | La importación crea a los que no existen como **invitados sin contraseña**. Hace falta la invitación de E0 (RF-01.01) o que se les defina contraseña antes del corte | ⏳ |
| PHP-FPM y nginx con timeout ≥ 100 s, `upload_max_filesize` y `post_max_size` ≥ 16 MB | "Generar reporte" espera hasta 90 s al Claude del server; chequeo en vivo al reportar (~25 s); capturas | ⏳ |
| El usuario de PHP-FPM puede abrir `/run/monitor-claude.sock` (`root:monitor-panel 0660`) | Reporte diario. Usar un grupo dedicado, no `www-data`: si no, cualquier app PHP del server llega al Claude de root. Variable opcional `MONITORING_CLAUDE_SOCKET` | ⏳ |
| Probar una vez en Linux el reporte diario por socket | En Windows (dev) no hay sockets unix: ese camino no se ejecutó nunca | ⏳ |
| `config/reverb.php` con `max_request_size` más alto, si las métricas en vivo de algún server lo superan | El evento `metrics` con `full` (procesos) puede pasar los 10 KB por defecto | Medir en convivencia |

## 1. Variables nuevas en el `.env` de newDesk

Son todas nuevas: no cambian el comportamiento de nada que ya exista.

```
MONITORING_NOTIFY=false            # convivencia: no sale ningún aviso (CA-13.20)
MONITOREO_DB_HOST=…                # base `monitoreo`, usuario de solo lectura
MONITOREO_DB_PORT=3306
MONITOREO_DB_DATABASE=monitoreo
MONITOREO_DB_USERNAME=…
MONITOREO_DB_PASSWORD=…
MONITOREO_ENCRYPTION_KEY=…         # ENCRYPTION_KEY del .env del panel Go (solo para importar)
MONITORING_ALERT_EMAIL=…           # avisos de dominio y reportes nuevos
DIGEST_EMAIL_TO=…                  # resumen de las 9 y las 18 (vacío = MONITORING_ALERT_EMAIL)
DISCORD_BOT_TOKEN=… DISCORD_GUILD_ID=… DISCORD_CATEGORY_ID=… DISCORD_SUMMARY_CHANNEL_ID=… DISCORD_CHANNEL_CATEGORIES=…
HEALTHCHECKS_URL=…
PAGESPEED_API_KEY=…
REVERB_APP_ID=… REVERB_APP_KEY=… REVERB_APP_SECRET=…   # y VITE_REVERB_* en el build del front
```

Los valores de Discord, Healthchecks y PageSpeed son los mismos que usa hoy el panel Go.

## 2. Convivencia (días antes del corte)

1. `php artisan migrate --force`
2. `php artisan monitoring:import --company=<id> --storage-dir=<STORAGE_DIR del panel Go>`
   - Imprime los usuarios del equipo que creó como invitados y los archivos de reportes que no encontró.
   - Revisar los proyectos importados: cada proyecto de monitoreo queda como proyecto nuevo de newDesk con su código derivado del slug.
3. **Solo si newDesk no corre en el Servidor 5**, en cada uno de los 6 servers:
   - sumar la IP de newDesk a `allowed_ips` en `/etc/monitor-agent/config.yaml`;
   - `ufw allow from <IP newDesk> to any port 9100 proto tcp`;
   - `systemctl restart monitor-agent`.
   El binario del agente no cambia.
4. `php artisan supervisor:sync` y recargar supervisor. Verificar `GET /api/health` con `poller: true`.
5. Comparar contra el panel Go durante al menos un día:
   - estado de los 6 servers y alertas abiertas;
   - gráficos de 24 h;
   - estado de 2 o 3 proyectos en el portal, con un usuario cliente de prueba.
6. Mientras conviven **no se editan servers, tokens ni huellas** en ninguno de los dos paneles. El agente acepta un solo token.
7. Con los avisos apagados igual funcionan las listas de canales de Discord y el botón "Probar" (lo aprieta alguien a propósito). No se crean canales nuevos.

## 3. Día del corte

1. Avisar al equipo. Los clientes no necesitan aviso: sus links redirigen solos (paso 3.5).
2. Apagar el panel Go en el Servidor 5:
   `systemctl stop monitor-panel.socket monitor-panel.service` y `systemctl disable monitor-panel.socket`.
   No se borran ni la base `monitoreo` ni el binario: se guardan 30 días para volver atrás.
3. Importación final, desde cero: `php artisan monitoring:import --company=<id> --storage-dir=… --fresh`.
   - Verificar los conteos por tabla contra la base vieja: servers, alertas, componentes, reportes, mensajes y adjuntos.
4. En newDesk:
   - `MONITORING_NOTIFY=true` (variable nueva, paso 1);
   - `php artisan config:cache`;
   - reiniciar los programas de supervisor de newDesk.
5. nginx de `monitoreo.nexosmart.com`: reemplazar el vhost por redirecciones. Hacer backup del vhost actual, `nginx -t` y después `systemctl reload nginx`.

   ```nginx
   location = /login  { return 301 https://<dominio newDesk>/portal/login; }
   location /portal/  { return 301 https://<dominio newDesk>$request_uri; }
   location /api/     { return 410; }
   location /         { return 301 https://<dominio newDesk>/monitoreo$request_uri; }
   ```

   Los slugs y los números de reporte se conservan, así que `/portal/<slug>` y `/reportes/<id>` caen en la misma página.
6. MCP: cada dev genera un token en newDesk (Monitoreo → Configuración → Tokens) y en Claude Code ejecuta:
   - `claude mcp remove monitoreo`;
   - el `claude mcp add` que muestra la pantalla de Tokens: `claude mcp add --transport http monitoreo https://<dominio newDesk>/mcp/monitoreo --header "Authorization: Bearer <token>"`.

## 4. Verificación después del corte

- `GET /api/health` → 200 con `poller: true`.
- El dashboard muestra los 6 servers en línea y el modo en vivo se mueve cada 5 s.
- Llega a Discord el mensaje de prueba de un proyecto (botón "Probar Discord").
- Un usuario cliente entra por código y ve su página de estado. Un reporte de prueba aparece en la cola del equipo y en Discord.
- Healthchecks.io recibe el heartbeat de newDesk.
- A las 9 o a las 18 llega el resumen.

## 5. Volver atrás (dentro de los 30 días)

1. En newDesk, `MONITORING_NOTIFY=false`, `config:cache` y reiniciar supervisor.
2. Restaurar el vhost de `monitoreo.nexosmart.com` desde el backup, `nginx -t` y reload.
3. `systemctl enable --now monitor-panel.socket`.

Lo que se cargó en newDesk después del corte (reportes, notas, cambios de componentes) no vuelve al panel Go. Hay que pasarlo a mano.
