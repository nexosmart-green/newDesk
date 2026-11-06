// Traducción manual de internal/contract/contract.go. Mismos nombres de campos JSON.
// Si cambia el contrato Go, se actualiza acá en el mismo cambio.

export type Section =
  | 'metrics' | 'system' | 'services' | 'pm2' | 'supervisor' | 'cron' | 'projects'
  | 'sites' | 'dependencies' | 'packages' | 'databases' | 'ports' | 'security' | 'large_files'

export interface CPUStats { usage_percent: number; cores: number; load1: number; load5: number; load15: number }
export interface MemStats { total_bytes: number; used_bytes: number; available_bytes: number; used_percent: number }
export interface SwapStats { total_bytes: number; used_bytes: number; used_percent: number }
export interface Disk {
  mount: string; device: string; fs_type: string; total_bytes: number; used_bytes: number
  free_bytes: number; used_percent: number; inodes_used_percent: number
}
export interface NetIface { name: string; rx_bytes: number; tx_bytes: number; rx_bytes_per_sec: number; tx_bytes_per_sec: number }
export interface Process {
  pid: number; user: string; name: string; command: string
  cpu_percent: number; memory_bytes: number; memory_percent: number
}
export interface Metrics {
  cpu: CPUStats; memory: MemStats; swap: SwapStats; disks: Disk[] | null; network: NetIface[] | null
  uptime_seconds: number; boot_time: string; top_by_cpu: Process[] | null; top_by_memory: Process[] | null
}

export interface OSInfo { id: string; name: string; version: string; codename: string; pretty_name: string; eol: boolean; eol_date?: string }
export interface IPAddress { interface: string; address: string; public: boolean }
export interface System {
  hostname: string; os: OSInfo; kernel: string; arch: string; virtualization: string
  // ssh_port es el preferido (el que identifica al server); ssh_ports, todos los que escucha sshd.
  ips: IPAddress[] | null; ssh_port: number; ssh_ports?: number[]; timezone: string; ntp_synchronized: boolean | null
  init: { name: string; version: string }; cpu_model: string; cpu_count: number; memory_total_bytes: number
}

export interface Service {
  name: string; active: string; sub_state: string; enabled: string; type: string; main_pid: number
  since?: string; custom: boolean; healthy: boolean; detail?: string
}
export interface Services { items: Service[] | null }

export interface PM2Process {
  pm_id: number; name: string; status: string; restarts: number; unstable_restarts: number
  uptime_since?: string; memory_bytes: number; cpu_percent: number; exec_mode: string; instances: number
  cwd: string; node_version: string; is_module: boolean; out_log?: string; err_log?: string; reload_command: string
}
export interface PM2Daemon { user: string; home: string; pid: number; alive: boolean; error?: string; processes: PM2Process[] | null }
export interface PM2 { daemons: PM2Daemon[] | null }

export interface SupervisorProgram {
  name: string; group?: string; state: string; pid?: number; uptime_seconds?: number; description?: string; healthy: boolean
  // autostart del .conf: STOPPED con autostart=false está detenido a propósito.
  autostart?: boolean
  // directory= del .conf y la carpeta del proyecto donde corre (los agentes viejos no los mandan).
  directory?: string; project?: string
}
export interface Supervisor { available: boolean; programs: SupervisorProgram[] | null }

export interface CronEntry { source: string; user?: string; schedule: string; command: string }
export interface Timer {
  unit: string; service: string; next?: string; last?: string; result?: string; exit_status: number; active: boolean; custom: boolean
}
export interface Cron { entries: CronEntry[] | null; timers: Timer[] | null }

export interface GitInfo {
  // deployed_at: cuándo se movió HEAD en ese checkout (pull, checkout), de .git/logs/HEAD; los agentes viejos no lo mandan.
  branch: string; commit: string; commit_date?: string; deployed_at?: string; author: string; subject: string
  remote_host?: string; remote_repo?: string; remote_proto?: string; dirty_files: number; error?: string
}
export interface Subapp { path: string; type: string; framework_version?: string }
export interface Project {
  path: string; name: string; type: string; framework_version?: string; markers: string[] | null
  owner: string; mode: string; outside_web_root: boolean; git?: GitInfo; subapps?: Subapp[]
}
export interface Projects { items: Project[] | null; discarded: { path: string; reason: string }[] | null }

