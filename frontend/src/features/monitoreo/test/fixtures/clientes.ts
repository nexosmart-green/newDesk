// Datos de ejemplo del relevamiento de vps-59ab9ce9 (server de clientes.nexosmart.com.ar, 2026-09-30).
// Solo desarrollo y tests: sin secretos; IPs de atacantes en rangos de documentación.
import type {
  Cron, Databases, Dependencies, LargeFiles, Metrics, Packages, PM2, PM2Process, Ports, Projects, Proxy, Security, Service, Services,
  Sites, Supervisor, SupervisorProgram, System,
} from '../../api/contract'

const GiB = 1024 ** 3
const MiB = 1024 ** 2
const W = '/var/www/clientes.nexosmart.com.ar'

export const metrics: Metrics = {
  cpu: { usage_percent: 3.2, cores: 4, load1: 0.11, load5: 0.12, load15: 0.12 },
  memory: { total_bytes: 7.6 * GiB, used_bytes: 3.5 * GiB, available_bytes: 4.1 * GiB, used_percent: 46.1 },
  swap: { total_bytes: 16 * GiB, used_bytes: 368 * MiB, used_percent: 2.2 },
  disks: [
    { mount: '/', device: '/dev/sda1', fs_type: 'ext4', total_bytes: 72 * GiB, used_bytes: 50 * GiB, free_bytes: 22 * GiB, used_percent: 70, inodes_used_percent: 18.2 },
    { mount: '/boot', device: '/dev/sda13', fs_type: 'ext4', total_bytes: 989 * MiB, used_bytes: 175 * MiB, free_bytes: 814 * MiB, used_percent: 19, inodes_used_percent: 1 },
  ],
  network: [{ name: 'ens3', rx_bytes: 310 * GiB, tx_bytes: 402 * GiB, rx_bytes_per_sec: 94_100, tx_bytes_per_sec: 211_800 }],
  uptime_seconds: 19 * 86_400 + 23 * 3600,
  boot_time: '2026-09-10T15:30:00Z',
  top_by_cpu: [
    { pid: 88121, user: 'root', name: 'node', command: 'next-server (TOL)', cpu_percent: 1.4, memory_bytes: 152 * MiB, memory_percent: 1.9 },
    { pid: 1502, user: 'mysql', name: 'mysqld', command: '/usr/sbin/mysqld', cpu_percent: 0.9, memory_bytes: 890 * MiB, memory_percent: 11.4 },
  ],
  top_by_memory: [
    { pid: 1502, user: 'mysql', name: 'mysqld', command: '/usr/sbin/mysqld', cpu_percent: 0.9, memory_bytes: 890 * MiB, memory_percent: 11.4 },
    { pid: 88121, user: 'root', name: 'node', command: 'next-server (TOL)', cpu_percent: 1.4, memory_bytes: 152 * MiB, memory_percent: 1.9 },
  ],
}

export const system: System = {
  hostname: 'vps-59ab9ce9',
  os: { id: 'ubuntu', name: 'Ubuntu', version: '26.04', codename: 'resolute', pretty_name: 'Ubuntu 26.04.1 LTS', eol: false, eol_date: '2031-05-31' },
  kernel: '7.0.0-28-generic', arch: 'amd64', virtualization: 'kvm',
  ips: [{ interface: 'ens3', address: '51.255.36.83/32', public: true }, { interface: 'ens3', address: '2001:41d0:305:2100::1:5caf/128', public: true }],
  ssh_port: 1080, ssh_ports: [22, 1080], timezone: 'Etc/UTC', ntp_synchronized: true, init: { name: 'systemd', version: '259' },
  cpu_model: 'AMD EPYC-Milan Processor', cpu_count: 4, memory_total_bytes: 7.6 * GiB,
}

const svc = (name: string, extra: Partial<Service> = {}): Service => ({
  name, active: 'active', sub_state: 'running', enabled: 'enabled', type: 'systemd', main_pid: 1000, custom: false, healthy: true, ...extra,
})
const pm2Unit = (user: string): Service =>
  svc(`pm2-${user}`, { active: 'inactive', sub_state: 'dead', custom: true, detail: 'unidad inactiva pero el daemon PM2 corre' })
