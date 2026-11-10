# Lessons — newDesk

(Se completa ante cada corrección del usuario: patrón + regla para no repetirlo.)

- 2026-11-06 — `php artisan migrate:fresh --env=testing` sin `.env.testing` cae en `.env` y **vacía la DB de desarrollo**. Para la DB de tests usar solo `php artisan test` (phpunit.xml fuerza `newdesk_testing`) o `DB_DATABASE=newdesk_testing php artisan ...` explícito.
- 2026-11-06 — Tests con procesos reales (suite `Concurrency`) commitean datos: truncar también en `afterEach`, o contaminan las suites transaccionales (duplicate entry en tests ajenos).
- 2026-11-06 — `Process::pool(...)->wait()` devuelve `ProcessPoolResults`: **`foreach` directo no itera nada** (recorre props públicas). Usar `->collect()`. Si no, los `expect($result->successful())` nunca corren y el test pasa en verde sin verificar los procesos.
- 2026-11-10 — Armé toda la UI sin cargar los skills de diseño y el usuario lo marcó con bronca. Regla: **antes de crear o rediseñar pantallas/layout, cargar `frontend-design:frontend-design` y `ui-ux-pro-max`** (lo pide el CLAUDE.md global: "landings o componentes → skills frontend*"), y tomar la paleta del sistema que se reemplaza (desk.nexosmart.es: teal `#309798`, rosa `#f6beb6`, sidebar `#171f29`). Revisar con screenshots antes de entregar.

## 2026-11-06 — Preguntas al usuario sin siglas

- Patrón: en una tanda de preguntas usé IDs internos (RF-02.01, CA, E2, slug, 301) y el usuario no entendió nada ("explica todo de nuevo").
- Regla: cuando le pregunto algo al usuario, primero digo en una frase qué problema hay y después las opciones en palabras comunes. Los IDs van en los docs, no en las preguntas.
- 2026-11-10 — Al unir ramas, mirar si una cambia el `TestCase` base: main congela el reloj (`freezeSecond`) y un `Cache::lock` con TTL que el test no suelta nunca vence → `block()` gira para siempre (suite colgada sin error). Regla: soltar siempre el lock que un test toma; ante una suite "lenta", correr archivo por archivo con `-d max_execution_time` para ver dónde se queda.
- 2026-11-10 — No editar el `.env` de un worktree para levantar una segunda instancia: los tests lo leen (stateful domains, DB) y fallan por eso. Pasar los valores como variables de entorno del proceso del servidor.
