import { useState } from 'react'
import { mustSetUpTwoFactor } from '@/features/auth/types'
import { useCurrentUser } from '@/features/auth/useAuth'
import { DisableTwoFactorForm } from './DisableTwoFactorForm'
import { TwoFactorSetup } from './TwoFactorSetup'

export function SecurityPage() {
  const { data: user } = useCurrentUser()
  // Keeps the setup (and its one-time recovery codes) mounted even if /me refetches meanwhile.
  const [setupInProgress, setSetupInProgress] = useState(false)
  if (!user) return null

  const { enabled, required } = user.two_factor
  const showSetup = !enabled || setupInProgress

  return (
    <section className="max-w-2xl space-y-4">
      <h1 className="text-lg font-semibold">Seguridad</h1>
      {mustSetUpTwoFactor(user) && (
        <p role="alert" className="rounded border border-warning/40 bg-warning/10 px-3 py-2 text-sm text-warning">
          Tu rol exige verificación en dos pasos. Activala para seguir usando newDesk.
        </p>
      )}
      <div className="space-y-4 rounded-lg border border-border bg-surface p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h2 className="font-medium">Verificación en dos pasos</h2>
          <span className={`text-sm ${enabled ? 'text-success' : 'text-muted'}`}>{enabled ? 'Activa' : 'Inactiva'}</span>
        </div>
        {showSetup && <TwoFactorSetup onActiveChange={setSetupInProgress} />}
        {!showSetup && required && (
          <p className="text-sm text-muted">Tu rol exige la verificación en dos pasos, así que no se puede desactivar.</p>
        )}
        {!showSetup && !required && <DisableTwoFactorForm />}
      </div>
    </section>
  )
}
