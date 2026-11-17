import { FLOATING_KEY, MAX_FLOATING, ROADMAP_WIDTH, WINDOW_WIDTH, clamp, focusWindow, loadWindows, openWindow, saveWindows } from './floating'

const viewport = { width: 1440, height: 900 }

describe('ventanas flotantes de reportes', () => {
  beforeEach(() => localStorage.clear())

  it('abre escalonadas, trae al frente la que ya estaba y respeta el máximo', () => {
    let windows = openWindow([], 4, viewport)
    windows = openWindow(windows, 1, viewport)
    expect(windows.map((each) => each.id)).toEqual([4, 1])
    const [first, second] = windows
    expect(second?.x).toBeLessThan(first?.x ?? 0)
    expect(first?.x).toBe(viewport.width - ROADMAP_WIDTH - WINDOW_WIDTH - 24) // al lado del roadmap, sin taparlo

    // Minimizada y reabierta: se restaura, no se duplica, y queda arriba.
    windows = windows.map((each) => (each.id === 4 ? { ...each, minimized: true } : each))
    windows = openWindow(windows, 4, viewport)
    expect(windows).toHaveLength(2)
    expect(windows.find((each) => each.id === 4)).toMatchObject({ minimized: false, z: 3 })

    for (const id of [7, 8, 9]) windows = openWindow(windows, id, viewport)
    expect(windows).toHaveLength(MAX_FLOATING)
    expect(windows.map((each) => each.id)).not.toContain(1) // la del fondo
  })

  it('enfocar sube solo la que no estaba arriba', () => {
    const windows = openWindow(openWindow([], 1, viewport), 2, viewport)
    expect(focusWindow(windows, 2)).toBe(windows)
    expect(focusWindow(windows, 1).find((each) => each.id === 1)?.z).toBe(3)
  })

  it('nunca deja la barra de título fuera de la pantalla', () => {
    expect(clamp(-200, -50, viewport)).toEqual({ x: 8, y: 8 })
    expect(clamp(5000, 5000, viewport)).toEqual({ x: viewport.width - WINDOW_WIDTH - 8, y: viewport.height - 48 })
  })

  it('se guardan y vuelven, y un valor roto no rompe nada', () => {
    const windows = openWindow([], 3, viewport)
    saveWindows(windows)
    expect(loadWindows()).toEqual(windows)
    saveWindows([])
    expect(localStorage.getItem(FLOATING_KEY)).toBeNull()
    localStorage.setItem(FLOATING_KEY, '{roto')
    expect(loadWindows()).toEqual([])
    localStorage.setItem(FLOATING_KEY, JSON.stringify([{ id: 'x' }, { id: 5, x: 10, y: 20 }]))
    expect(loadWindows()).toEqual([{ id: 5, x: 10, y: 20, minimized: false, z: 1 }])
  })
})
