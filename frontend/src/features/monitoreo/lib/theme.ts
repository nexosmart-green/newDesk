import { useLayoutEffect, useState, useSyncExternalStore } from 'react'

// Dos claros/oscuros (RF-13, diferencia 3). Las pantallas del equipo siguen al botón de newDesk (useTheme pone .light o
// .dark en <html>). El portal del cliente mantiene el de monitoreo: Automático/Claro/Oscuro, que mientras está montado
// vive en <html data-portal-theme> (public/portal-theme.js lo pone antes del primer pintado; index.css y skin.css lo aplican).
export type ThemePref = 'auto' | 'light' | 'dark'

// La misma key que monitoreo y que lee public/portal-theme.js.
const storageKey = 'mon-theme-pref'
const root = () => document.documentElement
// jsdom no trae matchMedia.
const darkQuery = () => window.matchMedia?.('(prefers-color-scheme: dark)')

function storedPref(): ThemePref {
  try {
    const pref = localStorage.getItem(storageKey)
    return pref === 'light' || pref === 'dark' ? pref : 'auto'
  } catch {
    return 'auto'
  }
}

// La raíz del portal: su preferencia en <html> mientras está montado. Al salir (el equipo que entró por error) se va,
// y el resto de newDesk queda como estaba.
export function usePortalThemeRoot(): void {
  useLayoutEffect(() => {
    root().dataset.portalTheme = storedPref()
    return () => {
      delete root().dataset.portalTheme
    }
  }, [])
}

// El selector del portal. 'auto' sigue al sistema; 'light' y 'dark' lo fijan.
export function usePortalTheme(): [ThemePref, (pref: ThemePref) => void] {
  const [pref, setPref] = useState(storedPref)
  const choose = (next: ThemePref) => {
    const apply = () => {
      root().dataset.portalTheme = next
    }
    if ('startViewTransition' in document) document.startViewTransition(apply)
    else apply()
    try {
      if (next === 'auto') localStorage.removeItem(storageKey)
      else localStorage.setItem(storageKey, next)
    } catch {
      // modo privado: la preferencia dura hasta recargar
    }
    setPref(next)
  }
  return [pref, choose]
}

function subscribe(onChange: () => void) {
  const observer = new MutationObserver(onChange)
  observer.observe(root(), { attributes: true, attributeFilter: ['class', 'data-portal-theme'] })
  const query = darkQuery()
  query?.addEventListener('change', onChange)
  return () => {
    observer.disconnect()
    query?.removeEventListener('change', onChange)
  }
}

function currentScheme(): 'light' | 'dark' {
  const portal = root().dataset.portalTheme
  if (portal === 'light' || portal === 'dark') return portal
  if (portal === 'auto') return darkQuery()?.matches ? 'dark' : 'light'
  return root().classList.contains('light') ? 'light' : 'dark'
}

// Esquema efectivo (el del portal o el de newDesk). Lo usan los gráficos, que pintan en canvas.
export function useColorScheme(): 'light' | 'dark' {
  return useSyncExternalStore(subscribe, currentScheme)
}
