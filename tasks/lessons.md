# Lessons — newDesk

(Se completa ante cada corrección del usuario: patrón + regla para no repetirlo.)

- 2026-11-06 — `php artisan migrate:fresh --env=testing` sin `.env.testing` cae en `.env` y **vacía la DB de desarrollo**. Para la DB de tests usar solo `php artisan test` (phpunit.xml fuerza `newdesk_testing`) o `DB_DATABASE=newdesk_testing php artisan ...` explícito.
- 2026-11-06 — Tests con procesos reales (suite `Concurrency`) commitean datos: truncar también en `afterEach`, o contaminan las suites transaccionales (duplicate entry en tests ajenos).