// Archivos de threshold_bytes o más por proyecto (sin node_modules, vendor ni .git).
// Solo vienen los proyectos con archivos pesados o con la búsqueda incompleta.
export interface LargeFile { path: string; size_bytes: number; modified_at: string } // path relativo al proyecto
export interface ProjectLargeFiles {
  project: string; files: LargeFile[]; count: number; total_bytes: number; scanned: number; unreadable: number; truncated: boolean
}
export interface LargeFiles { threshold_bytes: number; truncated: boolean; projects: ProjectLargeFiles[] }

// listening: false = upstream local sin proceso escuchando (nginx responde 502). Ausente = remoto/desconocido.
export interface Proxy { location: string; upstream: string; listening?: boolean }
export interface Site {
  file: string; server_names: string[] | null; redirects?: string[]; listen: string[] | null; root?: string
  root_exists: boolean; fastcgi?: string; proxies?: Proxy[]; access_log?: string; error_log?: string
  ssl_certificate?: string; ssl_expires_at?: string; projects?: string[]; missing_paths?: string[]
}
export interface Sites { web_server: { name: string; version: string; config_ok?: boolean }; items: Site[] | null }

export interface Dependency {
  name: string; installed: boolean; version?: string; path?: string; resolves_to?: string
  source?: string; broken: boolean; note?: string
}
export interface Dependencies { items: Dependency[] | null }

export interface Packages {
  manager: string; upgradable: number; security: number; upgradable_list: string[] | null
  reboot_required: boolean; unattended_upgrades: boolean; security_suite_configured: boolean
}

export interface Schema { name: string; size_bytes: number }
export interface Database {
  engine: string; version?: string; listen?: string[]; running: boolean; schemas?: Schema[]
  info?: Record<string, string>; error?: string
}
export interface Databases { items: Database[] | null }

export interface Port { proto: string; address: string; port: number; process?: string; pid?: number; user?: string; all_interfaces: boolean }
export interface Ports { items: Port[] | null }

export interface AuthCounts {
  invalid_user: number; failed_password: number; preauth_closed: number
  banner_exchange: number; phpmyadmin_denied: number; max_auth_attempts: number; accepted: number
}
export interface Counter { key: string; count: number }
// unverified: lo pudo escribir cualquier usuario del server (phpMyAdmin, auth.log). NV-12.
export interface AuthEvent { time: string; kind: string; service: string; user?: string; ip?: string; unverified?: boolean }
export interface FailedLogins {
  source: string; window_24h: AuthCounts; window_7d: AuthCounts; distinct_ips_7d: number
  top_ips: Counter[] | null; top_users: Counter[] | null; recent: AuthEvent[] | null
  verified_24h?: AuthCounts // solo sshd de root en journald; falta en agentes viejos
}
export interface Jail {
  name: string; currently_failed: number; total_failed: number; currently_banned: number; total_banned: number; banned_ips?: string[]
}
export type Severity = 'critical' | 'high' | 'medium' | 'low' | 'info'
export interface Finding { id: string; severity: Severity; title: string; evidence: string; recommendation: string }
export interface Security {
  failed_logins: FailedLogins
  fail2ban?: { active: boolean; jails: Jail[] | null }
  firewall: { tool: string; active: boolean; default_incoming?: string; rules?: string[] }
  findings: Finding[] | null
}

// Resultado de security-scan.sh (npm audit / composer audit), sección vulnerabilities del agente (D-053).
// No está en Section: se ve por proyecto, no en la ficha del server.
export type VulnSeverity = 'critical' | 'high' | 'moderate' | 'low'
export interface VulnCounts { critical: number; high: number; moderate: number; low: number }
export interface VulnFinding { package: string; severity: VulnSeverity; title: string; cve?: string; url?: string; fix_available?: boolean }
export interface VulnDir {
  path: string; manager: 'npm' | 'composer'; report_only?: boolean
  error?: string // la auditoría no devolvió JSON: no es lo mismo que limpio
  counts: VulnCounts; findings: VulnFinding[]
}

export interface SectionData {
  metrics: Metrics; system: System; services: Services; pm2: PM2; supervisor: Supervisor; cron: Cron
  projects: Projects; sites: Sites; dependencies: Dependencies; packages: Packages
  databases: Databases; ports: Ports; security: Security; large_files: LargeFiles
}

export function failedTotal(c: AuthCounts): number {
  return c.invalid_user + c.failed_password + c.preauth_closed + c.banner_exchange + c.phpmyadmin_denied + (c.max_auth_attempts ?? 0)
}