export const services: Services = {
  items: [
    svc('nginx'), svc('php8.4-fpm', { custom: true }), svc('php8.5-fpm'), svc('mysql'), svc('redis-server'), svc('supervisor'),
    svc('fail2ban'), svc('cron'), svc('ufw', { sub_state: 'exited' }), svc('ssh'), pm2Unit('root'), pm2Unit('deploy'),
  ],
}

const pm2proc = (pm_id: number, name: string, cwd: string, extra: Partial<PM2Process> = {}): PM2Process => ({
  pm_id, name, status: 'online', restarts: 0, unstable_restarts: 0, uptime_since: '2026-09-24T15:00:00Z', memory_bytes: 90 * MiB,
  cpu_percent: 0.1, exec_mode: 'fork_mode', instances: 1, cwd, node_version: '24.21.0', is_module: false, reload_command: `pm2 reload ${name}`, ...extra,
})
export const pm2: PM2 = {
  daemons: [
    {
      user: 'root', home: '/root/.pm2', pid: 1210, alive: true,
      processes: [
        pm2proc(0, 'pm2-logrotate', '/root/.pm2/modules/pm2-logrotate', { is_module: true, restarts: 3 }),
        pm2proc(1, 'TOL', `${W}/tol_frontend_web`, { exec_mode: 'cluster_mode', instances: 2, restarts: 2, memory_bytes: 152 * MiB }),
        pm2proc(2, 'TOL', `${W}/tol_frontend_web`, { exec_mode: 'cluster_mode', instances: 2, restarts: 2, memory_bytes: 155 * MiB }),
        pm2proc(3, 'landing-olimpo', `${W}/landing-olimpo`, { exec_mode: 'cluster_mode', instances: 2 }),
        pm2proc(5, 'bankz-reverb', `${W}/bankz/api`, { memory_bytes: 36 * MiB }),
        pm2proc(6, 'playinn-web', `${W}/playinn/front/apps/web/.next/standalone/apps/web`, { memory_bytes: 117 * MiB }),
        pm2proc(7, 'sierra-landing', `${W}/agente_sierra/web`, { restarts: 1, memory_bytes: 82 * MiB }),
      ],
    },
    { user: 'deploy', home: '/home/deploy/.pm2', pid: 1211, alive: true, processes: [pm2proc(0, 'pm2-logrotate', '/home/deploy/.pm2/modules/pm2-logrotate', { is_module: true })] },
  ],
}

// Proyecto de cada programa, como lo arma el agente desde directory= o desde el command.
// playinn es un monorepo sin .git arriba: el agente se queda en la subapp (playinn/back).
const supervisorProjects: Record<string, { project: string; directory?: string }> = {
  'medplace.laravel-queue': { project: `${W}/medplace-backend`, directory: `${W}/medplace-backend` },
  'playinn-queue': { project: `${W}/playinn/back`, directory: `${W}/playinn/back` },
  'playinn-scheduler': { project: `${W}/playinn/back` },
  'teka-scheduler': { project: `${W}/tekatickets.com` },
  'tennisopenleague.scheduler': { project: `${W}/tennisopenleague_backend` },
  'universitario.laravel-echo-server': { project: `${W}/universitario` },
}
const running = (name: string, uptime: number): SupervisorProgram => ({
  name, group: name.includes(':') ? name.split(':')[0] : undefined, state: 'RUNNING', uptime_seconds: uptime, healthy: true, autostart: true,
  ...supervisorProjects[name.split(':')[0] ?? name],
})
const parked = (name: string): SupervisorProgram => ({
  name, group: name.split(':')[0], state: 'STOPPED', description: 'Not started', healthy: true, autostart: false,
  ...supervisorProjects[name.split(':')[0] ?? name],
})
export const supervisor: Supervisor = {
  available: true,
  programs: [
    running('calculate-day:calculate-day_00', 29_476), running('medplace.laravel-queue:medplace.laravel-queue_00', 642),
    running('playinn-queue:playinn-queue_00', 663), running('playinn-scheduler', 29_476), running('reverb:reverb_00', 29_476),
    running('sierra.reverb', 29_476), running('teka-scheduler', 29_476), running('tennisopenleague.scheduler', 29_476),
    parked('bhinfo-worker:bhinfo-worker_00'), parked('cobro.laravel-queue:cobro.laravel-queue_00'),
    parked('reaquila-reverb:reaquila-reverb_00'), parked('universitario.laravel-echo-server:universitario.laravel-echo-server_00'),
  ],
}

