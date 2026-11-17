import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useLocation, useNavigate } from 'react-router'
import { currentUserKey } from '@/features/auth/useAuth'
import { BrandMark } from '../../components/BrandMark'
import { EmailCodeStep } from './EmailCodeStep'

// El cliente entra con mail y código (ADR-0002 punto 7): sin el paso del autenticador del equipo de monitoreo.
const stepLabels = ['Mail', 'Código']

export function PortalLoginPage() {
  const [codeSent, setCodeSent] = useState(false)
  const navigate = useNavigate()
  const location = useLocation()
  const client = useQueryClient()
  const from = (location.state as { from?: string } | null)?.from ?? '/portal'

  // Vuelve a donde estaba en el portal. reset y no invalidate: si la pestaña guardó el 401 de una sesión
  // vencida, el guard lo releería y volvería al login aunque la sesión nueva ya ande.
  const finish = async () => {
    await client.resetQueries({ queryKey: currentUserKey })
    navigate(from.startsWith('/portal') ? from : '/portal', { replace: true })
  }

  return (
    <main className="grid min-h-dvh place-items-center px-5 py-12">
      <div className="rise w-full max-w-[400px]">
        <div className="mb-8 flex flex-col items-center text-center">
          <BrandMark className="size-12 rounded-[14px]" iconSize={26} />
          <p className="mt-4 text-[1.0625rem] font-semibold tracking-tight">Monitoreo</p>
          <p className="text-sm text-muted">nexosmart</p>
        </div>
        <StepDots current={codeSent ? 1 : 0} />
        <div className="panel rounded-3xl p-6 sm:p-8">
          <div className="rise">
            <EmailCodeStep onCodeSent={setCodeSent} onVerified={() => void finish()} />
          </div>
        </div>
        <p className="mt-6 text-center text-xs text-faint">Acceso restringido. Los intentos quedan registrados.</p>
      </div>
    </main>
  )
}

// Puntos de progreso. El nombre del paso va como texto oculto (no aria-label: chocaría con los labels de los campos).
function StepDots({ current }: { current: number }) {
  return (
    <ol aria-label="Pasos del ingreso" className="mb-5 flex justify-center gap-1.5">
      {stepLabels.map((label, index) => (
        <li
          key={label}
          aria-current={index === current ? 'step' : undefined}
          className={`h-1.5 rounded-full transition-[width,background-color] duration-[var(--dur-3)] ease-[var(--ease-spring)] ${
            index === current ? 'w-5 bg-accent' : index < current ? 'w-1.5 bg-accent' : 'w-1.5 bg-surface-3'
          }`}
        >
          <span className="sr-only">{label}</span>
        </li>
      ))}
    </ol>
  )
}
