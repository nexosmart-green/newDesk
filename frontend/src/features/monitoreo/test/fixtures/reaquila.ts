// Datos de ejemplo basados en el relevamiento de mail.reaquila.com (2026-09-30).
// Solo para desarrollo y tests: sin secretos, IPs de atacantes con rangos de documentación.
import type {
  Cron, Databases, Dependencies, Dependency, LargeFiles, Metrics, Packages, PM2, PM2Process, Ports, Projects, Security, Service, Services,
  Sites, Supervisor, System,
} from '../../api/contract'

const GiB = 1024 ** 3
const MiB = 1024 ** 2

export const metrics: Metrics = {
  cpu: { usage_percent: 11.4, cores: 4, load1: 0.3, load5: 0.33, load15: 0.42 },
  memory: { total_bytes: 3.7 * GiB, used_bytes: 2.5 * GiB, available_bytes: 1.2 * GiB, used_percent: 67.6 },
  swap: { total_bytes: 4 * GiB, used_bytes: 2.7 * GiB, used_percent: 67.5 },
  disks: [
    { mount: '/', device: '/dev/sda1', fs_type: 'ext4', total_bytes: 77 * GiB, used_bytes: 49 * GiB, free_bytes: 28 * GiB, used_percent: 65, inodes_used_percent: 21.4 },
    { mount: '/boot/efi', device: '/dev/sda15', fs_type: 'vfat', total_bytes: 105 * MiB, used_bytes: 6 * MiB, free_bytes: 99 * MiB, used_percent: 5.8, inodes_used_percent: 0 },
  ],
  network: [{ name: 'ens3', rx_bytes: 912 * GiB, tx_bytes: 1.4 * 1024 * GiB, rx_bytes_per_sec: 182_300, tx_bytes_per_sec: 421_900 }],
  uptime_seconds: 82 * 86_400 + 4 * 3600,
  boot_time: '2026-07-10T09:12:00Z',
  top_by_cpu: [
    { pid: 3040557, user: 'root', name: 'next-server', command: 'next-server (v16.1.6)', cpu_percent: 5.6, memory_bytes: 261 * MiB, memory_percent: 6.9 },
    { pid: 1697, user: 'mysql', name: 'mysqld', command: '/usr/sbin/mysqld', cpu_percent: 3.1, memory_bytes: 612 * MiB, memory_percent: 16.2 },
    { pid: 2211, user: 'www-data', name: 'php', command: 'php artisan horizon', cpu_percent: 2.2, memory_bytes: 88 * MiB, memory_percent: 2.3 },
    { pid: 961, user: 'root', name: 'PM2 v5.4.3: God', command: 'PM2 v5.4.3: God Daemon (/root/.pm2)', cpu_percent: 0.4, memory_bytes: 64 * MiB, memory_percent: 1.7 },
  ],
  top_by_memory: [
    { pid: 1697, user: 'mysql', name: 'mysqld', command: '/usr/sbin/mysqld', cpu_percent: 3.1, memory_bytes: 612 * MiB, memory_percent: 16.2 },
    { pid: 3040557, user: 'root', name: 'next-server', command: 'next-server (v16.1.6)', cpu_percent: 5.6, memory_bytes: 261 * MiB, memory_percent: 6.9 },
    { pid: 3040558, user: 'root', name: 'next-server', command: 'next-server (v16.1.6)', cpu_percent: 2.2, memory_bytes: 220 * MiB, memory_percent: 5.8 },
    { pid: 1180, user: 'redis', name: 'redis-server', command: '/usr/bin/redis-server 127.0.0.1:6379', cpu_percent: 0.3, memory_bytes: 96 * MiB, memory_percent: 2.5 },
  ],
}

