import type { Project } from '../api/contract'

// Proyecto del inventario que contiene path: el mismo o el ancestro más cercano.
// El agente se queda en la carpeta con marcadores más cercana, que en un monorepo
// sin .git arriba es la subapp (playinn/back); el inventario lo lista como playinn.
export function inventoryProjectFor(path: string, projects: readonly Project[]): Project | undefined {
  let best: Project | undefined
  for (const project of projects) {
    const inside = path === project.path || path.startsWith(`${project.path}/`)
    if (inside && (!best || project.path.length > best.path.length)) best = project
  }
  return best
}

// Minúsculas y sin acentos: "canada" encuentra "Cañada".
export function searchable(text: string): string {
  return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

// Cada palabra de la búsqueda tiene que aparecer en alguno de los campos.
export function matchesSearch(query: string, fields: readonly (string | undefined)[]): boolean {
  const haystack = searchable(fields.filter(Boolean).join(' '))
  return searchable(query).split(/\s+/).filter(Boolean).every((word) => haystack.includes(word))
}

export function lastSegment(path: string): string {
  return path.split('/').filter(Boolean).pop() ?? path
}

// Tipos del agente que son back; la DB del proyecto vive en su mismo server (D-072).
const backTypes = new Set(['laravel', 'php', 'node-express', 'node'])
const frontTypes = new Set(['nextjs', 'react', 'react-vite', 'static'])

// En qué server está cada parte del proyecto, según sus carpetas vinculadas.
export function partServers(folders: readonly { server_name: string; types: readonly string[] }[]): { part: string; servers: string[] }[] {
  const on = (kinds: Set<string>) => [...new Set(folders.filter((folder) => folder.types.some((type) => kinds.has(type))).map((folder) => folder.server_name))]
  const back = on(backTypes)
  return [
    { part: 'Front', servers: on(frontTypes) },
    { part: 'Back', servers: back },
    { part: 'DB', servers: back },
  ].filter((row) => row.servers.length > 0)
}
