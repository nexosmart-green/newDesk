import { GITHUB_MOCK_TOKEN, stubGitHub } from '../test/github'
import { GitHubError, readTodayCommits, startOfToday } from './githubCommits'

afterEach(() => vi.unstubAllGlobals())

describe('commits de hoy desde GitHub (en el navegador)', () => {
  it('lee solo repos con push hoy donde podés pushear, tus commits de todas las ramas, sin merges ni repetidos', async () => {
    const asked = stubGitHub()
    const progress: string[] = []
    const result = await readTodayCommits(GITHUB_MOCK_TOKEN, startOfToday(), (done, total) => progress.push(`${done}/${total}`))

    expect(result.login).toBe('nexosmart-violet')
    expect(result.repos).toEqual([{ repo: 'maxirodr/tennisopenleague_backend', commits: ['Agrega el ranking semanal', 'Arregla el cálculo de puntos\n\nEn los partidos con tie-break'] }])
    expect(result.skipped).toEqual(['maxirodr/tfp-api (GitHub respondió 404)'])
    expect(progress.at(-1)).toBe('2/2')
    // Ni el repo donde solo es lector ni el viejo; siempre filtrando por autor y desde hoy.
    expect(asked.some((url) => url.includes('reaquila') || url.includes('mlambiental'))).toBe(false)
    for (const url of asked.filter((each) => each.includes('/commits?'))) {
      expect(url).toContain('author=nexosmart-violet')
      expect(url).toContain(`since=${encodeURIComponent(startOfToday().toISOString())}`)
    }
  })

  it('un token inválido corta con un mensaje claro', async () => {
    stubGitHub()
    await expect(readTodayCommits('ghp_cualquiera', startOfToday())).rejects.toThrow(GitHubError)
    await expect(readTodayCommits('ghp_cualquiera', startOfToday())).rejects.toThrow('GitHub rechazó el token')
  })

  it('avisa cuando GitHub corta por límite de pedidos', async () => {
    stubGitHub({ rateLimited: true })
    await expect(readTodayCommits(GITHUB_MOCK_TOKEN, startOfToday())).rejects.toThrow('límite de pedidos')
  })
})