export const system: System = {
  hostname: 'mail.reaquila.com',
  os: { id: 'ubuntu', name: 'Ubuntu', version: '24.10', codename: 'oracular', pretty_name: 'Ubuntu 24.10', eol: true, eol_date: '2025-07-10' },
  kernel: '6.11.0-29-generic', arch: 'amd64', virtualization: 'kvm',
  ips: [{ interface: 'ens3', address: '51.222.205.245/32', public: true }, { interface: 'lo', address: '127.0.0.1/8', public: false }],
  ssh_port: 1080, ssh_ports: [1080], timezone: 'Etc/UTC', ntp_synchronized: true, init: { name: 'systemd', version: '256' },
  cpu_model: 'AMD EPYC-Milan Processor', cpu_count: 4, memory_total_bytes: 3.7 * GiB,
}

const svc = (name: string, extra: Partial<Service> = {}): Service => ({
  name, active: 'active', sub_state: 'running', enabled: 'enabled', type: 'systemd', main_pid: 1000, custom: false, healthy: true, ...extra,
})
export const services: Services = {
  items: [
    svc('nginx'), svc('php8.3-fpm'),
    svc('mysql', { type: 'lsb', sub_state: 'exited', detail: 'mysqld vivo (PID 1697); systemd reporta active (exited)' }),
    svc('redis-server'), svc('supervisor', { type: 'sysv' }), svc('fail2ban'), svc('cron'), svc('ufw', { sub_state: 'exited' }),
    svc('postfix'), svc('ssh'), svc('earlyoom'),
    svc('pm2-root', { custom: true }), svc('pm2-nodeapp', { custom: true }),
    svc('apache2', { active: 'inactive', sub_state: 'dead', enabled: 'not-found', healthy: true, detail: 'no instalado' }),
  ],
}

const pm2proc = (pm_id: number, name: string, extra: Partial<PM2Process>): PM2Process => ({
  pm_id, name, status: 'online', restarts: 0, unstable_restarts: 0, memory_bytes: 0, cpu_percent: 0, exec_mode: 'fork_mode',
  instances: 1, cwd: '', node_version: '20.19.5', is_module: false, reload_command: `pm2 reload ${name}`, ...extra,
})
export const pm2: PM2 = {
  daemons: [
    {
      user: 'root', home: '/root/.pm2', pid: 961, alive: true,
      processes: [
        pm2proc(0, 'content-api', { restarts: 14, uptime_since: '2026-09-10T15:31:16Z', memory_bytes: 8 * MiB, cwd: '/var/www/reaquila.com/content-api', node_version: '22.22.3' }),
        pm2proc(3, 'landing-olimpo', { restarts: 4, uptime_since: '2026-08-20T16:33:48Z', memory_bytes: 34 * MiB, cpu_percent: 1.1, exec_mode: 'cluster_mode', cwd: '/var/www/clubolimpo.com.ar/landing-olimpo' }),
        pm2proc(4, 'landing-olimpo', { restarts: 4, uptime_since: '2026-08-20T16:33:48Z', memory_bytes: 38 * MiB, exec_mode: 'cluster_mode', cwd: '/var/www/clubolimpo.com.ar/landing-olimpo' }),
        pm2proc(5, 'farmaencuentro', { restarts: 33, uptime_since: '2026-09-23T20:45:30Z', memory_bytes: 261 * MiB, cpu_percent: 5.6, exec_mode: 'cluster_mode', instances: 2, cwd: '/var/www/web.tufarmapoint.com' }),
        pm2proc(6, 'farmaencuentro', { restarts: 33, uptime_since: '2026-09-23T20:45:31Z', memory_bytes: 220 * MiB, cpu_percent: 2.2, exec_mode: 'cluster_mode', instances: 2, cwd: '/var/www/web.tufarmapoint.com' }),
        pm2proc(7, 'pm2-logrotate', { restarts: 3, unstable_restarts: 1, uptime_since: '2026-07-30T14:44:35Z', memory_bytes: 38 * MiB, is_module: true, node_version: '22.22.3', cwd: '/root/.pm2/modules/pm2-logrotate' }),
      ],
    },
    { user: 'nodeapp', home: '/home/nodeapp/.pm2', pid: 960, alive: true, processes: [] },
  ],
}

