import type { PortalPart } from '../api/types'

// Las partes del proyecto como las nombra el cliente (D-045), en el orden en que se muestran.
export const PORTAL_PARTS: readonly PortalPart[] = ['web', 'app', 'cms']

export const partLabels: Record<PortalPart, string> = { web: 'Web', app: 'App', cms: 'CMS' }

// "App", "App y CMS", "Web, App y CMS": siempre en el orden web, app, cms.
export function partsText(parts: readonly PortalPart[]): string {
  const labels = PORTAL_PARTS.filter((part) => parts.includes(part)).map((part) => partLabels[part])
  if (labels.length <= 1) return labels[0] ?? ''
  return `${labels.slice(0, -1).join(', ')} y ${labels.at(-1)}`
}

// La parte de una URL web por su host, como el back: cms/admin/panel → CMS, app → App, el resto → Web.
export function partFromUrl(url: string): PortalPart {
  const host = url.trim().replace(/^https?:\/\//i, '').split(/[/:?#]/)[0] ?? ''
  const first = (host.split('.')[0] ?? '').toLowerCase()
  if (/cms|admin|panel/.test(first)) return 'cms'
  if (first.startsWith('app') || first.endsWith('app') || first.includes('_app') || first.includes('-app')) return 'app'
  return 'web'
}
