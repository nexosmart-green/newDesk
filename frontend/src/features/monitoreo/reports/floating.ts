import { createContext, useContext } from 'react'

// Reportes en ventanas flotantes (D-064): quedan abiertas mientras se navega el panel.
// z: el orden de apilado. No se reordena el array al enfocar, así React no mueve el nodo
// en medio de un arrastre (perdería el pointer capture).
export interface FloatingWindow { id: number; x: number; y: number; minimized: boolean; z: number }

export const MAX_FLOATING = 4
export const FLOATING_KEY = 'mon-floating-reports'
export const WINDOW_WIDTH = 384 // 24rem, el lg:w-[24rem] de la ventana
export const ROADMAP_WIDTH = 320 // 20rem, la columna del roadmap en el Layout

export interface Viewport { width: number; height: number }

export interface FloatingApi {
  windows: FloatingWindow[]
  open: (id: number) => void
  close: (id: number) => void
  toggleMinimized: (id: number) => void
  focus: (id: number) => void
  move: (id: number, x: number, y: number) => void
}

export const FloatingContext = createContext<FloatingApi | null>(null)

export function useFloatingReports(): FloatingApi {
  const api = useContext(FloatingContext)
  if (!api) throw new Error('useFloatingReports necesita FloatingReportsProvider')
  return api
}

const topZ = (windows: FloatingWindow[]) => windows.reduce((z, each) => Math.max(z, each.z), 0)

// Siempre queda a la vista al menos la barra de título.
export function clamp(x: number, y: number, viewport: Viewport): { x: number; y: number } {
  return {
    x: Math.min(Math.max(8, x), Math.max(8, viewport.width - WINDOW_WIDTH - 8)),
    y: Math.min(Math.max(8, y), Math.max(8, viewport.height - 48)),
  }
}

// Abrir uno que ya está lo restaura y lo trae al frente. Uno nuevo se escalona abajo a la derecha;
// con MAX_FLOATING abiertas, se cierra la que quedó más al fondo.
export function openWindow(windows: FloatingWindow[], id: number, viewport: Viewport): FloatingWindow[] {
  const z = topZ(windows) + 1
  if (windows.some((each) => each.id === id)) {
    return windows.map((each) => (each.id === id ? { ...each, minimized: false, z } : each))
  }
  let kept = windows
  if (kept.length >= MAX_FLOATING) {
    const bottom = kept.reduce((lowest, each) => (each.z < lowest.z ? each : lowest))
    kept = kept.filter((each) => each !== bottom)
  }
  const step = kept.length * 28
  // En escritorio arranca a la izquierda de la columna del roadmap (20rem), para no taparlo.
  const roadmap = viewport.width >= 1024 ? ROADMAP_WIDTH : 0
  const position = clamp(viewport.width - roadmap - WINDOW_WIDTH - 24 - step, 72 + step, viewport)
  return [...kept, { id, ...position, minimized: false, z }]
}

export function focusWindow(windows: FloatingWindow[], id: number): FloatingWindow[] {
  const target = windows.find((each) => each.id === id)
  const z = topZ(windows)
  if (!target || target.z === z) return windows
  return windows.map((each) => (each.id === id ? { ...each, z: z + 1 } : each))
}

export function loadWindows(): FloatingWindow[] {
  try {
    const saved: unknown = JSON.parse(localStorage.getItem(FLOATING_KEY) ?? '[]')
    if (!Array.isArray(saved)) return []
    return saved
      .filter((each): each is FloatingWindow => Number.isInteger(each?.id) && typeof each.x === 'number' && typeof each.y === 'number')
      .slice(0, MAX_FLOATING)
      .map((each, index) => ({ id: each.id, x: each.x, y: each.y, minimized: Boolean(each.minimized), z: Number(each.z) || index + 1 }))
  } catch {
    return []
  }
}

export function saveWindows(windows: FloatingWindow[]) {
  try {
    if (windows.length === 0) localStorage.removeItem(FLOATING_KEY)
    else localStorage.setItem(FLOATING_KEY, JSON.stringify(windows))
  } catch {
    // Sin storage (modo privado): las ventanas viven hasta recargar.
  }
}