// Proyecto por prefijo del nombre, como lo arma el agente desde directory= o desde el command.
const supervisorProject = (name: string) =>
  name.startsWith('tfp-api-') ? '/var/www/api2025.tufarmapoint.com/tfp-api'
    : name.startsWith('clubolimpo.') ? '/var/www/clubolimpo.com.ar/api'
      : name.startsWith('reaquila-') ? '/var/www/reaquila.com'
        : undefined
const prog = (name: string, uptime: number, extra: { state?: string; healthy?: boolean } = {}) => ({
  name, group: name.includes(':') ? name.split(':')[0] : undefined, state: extra.state ?? 'RUNNING', uptime_seconds: uptime, healthy: extra.healthy ?? true, autostart: true,
  project: supervisorProject(name),
})
export const supervisor: Supervisor = {
  available: true,
  programs: [
    prog('clubolimpo.laravel-queue-work:clubolimpo.laravel-queue-work_00', 18 * 86_400),
    prog('clubolimpo.laravel-queue-work-afip:clubolimpo.laravel-queue-work-afip_00', 285),
    prog('reaquila-queue-worker:reaquila-queue-worker_00', 1104),
    prog('reaquila-reverb:reaquila-reverb_00', 68 * 86_400),
    prog('reaquila-scheduler', 68 * 86_400),
    prog('reverb:reverb_00', 52 * 86_400),
    prog('supervisor-watchdog', 68 * 86_400),
    prog('tfp-api-admin-horizon', 6 * 86_400),
    prog('tfp-api-horizon', 6 * 86_400),
    prog('tfp-api-scheduler', 21 * 86_400),
    prog('tfp-api-worker:tfp-api-worker_00', 1712),
    prog('tfp-api-worker:tfp-api-worker_01', 1711),
    prog('tfp-api.laravel-echo-server:tfp-api.laravel-echo-server_00', 68 * 86_400),
    prog('tfp-scheduler', 25 * 86_400),
    prog('tfp_api_admin.laravel-queue-work:tfp_api_admin.laravel-queue-work_00', 1571),
  ],
}

export const cron: Cron = {
  entries: [
    { source: 'crontab:root', user: 'root', schedule: '* * * * *', command: 'php /var/www/api2025.tufarmapoint.com/tfp_api/api/artisan schedule:run' },
    { source: 'crontab:root', user: 'root', schedule: '0 8 * * *', command: '/var/www/security-scan.sh' },
    { source: 'crontab:root', user: 'root', schedule: '0 4,6,8 * * *', command: 'AUTOFIX_CONFIG=*** /opt/tfp-autofix/orchestrator.sh' },
    { source: 'crontab:www-data', user: 'www-data', schedule: '* * * * *', command: 'php /var/www/clubolimpo.com.ar/api/artisan schedule:run' },
    { source: '/etc/cron.hourly/disk-alert', schedule: '17 * * * *', command: '/usr/local/bin/disk-alert.sh' },
  ],
  timers: [
    { unit: 'health-alert.timer', service: 'health-alert.service', next: '2026-09-30T14:00:00Z', last: '2026-09-30T13:00:00Z', result: 'success', exit_status: 0, active: true, custom: true },
    { unit: 'db-backup.timer', service: 'db-backup.service', next: '2026-10-01T03:00:00Z', last: '2026-09-30T03:00:00Z', result: 'success', exit_status: 0, active: true, custom: true },
    { unit: 'apt-daily.timer', service: 'apt-daily.service', next: '2026-10-01T06:12:00Z', last: '2026-09-30T06:38:00Z', result: 'success', exit_status: 0, active: true, custom: false },
  ],
}

