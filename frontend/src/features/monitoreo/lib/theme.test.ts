import { act, renderHook } from '@testing-library/react'
import { useColorScheme, usePortalTheme, usePortalThemeRoot } from './theme'

const html = document.documentElement

// Sistema simulado: claro.
beforeEach(() => vi.stubGlobal('matchMedia', (query: string) => ({ matches: false, media: query, addEventListener() {}, removeEventListener() {} })))
afterEach(() => {
  vi.unstubAllGlobals()
  html.classList.remove('light', 'dark')
  localStorage.clear()
})

const renderPortal = () =>
  renderHook(() => {
    usePortalThemeRoot()
    return [usePortalTheme(), useColorScheme()] as const
  })

describe('claro/oscuro del portal (RF-13, diferencia 3)', () => {
  it('arranca en automático, fija la preferencia y la libera', () => {
    const { result } = renderPortal()
    expect(result.current[0][0]).toBe('auto')
    expect(html.dataset.portalTheme).toBe('auto')
    expect(result.current[1]).toBe('light')

    act(() => result.current[0][1]('dark'))
    expect(html.dataset.portalTheme).toBe('dark')
    expect(localStorage.getItem('mon-theme-pref')).toBe('dark')
    expect(result.current[1]).toBe('dark')

    act(() => result.current[0][1]('auto'))
    expect(html.dataset.portalTheme).toBe('auto')
    expect(localStorage.getItem('mon-theme-pref')).toBeNull()
    expect(result.current[1]).toBe('light')
  })

  it('usa la preferencia guardada y no pisa el tema de newDesk: al salir del portal vuelve el del equipo', () => {
    localStorage.setItem('mon-theme-pref', 'light')
    html.classList.add('dark') // el botón de newDesk
    const { result, unmount } = renderPortal()
    expect(html.dataset.portalTheme).toBe('light')
    expect(result.current[1]).toBe('light')
    expect(html.classList.contains('dark')).toBe(true)

    unmount()
    expect(html.dataset.portalTheme).toBeUndefined()
    const team = renderHook(() => useColorScheme())
    expect(team.result.current).toBe('dark')
  })
})
