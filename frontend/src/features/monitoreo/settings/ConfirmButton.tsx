import { useEffect, useState } from 'react'

// Confirmación en dos clics dentro de la página (sin window.confirm). Armado: la línea de .btn[data-armed]
// se vacía en 4 s, el mismo tiempo que el timeout de abajo.
export function ConfirmButton({ label, confirmLabel = '¿Seguro?', onConfirm, disabled }: { label: string; confirmLabel?: string; onConfirm: () => void; disabled?: boolean }) {
  const [armed, setArmed] = useState(false)

  useEffect(() => {
    if (!armed) return
    const timer = window.setTimeout(() => setArmed(false), 4000)
    return () => window.clearTimeout(timer)
  }, [armed])

  return (
    <button
      type="button"
      className="btn btn-sm btn-danger"
      data-armed={armed || undefined}
      disabled={disabled}
      onClick={() => {
        if (armed) {
          setArmed(false)
          onConfirm()
        } else {
          setArmed(true)
        }
      }}
    >
      {armed ? confirmLabel : label}
    </button>
  )
}