const git = (branch: string, commit: string, date: string, author: string, subject: string, repo: string) => ({
  branch, commit, commit_date: date, author, subject, remote_host: 'github.com', remote_repo: repo, remote_proto: 'https', dirty_files: 0,
})
export const projects: Projects = {
  items: [
    { path: '/var/www/api2025.tufarmapoint.com/tfp-api', name: 'tfp-api', type: 'laravel', framework_version: '12.62.0', markers: ['git', 'composer.json', 'artisan'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('main', 'a1aecbb', '2026-09-23T20:26:39Z', 'telayna-i', 'fix(comprobantes): un archivo ilegible deja de auto-rechazar el pago', 'nexosmart/tfp-api') },
    { path: '/var/www/clubolimpo.com.ar', name: 'clubolimpo.com.ar', type: 'monorepo', markers: ['git', 'package.json', 'composer.json'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('migracion', '3702a960', '2026-09-22T19:45:55Z', 'nexosmart-teamblue', 'feat(olimpo-app): route paid bonus redemption to MercadoPago', 'nexosmart/clubolimpo'), subapps: [{ path: 'api', type: 'laravel', framework_version: '12.62.0' }, { path: 'landing-olimpo', type: 'nextjs', framework_version: '16.3.4' }, { path: 'olimpo_app', type: 'react-vite' }] },
    { path: '/var/www/reaquila.com', name: 'reaquila.com', type: 'monorepo', markers: ['git', 'package.json', 'composer.json'], owner: 'www-data:www-data', mode: '2775', outside_web_root: false, git: { ...git('master', 'fa031f976', '2026-09-30T12:55:55Z', 'nexosmart-dev', 'feat(cotizaciones): publish Lanko Altas Montañas 2026/2027 quotation (#741)', 'nexosmart/reaquila'), remote_proto: 'ssh', dirty_files: 2 }, subapps: [{ path: 'apilaravel', type: 'laravel', framework_version: '12.69.1' }, { path: 'content-api', type: 'node-express-tsx' }, { path: 'crm', type: 'react-vite' }] },
    { path: '/var/www/web.tufarmapoint.com', name: 'web.tufarmapoint.com', type: 'nextjs', framework_version: '16.1.6', markers: ['git', 'package.json'], owner: 'root:root', mode: '755', outside_web_root: false, git: git('main', 'f768be2', '2026-09-24T08:00:46Z', 'root', 'update', 'nexosmart/tufarmapoint_web') },
    { path: '/var/www/nothinghere!', name: 'nothinghere!', type: 'phpmyadmin', framework_version: '5.2.2', markers: ['package.json', 'composer.json'], owner: 'root:root', mode: '755', outside_web_root: false },
    { path: '/opt/tfp-autofix/repos/tfp-api', name: 'tfp-api (autofix)', type: 'laravel', markers: ['git'], owner: 'fixer:fixer', mode: '755', outside_web_root: true, git: git('auto-fixes', '31c6e1f', '2026-09-29T08:00:00Z', 'fixer', 'auto-fix', 'nexosmart/tfp-api') },
  ],
  discarded: [
    { path: '/var/www/html', reason: 'página default de nginx, no habilitada' },
    { path: '/var/www/backups/tfe', reason: 'dumps SQL' },
  ],
}

export const sites: Sites = {
  web_server: { name: 'nginx', version: '1.26.0', config_ok: true },
  items: [
    { file: '/etc/nginx/sites-enabled/reaquila.com', server_names: ['www.reaquila.com'], redirects: ['reaquila.com'], listen: ['80'], root: '/var/www/reaquila.com', root_exists: true, fastcgi: 'unix:/run/php/php8.3-fpm.sock', proxies: [{ location: '/app', upstream: '127.0.0.1:8081', listening: true }], access_log: '/var/log/nginx/reaquila.com-access.log', projects: ['/var/www/reaquila.com'] },
    { file: '/etc/nginx/sites-enabled/web.farmaencuentro.com', server_names: ['www.farmaencuentro.com'], redirects: ['farmaencuentro.com'], listen: ['80'], root: '/var/www/web.tufarmapoint.com', root_exists: true, proxies: [{ location: '/', upstream: '127.0.0.1:3000', listening: true }], projects: ['/var/www/web.tufarmapoint.com'] },
    { file: '/etc/nginx/sites-enabled/clubolimpo.com.ar', server_names: ['www.clubolimpo.com.ar'], redirects: ['clubolimpo.com.ar', 'clubolimpo.org'], listen: ['80'], root: '/var/www/clubolimpo.com.ar', root_exists: true, proxies: [{ location: '/', upstream: '127.0.0.1:3006', listening: true }], projects: ['/var/www/clubolimpo.com.ar'] },
    { file: '/etc/nginx/sites-enabled/cms.farmaencuentro.com', server_names: ['cms.farmaencuentro.com'], listen: ['80', '[::]:80'], root: '/var/www/tufarmapoint-cms/dist', root_exists: false },
    { file: '/etc/nginx/sites-enabled/apitest2025.tufarmapoint.com', server_names: ['apitest2025.tufarmapoint.com'], listen: ['80'], root: '/var/www/apitest2025.tufarmapoint.com/public', root_exists: false },
  ],
}

const dep = (name: string, version?: string, path?: string, extra: Partial<Dependency> = {}): Dependency => ({
  name, installed: version !== undefined, version, path, broken: false, ...extra,
})
export const dependencies: Dependencies = {
  items: [
    dep('node', '20.19.5', '/usr/local/bin/node', { resolves_to: '/root/.nvm/versions/node/v20.19.5/bin/node', source: 'nvm-root', note: 'también v22.22.3 en nvm' }),
    dep('npm', '10.9.8', '/root/.nvm/versions/node/v22.22.3/bin/npm', { source: 'nvm-root' }),
    dep('pm2', '5.4.3', '/usr/local/bin/pm2', { source: 'npm-global' }),
    dep('php', '8.3.11', '/usr/bin/php', { source: 'apt' }),
    dep('composer', '2.7.7', '/usr/bin/composer'),
    dep('nginx', '1.26.0', '/usr/sbin/nginx', { source: 'apt' }),
    dep('mysql', '8.0.42', '/usr/sbin/mysqld', { source: 'apt' }),
    dep('redis', '7.0.15', '/usr/bin/redis-server', { source: 'apt' }),
    dep('supervisor', '4.2.5', '/usr/bin/supervisord'),
    dep('git', '2.45.2', '/usr/bin/git'),
    dep('yarn', undefined), dep('docker', undefined), dep('certbot', undefined),
  ],
}

export const packages: Packages = {
  manager: 'apt', upgradable: 1, security: 0, upgradable_list: ['gh'], reboot_required: false,
  unattended_upgrades: true, security_suite_configured: false,
}

export const databases: Databases = {
  items: [
    {
      engine: 'mysql', version: '8.0.42', listen: ['127.0.0.1:3306', '127.0.0.1:33060'], running: true,
      schemas: [
        { name: 'clubolimpo', size_bytes: 732 * MiB }, { name: 'tfp_db', size_bytes: 351 * MiB }, { name: 'ds_db', size_bytes: 287 * MiB },
        { name: 'reaquila', size_bytes: 267 * MiB }, { name: 'diferent', size_bytes: 149 * MiB }, { name: 'tfp_db_test', size_bytes: 47 * MiB },
      ],
      info: { uptime: '7084800', threads_connected: '14', slow_queries: '312' },
    },
    { engine: 'redis', version: '7.0.15', listen: ['127.0.0.1:6379'], running: true, info: { used_memory_human: '84.2M', connected_clients: '23' } },
  ],
}

export const ports: Ports = {
  items: [
    { proto: 'tcp', address: '0.0.0.0', port: 80, process: 'nginx', all_interfaces: true, user: 'root' },
    { proto: 'tcp', address: '0.0.0.0', port: 1080, process: 'sshd', all_interfaces: true, user: 'root' },
    { proto: 'tcp', address: '127.0.0.1', port: 3306, process: 'mysqld', all_interfaces: false, user: 'mysql' },
    { proto: 'tcp', address: '*', port: 3000, process: 'node', all_interfaces: true, user: 'root' },
    { proto: 'tcp', address: '*', port: 8443, process: 'laravel-echo-server', all_interfaces: true, user: 'root' },
    { proto: 'tcp', address: '0.0.0.0', port: 9100, process: 'monitor-agent', all_interfaces: true, user: 'monitor-agent' },
  ],
}

export const security: Security = {
  failed_logins: {
    source: 'journald',
    window_24h: { invalid_user: 369, failed_password: 0, preauth_closed: 0, banner_exchange: 65, phpmyadmin_denied: 1, max_auth_attempts: 0, accepted: 4 },
    window_7d: { invalid_user: 2496, failed_password: 0, preauth_closed: 166, banner_exchange: 348, phpmyadmin_denied: 1, max_auth_attempts: 0, accepted: 11 },
    distinct_ips_7d: 307,
    top_ips: [{ key: '203.0.113.68', count: 212 }, { key: '198.51.100.23', count: 187 }, { key: '192.0.2.150', count: 96 }],
    top_users: [{ key: 'admin', count: 402 }, { key: 'ubuntu', count: 233 }, { key: 'test', count: 120 }],
    recent: [
      { time: '2026-09-30T11:21:26Z', kind: 'invalid_user', service: 'sshd', user: 'admin', ip: '203.0.113.68' },
      { time: '2026-09-30T13:08:34Z', kind: 'phpmyadmin_denied', service: 'phpMyAdmin', user: 'demo', ip: '198.51.100.9', unverified: true },
      { time: '2026-09-26T21:15:34Z', kind: 'banner_exchange', service: 'sshd', ip: '192.0.2.150' },
    ],
    verified_24h: { invalid_user: 369, failed_password: 0, preauth_closed: 0, banner_exchange: 65, phpmyadmin_denied: 0, max_auth_attempts: 0, accepted: 4 },
  },
  fail2ban: { active: true, jails: [{ name: 'sshd', currently_failed: 0, total_failed: 10997, currently_banned: 24, total_banned: 789 }] },
  firewall: { tool: 'ufw', active: true, default_incoming: 'deny', rules: ['1080/tcp ALLOW Anywhere', '80/tcp ALLOW Anywhere', '443/tcp ALLOW Anywhere'] },
  findings: [
    { id: 'os-eol', severity: 'critical', title: 'Ubuntu 24.10 sin soporte (EOL 2025-07-10)', evidence: 'apt apunta a old-releases.ubuntu.com, sin suite -security', recommendation: 'Migrar a Ubuntu 24.04 LTS.' },
    { id: 'sudoers-wildcard:www-data', severity: 'critical', title: 'www-data puede ejecutar chown con comodín como root', evidence: 'www-data NOPASSWD: /usr/bin/chown www-data:www-data *', recommendation: 'Quitar la regla o limitarla a rutas fijas.' },
    { id: 'world-readable-secret:/root/.pm2/dump.pm2', severity: 'high', title: 'dump.pm2 legible por todos (contiene env)', evidence: '/root/.pm2/dump.pm2 modo 644', recommendation: 'chmod 600 /root/.pm2/dump.pm2' },
    { id: 'exposed-port:3000', severity: 'low', title: 'Puerto 3000 escucha en todas las interfaces', evidence: 'node *:3000 (bloqueado por UFW)', recommendation: 'Bindear a 127.0.0.1.' },
  ],
}

const MB = 1024 ** 2
export const largeFiles: LargeFiles = {
  threshold_bytes: 50 * MB,
  truncated: false,
  projects: [
    {
      project: '/var/www/reaquila.com', count: 2, total_bytes: 700 * MB + 59 * MB, scanned: 91_822, unreadable: 0, truncated: false,
      files: [
        { path: 'backup.zip', size_bytes: 700 * MB, modified_at: '2026-03-14T18:02:55Z' },
        { path: 'apilaravel/storage/app/public/cotizaciones/lanko-2026.pdf', size_bytes: 59 * MB, modified_at: '2026-09-30T12:40:10Z' },
      ],
    },
    {
      project: '/var/www/api2025.tufarmapoint.com/tfp-api', count: 1, total_bytes: 210 * MB, scanned: 38_004, unreadable: 0, truncated: false,
      files: [{ path: 'storage/logs/laravel-2026-10-06.log', size_bytes: 210 * MB, modified_at: '2026-10-06T23:59:58Z' }],
    },
  ],
}