export const cron: Cron = {
  entries: [{ source: 'crontab:root', user: 'root', schedule: '0 7 * * *', command: 'curl https://clientes.nexosmart.com.ar/mlambiental/api2/expirations/check_expirations.php' }],
  timers: [
    { unit: 'backup-servidor.timer', service: 'backup-servidor.service', next: '2026-10-01T03:31:00Z', last: '2026-09-30T03:31:00Z', result: 'success', exit_status: 0, active: true, custom: true },
    { unit: 'cloudflare-ips-sync.timer', service: 'cloudflare-ips-sync.service', next: '2026-10-05T00:00:00Z', last: '2026-09-28T00:00:00Z', result: 'success', exit_status: 0, active: true, custom: true },
    { unit: 'apt-daily.timer', service: 'apt-daily.service', result: 'success', exit_status: 0, active: true, custom: false },
  ],
}

const git = (branch: string, commit: string, date: string, author: string, subject: string, repo: string, dirty: number) => ({
  branch, commit, commit_date: date, author, subject, remote_host: 'github.com', remote_repo: repo, remote_proto: 'https', dirty_files: dirty,
})
export const projects: Projects = {
  items: [
    { path: `${W}/tol_frontend_web`, name: 'tol_frontend_web', type: 'nextjs', framework_version: '^16.2.2', markers: ['git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('staging', '72f4739', '2026-09-29T13:49:50Z', 'nexosmart-green', 'feat(leagues): …', 'nexosmart/tol_frontend_web', 1) },
    { path: `${W}/tennisopenleague_backend`, name: 'tennisopenleague_backend', type: 'laravel', framework_version: '^12.0', markers: ['git', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('staging', '23101df', '2026-09-29T14:25:49Z', 'nexosmart-green', 'docs(tasks): …', 'nexosmart/tennisopenleague_backend', 1) },
    { path: `${W}/bankz`, name: 'bankz', type: 'monorepo', markers: ['git'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('main', '14abf1a', '2026-07-08T16:50:59Z', 'nexosmart-green', 'Merge: pase de diseño landing…', 'nexosmart-green/bankz', 7), subapps: [{ path: 'api', type: 'laravel' }, { path: 'web', type: 'react-vite' }, { path: 'landing', type: 'nextjs' }] },
    { path: `${W}/mlambiental`, name: 'mlambiental', type: 'monorepo', markers: ['git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('staging', '0aaff6a', '2026-01-21T14:48:13Z', 'nexosmart-teamblue', 'Update exportAudits.ts', 'nexosmart/mlambiental', 1643), subapps: [{ path: 'landings', type: 'nextjs' }, { path: 'cms-react', type: 'react-vite' }, { path: 'api', type: 'php' }] },
    { path: `${W}/playinn`, name: 'playinn', type: 'monorepo', markers: [], owner: 'root:root', mode: '755', outside_web_root: false, subapps: [{ path: 'back', type: 'laravel', framework_version: '12.62.0' }, { path: 'front', type: 'monorepo' }, { path: 'front/apps/web', type: 'nextjs', framework_version: '16.2.0' }] },
    { path: '/var/www/dev.reaquila.com', name: 'dev.reaquila.com', type: 'monorepo', markers: ['git', 'package.json', 'composer.json'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('staging', '9e05dae0', '2026-06-17T14:49:03Z', 'nexosmart-green', 'fix(security): esbuild override…', 'nexosmart/reaquila', 15), subapps: [{ path: 'apilaravel', type: 'laravel' }, { path: 'content-api', type: 'node' }, { path: 'marketplace', type: 'nextjs' }] },
    { path: '/var/www/error_templates', name: 'error_templates', type: 'static', markers: ['git'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('main', '2bc24bd', '2026-01-18T22:13:31Z', 'nexosmart-dev', 'css', 'nexosmart/error_templates', 0) },
    { path: `${W}/agente_sierra`, name: 'agente_sierra', type: 'monorepo', markers: ['.git'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/bhinfo`, name: 'bhinfo', type: 'nextjs', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/bhnews`, name: 'bhnews', type: 'nextjs', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/cambiogeneral_financiera`, name: 'cambiogeneral_financiera', type: 'monorepo', markers: ['.git'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/club-olimpo-api`, name: 'club-olimpo-api', type: 'laravel', markers: ['.git', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/cobroonline_backend`, name: 'cobroonline_backend', type: 'laravel', markers: ['.git', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/contador_digital`, name: 'contador_digital', type: 'monorepo', markers: ['.git'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/landing-olimpo`, name: 'landing-olimpo', type: 'nextjs', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/medplace-app`, name: 'medplace-app', type: 'laravel', markers: ['.git', 'package.json', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/medplace-backend`, name: 'medplace-backend', type: 'laravel', markers: ['.git', 'package.json', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/medplace-cms`, name: 'medplace-cms', type: 'react-vite', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/medplace-cms-staging`, name: 'medplace-cms-staging', type: 'react-vite', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/medplace-staging`, name: 'medplace-staging', type: 'laravel', markers: ['.git', 'package.json', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/olimpo_app`, name: 'olimpo_app', type: 'react-vite', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/optimuskg`, name: 'optimuskg', type: 'nextjs', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/tekatickets.com`, name: 'tekatickets.com', type: 'monorepo', markers: ['.git'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/tennisopenleague_cms`, name: 'tennisopenleague_cms', type: 'react-vite', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/tooki-backend`, name: 'tooki-backend', type: 'laravel', markers: ['.git', 'package.json', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/tooki-backend-staging`, name: 'tooki-backend-staging', type: 'laravel', markers: ['.git', 'package.json', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/tooki-cms`, name: 'tooki-cms', type: 'react-vite', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/tooki-web`, name: 'tooki-web', type: 'nextjs', markers: ['.git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: `${W}/universitario`, name: 'universitario', type: 'monorepo', markers: ['.git'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: '/var/www/nothinghere!', name: 'nothinghere!', type: 'php', markers: ['package.json', 'composer.json'], owner: 'www-data:www-data', mode: '755', outside_web_root: false },
  ],
  discarded: [
    { path: W, reason: 'contenedor: incluye proyectos, la carpeta en sí no es un proyecto' },
    { path: '/var/www/html', reason: 'sin marcadores de proyecto' },
  ],
}

const proxy = (location: string, upstream: string, listening: boolean): Proxy => ({ location, upstream, listening })
// El vhost real tiene ~150 locations: se completan con rutas de alias/PHP para probar el colapso y el filtro.
const filler = Array.from({ length: 40 }, (_, i) => proxy(`/cliente-${String(i + 1).padStart(2, '0')}/api/`, '127.0.0.1:8000', true))
export const sites: Sites = {
  web_server: { name: 'nginx', version: '1.28.3', config_ok: true },
  items: [
    {
      file: '/etc/nginx/sites-enabled/clientes.nexosmart.com.ar', server_names: ['clientes.nexosmart.com.ar'], listen: ['80'], root: W, root_exists: true,
      fastcgi: 'unix:/run/php/php8.4-fpm.sock', access_log: '/var/log/nginx/clientes.nexosmart.com.ar-access.log',
      proxies: [
        proxy('/tooki-web', '127.0.0.1:3000', false), proxy('/landing-olimpo', '127.0.0.1:3006', true), proxy('/tol_frontend_web/', '127.0.0.1:3005', true),
        proxy('/mlambiental', '127.0.0.1:3002', false), proxy('/agente_sierra/web', '127.0.0.1:3003', true), proxy('/app', '127.0.0.1:8080', true),
        proxy('/tekatickets/app/', '127.0.0.1:8081', false), proxy('/bankz/app', '127.0.0.1:8085', true), proxy('/playinn/', '127.0.0.1:3007', true),
        ...filler,
      ],
    },
    {
      file: '/etc/nginx/sites-enabled/dev.reaquila.com', server_names: ['dev.reaquila.com'], listen: ['80'], root: '/var/www/dev.reaquila.com', root_exists: true,
      fastcgi: 'unix:/run/php/php8.4-fpm.sock', proxies: [proxy('/reaquila', '127.0.0.1:3001', false), proxy('/app', '127.0.0.1:8081', false)],
    },
  ],
}

// Las 26 del relevamiento real de clientes (testdata/servers/clientes): alcanza para ver la paginación.
export const dependencies: Dependencies = {
  items: [
    { name: 'node', installed: true, version: '24.21.0', path: '/usr/local/bin/node', source: 'n', broken: false },
    { name: 'npm', installed: true, version: '12.0.2', path: '/usr/local/bin/npm', resolves_to: '/usr/local/lib/node_modules/npm/bin/npm-cli.js', source: 'npm-global', broken: false },
    { name: 'npx', installed: true, version: '12.0.2', path: '/usr/local/bin/npx', resolves_to: '/usr/local/lib/node_modules/npm/bin/npx-cli.js', source: 'npm-global', broken: false },
    { name: 'yarn', installed: false, broken: false },
    { name: 'pnpm', installed: false, broken: false },
    { name: 'pm2', installed: true, version: '7.0.4', path: '/usr/local/bin/pm2', resolves_to: '/usr/local/lib/node_modules/pm2/bin/pm2', source: 'npm-global', broken: false },
    { name: 'laravel-echo-server', installed: true, version: '1.6.3', path: '/usr/local/bin/laravel-echo-server', resolves_to: '/usr/local/lib/node_modules/laravel-echo-server/bin/server.js', source: 'npm-global', broken: false },
    { name: 'composer', installed: true, version: '2.9.5', path: '/usr/bin/composer', source: 'system', broken: false },
    { name: 'nginx', installed: true, version: '1.28.3', path: '/usr/sbin/nginx', source: 'system', broken: false },
    { name: 'apache', installed: false, broken: false },
    { name: 'mysql', installed: true, version: '8.4.11-0ubuntu0.26.04.1', path: '/usr/bin/mysql', source: 'system', broken: false },
    { name: 'psql', installed: false, broken: false },
    { name: 'redis', installed: true, version: '8.0.5', path: '/usr/bin/redis-server', source: 'system', broken: false },
    { name: 'supervisor', installed: true, version: '4.3.0', path: '/usr/bin/supervisord', source: 'system', broken: false },
    { name: 'git', installed: true, version: '2.53.0', path: '/usr/bin/git', source: 'system', broken: false },
    { name: 'python3', installed: true, version: '3.14.4', path: '/usr/bin/python3', resolves_to: '/usr/bin/python3.14', source: 'system', broken: false },
    { name: 'go', installed: false, broken: false },
    { name: 'docker', installed: false, broken: false },
    { name: 'certbot', installed: false, broken: false },
    { name: 'fail2ban', installed: true, version: '1.1.0', path: '/usr/bin/fail2ban-client', source: 'system', broken: false },
    { name: 'ufw', installed: true, version: '0.36.2', path: '/usr/sbin/ufw', source: 'system', broken: false },
    { name: 'jq', installed: true, version: '1.8.1', path: '/usr/bin/jq', source: 'system', broken: false },
    { name: 'php', installed: true, version: '8.4.25', path: '/usr/bin/php', resolves_to: '/opt/php8.4/bin/php', source: 'compiled', broken: false, note: 'versión por defecto del CLI (php)' },
    { name: 'php', installed: true, version: '8.5.4', path: '/usr/bin/php8.5', source: 'system', broken: false },
    { name: 'php-fpm', installed: true, version: '8.5.4', path: '/usr/sbin/php-fpm8.5', source: 'system', broken: false },
    { name: 'php-fpm', installed: true, version: '8.4.25', path: '/opt/php8.4/sbin/php-fpm', source: 'compiled', broken: false },
  ],
}

export const packages: Packages = {
  manager: 'apt', upgradable: 10, security: 0, upgradable_list: ['glycin-loaders', 'rust-coreutils', 'ubuntu-minimal'], reboot_required: true,
  unattended_upgrades: true, security_suite_configured: true,
}

export const databases: Databases = {
  items: [
    { engine: 'mysql', version: '8.4.11', listen: ['127.0.0.1:3306'], running: true, schemas: [{ name: 'medplace-staging', size_bytes: 134.8 * MiB }, { name: 'tol_backend', size_bytes: 90.8 * MiB }, { name: 'testcobro', size_bytes: 78.3 * MiB }] },
    { engine: 'redis', version: '8.0.5', listen: ['127.0.0.1:6379'], running: true, info: { connected_clients: '31' } },
  ],
}

export const ports: Ports = {
  items: [
    { proto: 'tcp', address: '0.0.0.0', port: 80, process: 'nginx', all_interfaces: true, user: 'root' },
    { proto: 'tcp', address: '0.0.0.0', port: 22, process: 'sshd', all_interfaces: true, user: 'root' },
    { proto: 'tcp', address: '0.0.0.0', port: 1080, process: 'sshd', all_interfaces: true, user: 'root' },
    { proto: 'tcp', address: '*', port: 3005, process: 'node', all_interfaces: true, user: 'root' },
    { proto: 'tcp', address: '127.0.0.1', port: 3306, process: 'mysqld', all_interfaces: false, user: 'mysql' },
  ],
}

export const security: Security = {
  failed_logins: {
    source: 'journald',
    window_24h: { invalid_user: 414, failed_password: 0, preauth_closed: 608, banner_exchange: 0, phpmyadmin_denied: 0, max_auth_attempts: 0, accepted: 1 },
    window_7d: { invalid_user: 3167, failed_password: 0, preauth_closed: 4623, banner_exchange: 0, phpmyadmin_denied: 0, max_auth_attempts: 12, accepted: 26 },
    distinct_ips_7d: 412,
    top_ips: [{ key: '203.0.113.149', count: 318 }, { key: '198.51.100.174', count: 204 }],
    top_users: [{ key: 'root', count: 4100 }, { key: 'admin', count: 377 }],
    recent: [
      { time: '2026-09-30T14:41:07Z', kind: 'invalid_user', service: 'sshd-session', user: 'k***', ip: '203.0.113.149' },
      { time: '2026-09-30T14:41:41Z', kind: 'preauth_closed', service: 'sshd-session', user: 'root', ip: '198.51.100.174' },
    ],
  },
  fail2ban: { active: true, jails: [{ name: 'sshd', currently_failed: 3, total_failed: 900, currently_banned: 42, total_banned: 60 }, { name: 'recidive', currently_failed: 9, total_failed: 9, currently_banned: 0, total_banned: 0 }] },
  firewall: { tool: 'ufw', active: true, default_incoming: 'deny', rules: ['80/tcp ALLOW Cloudflare', '1080/tcp ALLOW Anywhere', '22/tcp ALLOW Anywhere # SSH temporal'] },
  findings: [
    { id: 'reboot-required', severity: 'medium', title: 'Reinicio pendiente desde 2026-09-24', evidence: 'kernel 7.0.0-28 corriendo, 7.0.0-34 instalado; libc6 actualizada', recommendation: 'Programar un reinicio.' },
    { id: 'exposed-port:3005', severity: 'low', title: 'Puerto 3005 escucha en todas las interfaces', evidence: 'node *:3005 (bloqueado por UFW)', recommendation: 'Bindear a 127.0.0.1.' },
  ],
}

export const largeFiles: LargeFiles = {
  threshold_bytes: 50 * MiB,
  truncated: false,
  projects: [
    {
      // 22 archivos: 2 se quedan afuera del top 20 que manda el agente.
      project: `${W}/medplace-backend`, count: 22, total_bytes: 1.3 * GiB + 274 * MiB + 18 * 60 * MiB + 2 * 51 * MiB, scanned: 48_211, unreadable: 0, truncated: false,
      files: [
        { path: 'storage/logs/laravel.log', size_bytes: 1.3 * GiB, modified_at: '2026-10-06T23:58:12Z' },
        { path: 'storage/app/backups/medplace-2026-08-01.sql.gz', size_bytes: 274 * MiB, modified_at: '2026-08-01T03:00:41Z' },
        ...Array.from({ length: 18 }, (_, i) => ({ path: `storage/app/public/estudios/estudio-${String(i + 1).padStart(3, '0')}.dcm`, size_bytes: 60 * MiB, modified_at: '2026-09-20T10:00:00Z' })),
      ],
    },
    {
      project: `${W}/playinn`, count: 1, total_bytes: 90 * MiB, scanned: 15_342, unreadable: 0, truncated: false,
      files: [{ path: 'front/apps/web/public/videos/hero.mp4', size_bytes: 90 * MiB, modified_at: '2026-06-12T14:20:00Z' }],
    },
    { project: `${W}/tooki-backend`, count: 0, total_bytes: 0, scanned: 2_210, unreadable: 3, truncated: false, files: [] },
  ],
}
