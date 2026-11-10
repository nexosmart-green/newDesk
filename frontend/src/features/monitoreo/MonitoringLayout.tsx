import '@fontsource-variable/inter/wght.css'
import './skin.css'

import { useEffect, useRef, useState, type MouseEvent } from 'react'
import { Outlet } from 'react-router'
import { BackToDesk, Brand, ConnectionStatus, MobileBar, RoadmapToggle, SidebarNav } from './LayoutParts'
import { DESKTOP, useMediaQuery } from './lib/useMediaQuery'
import { useEvents } from './realtime/useEvents'
import { FloatingReportsProvider } from './reports/FloatingReports'
import { RoadmapPanel } from './reports/Roadmap'

const roadmapKey = 'mon-roadmap-open'

function readRoadmapOpen(): boolean {
  try {
    return localStorage.getItem(roadmapKey) !== '0'
  } catch {
    return true
  }
}

// El panel de monitoreo a pantalla completa, fuera del shell de newDesk: usuario, tema y salir quedan del lado de newDesk.
export function MonitoringLayout() {
  // Una sola conexión a Reverb para todo el módulo; el modo en vivo lo pide ServerPage.
  const connection = useEvents()
  const [menuOpen, setMenuOpen] = useState(false)
  const menuButton = useRef<HTMLButtonElement>(null)
  const sidebar = useRef<HTMLElement>(null)
  const desktop = useMediaQuery(DESKTOP)
  // Roadmap (D-064): en escritorio, una columna a la derecha que se recuerda abierta o cerrada; en celular, un cajón.
  const [roadmapOpen, setRoadmapOpen] = useState(readRoadmapOpen)
  const [roadmapDrawer, setRoadmapDrawer] = useState(false)
  const toggleRoadmap = (open: boolean) => {
    setRoadmapOpen(open)
    try {
      localStorage.setItem(roadmapKey, open ? '1' : '0')
    } catch {
      // sin storage: se recuerda hasta recargar
    }
  }
  const roadmapColumn = desktop && roadmapOpen

  useEffect(() => {
    if (!roadmapDrawer) return
    const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && setRoadmapDrawer(false)
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [roadmapDrawer])

  // Cajón móvil: foco adentro al abrir; Escape o pasar a escritorio lo cierran y el foco vuelve al botón.
  useEffect(() => {
    if (!menuOpen) return
    sidebar.current?.querySelector<HTMLElement>('a')?.focus()
    const close = () => setMenuOpen(false)
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') close()
    }
    const desktop = window.matchMedia(DESKTOP)
    document.addEventListener('keydown', onKeyDown)
    desktop.addEventListener('change', close)
    const button = menuButton.current
    return () => {
      document.removeEventListener('keydown', onKeyDown)
      desktop.removeEventListener('change', close)
      button?.focus()
    }
  }, [menuOpen])

  const skipToMain = (event: MouseEvent) => {
    event.preventDefault()
    document.getElementById('main')?.focus()
  }

  return (
    // Toda la pantalla: en Monitoreo no van el menú ni el header de newDesk (se vuelve con BackToDesk).
    <div data-skin="monitoreo" className={`min-h-dvh lg:grid ${roadmapColumn ? 'lg:grid-cols-[16rem_1fr_20rem]' : 'lg:grid-cols-[16rem_1fr]'}`}>
      {/* Adentro de la piel: las ventanas flotantes (fixed) usan sus tokens y sobreviven a la navegación. */}
      <FloatingReportsProvider>
        <a href="#main" onClick={skipToMain} className="btn btn-primary sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50">
          Saltar al contenido
        </a>
        <aside id="sidebar" ref={sidebar} className="drawer glass flex flex-col border-r border-line" data-open={menuOpen || undefined}>
          <Brand />
          <SidebarNav onNavigate={() => setMenuOpen(false)} />
          <div className="space-y-3 border-t border-line p-4 lg:sticky lg:bottom-0">
            <ConnectionStatus connection={connection} />
            <BackToDesk onNavigate={() => setMenuOpen(false)} />
          </div>
        </aside>
        <button type="button" aria-label="Cerrar menú" tabIndex={-1} className="scrim" data-open={menuOpen || undefined} onClick={() => setMenuOpen(false)} />

        <div className="min-w-0" inert={menuOpen || roadmapDrawer}>
          <MobileBar open={menuOpen} onMenu={() => setMenuOpen(true)} onRoadmap={() => setRoadmapDrawer(true)} buttonRef={menuButton} connection={connection} />
          {desktop && !roadmapOpen && <RoadmapToggle onClick={() => toggleRoadmap(true)} className="fixed right-4 bottom-4 z-20" />}
          <main id="main" tabIndex={-1} className="mx-auto w-full max-w-[1400px] px-5 pt-6 pb-16 outline-none sm:px-8 lg:px-10 lg:pt-10">
            <Outlet />
          </main>
        </div>

        {roadmapColumn && (
          <aside aria-label="Roadmap" className="glass sticky top-0 h-dvh border-l border-line">
            <RoadmapPanel onClose={() => toggleRoadmap(false)} />
          </aside>
        )}
        {!desktop && (
          <>
            <button type="button" aria-label="Cerrar el roadmap" tabIndex={-1} className="scrim z-[65]" data-open={roadmapDrawer || undefined} onClick={() => setRoadmapDrawer(false)} />
            {roadmapDrawer && (
              <aside aria-label="Roadmap" className="glass fade-in fixed inset-y-0 right-0 z-[70] w-[min(20rem,90vw)] border-l border-line shadow-[var(--shadow-pop)]">
                <RoadmapPanel onClose={() => setRoadmapDrawer(false)} />
              </aside>
            )}
          </>
        )}
      </FloatingReportsProvider>
    </div>
  )
}
