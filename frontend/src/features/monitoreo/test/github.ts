// La API de GitHub para "Generar reporte" (D-068), como monitoreo/web/src/mocks/github.ts, con fetch simulado.
// Token válido: GITHUB_MOCK_TOKEN. Hoy hay push en dos repos de maxirodr donde podés pushear (uno da 404 al
// listar ramas), uno donde solo sos lector y uno viejo. Devuelve las URLs pedidas.
export const GITHUB_MOCK_TOKEN = 'ghp_mock_solo_lectura'

const json = (body: unknown, status = 200, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } })
const commit = (sha: string, message: string, merge = false) => ({ sha, commit: { message }, parents: merge ? [{}, {}] : [{}] })

export function stubGitHub({ rateLimited = false } = {}): string[] {
  const asked: string[] = []
  vi.stubGlobal('fetch', async (input: string, init?: RequestInit) => {
    const url = new URL(input)
    asked.push(input)
    if (new Headers(init?.headers).get('Authorization') !== `Bearer ${GITHUB_MOCK_TOKEN}`) return json({ message: 'Bad credentials' }, 401)
    if (url.pathname === '/user') return json({ login: 'nexosmart-violet' })
    if (url.pathname === '/user/repos') {
      if (rateLimited) return json(null, 403, { 'x-ratelimit-remaining': '0' })
      const today = new Date().toISOString()
      return json([
        { full_name: 'maxirodr/tennisopenleague_backend', pushed_at: today, permissions: { push: true } },
        { full_name: 'maxirodr/tfp-api', pushed_at: today, permissions: { push: true } },
        { full_name: 'maxirodr/reaquila', pushed_at: today, permissions: { push: false } },
        { full_name: 'maxirodr/mlambiental', pushed_at: '2026-01-05T12:00:00Z', permissions: { push: true } },
      ])
    }
    if (url.pathname === '/repos/maxirodr/tfp-api/branches') return json({ message: 'Not Found' }, 404)
    if (url.pathname.endsWith('/branches')) return json([{ name: 'main' }, { name: 'develop' }])
    // Del más nuevo al más viejo, como GitHub. El "a1" está en las dos ramas.
    if (url.searchParams.get('sha') === 'main') return json([commit('m9', "Merge branch 'develop'", true), commit('a1', 'Agrega el ranking semanal')])
    return json([commit('b2', 'Arregla el cálculo de puntos\n\nEn los partidos con tie-break'), commit('a1', 'Agrega el ranking semanal')])
  })
  return asked
}
