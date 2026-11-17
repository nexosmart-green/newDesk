// "Generar reporte" (D-068): tus commits de hoy, leídos desde el navegador con tu token de GitHub.
// El token solo vive dentro de esta función: no se guarda (ni en el navegador ni en el panel) y nunca
// se manda al servidor. Al panel solo le llegan los mensajes de los commits.

export interface RepoCommits { repo: string; commits: string[] }
export interface TodayCommits { login: string; repos: RepoCommits[]; skipped: string[] }

export class GitHubError extends Error {}

interface ApiRepo { full_name: string; pushed_at: string | null; permissions?: { push?: boolean } }
interface ApiCommit { sha: string; commit: { message: string }; parents: unknown[] }

const API = 'https://api.github.com'
const PAGE = 100
const CONCURRENT_BRANCHES = 4

// Repos con push hoy donde podés pushear (los tuyos como principal), y en cada uno tus commits de
// todas las ramas desde `since`, sin merges ni repetidos.
// ponytail: 5 páginas de repos y 100 ramas/commits por repo; alcanza para un día.
export async function readTodayCommits(token: string, since: Date, onProgress?: (done: number, total: number) => void): Promise<TodayCommits> {
  const get = async <T>(path: string): Promise<T> => {
    const response = await fetch(API + path, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/vnd.github+json', 'X-GitHub-Api-Version': '2022-11-28' },
    })
    if (response.status === 401) throw new GitHubError('GitHub rechazó el token: revisá que esté bien copiado y que no esté vencido.')
    if (response.status === 403 && response.headers.get('x-ratelimit-remaining') === '0') {
      throw new GitHubError('GitHub cortó por límite de pedidos. Probá de nuevo en unos minutos.')
    }
    if (!response.ok) throw new RepoError(response.status)
    return (await response.json()) as T
  }

  let login: string
  try {
    login = (await get<{ login: string }>('/user')).login
  } catch (error) {
    if (error instanceof RepoError) throw new GitHubError(`GitHub respondió ${error.status} al validar el token.`)
    throw error
  }

  // Ordenados por último push: apenas aparece uno de antes de hoy, el resto también lo es.
  const repos: string[] = []
  for (let page = 1; page <= 5; page++) {
    const batch = await get<ApiRepo[]>(`/user/repos?affiliation=owner,collaborator,organization_member&sort=pushed&direction=desc&per_page=${PAGE}&page=${page}`)
    for (const repo of batch) {
      if (repo.pushed_at && new Date(repo.pushed_at) >= since && repo.permissions?.push) repos.push(repo.full_name)
    }
    if (batch.length < PAGE || batch.some((repo) => !repo.pushed_at || new Date(repo.pushed_at) < since)) break
  }

  const out: TodayCommits = { login, repos: [], skipped: [] }
  const query = (branch: string) =>
    new URLSearchParams({ sha: branch, author: login, since: since.toISOString(), per_page: String(PAGE) }).toString()
  for (const [index, repo] of repos.entries()) {
    onProgress?.(index, repos.length)
    try {
      const branches = await get<{ name: string }[]>(`/repos/${repo}/branches?per_page=${PAGE}`)
      const seen = new Map<string, string>()
      for (let start = 0; start < branches.length; start += CONCURRENT_BRANCHES) {
        const lists = await Promise.all(
          branches.slice(start, start + CONCURRENT_BRANCHES).map((branch) => get<ApiCommit[]>(`/repos/${repo}/commits?${query(branch.name)}`)),
        )
        for (const commit of lists.flat()) {
          if (commit.parents.length <= 1) seen.set(commit.sha, commit.commit.message)
        }
      }
      if (seen.size > 0) out.repos.push({ repo, commits: [...seen.values()] })
    } catch (error) {
      if (error instanceof GitHubError) throw error
      out.skipped.push(`${repo} (GitHub respondió ${error instanceof RepoError ? error.status : 'con un error'})`)
    }
  }
  onProgress?.(repos.length, repos.length)
  return out
}

// Un repo puntual que falló (404, 409 de repo vacío…): se saltea y se sigue con el resto.
class RepoError extends Error {
  readonly status: number

  constructor(status: number) {
    super(`GitHub respondió ${status}`)
    this.status = status
  }
}

// El comienzo de hoy en la hora de tu compu.
export function startOfToday(now = new Date()): Date {
  const start = new Date(now)
  start.setHours(0, 0, 0, 0)
  return start
}
