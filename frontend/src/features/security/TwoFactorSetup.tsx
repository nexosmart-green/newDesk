import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { QrCode } from './QrCode'
import { RecoveryCodes } from './RecoveryCodes'
import type { TwoFactorSetup as SetupData } from './types'
import { useConfirmTwoFactor, useRefreshCurrentUser, useStartTwoFactorSetup } from './useTwoFactor'

interface TwoFactorSetupProps {
  /** True while the recovery codes are on screen, so the parent keeps this mounted. */
  onActiveChange: (active: boolean) => void
}

export function TwoFactorSetup({ onActiveChange }: TwoFactorSetupProps) {
  const startMutation = useStartTwoFactorSetup()
  const confirmMutation = useConfirmTwoFactor()
  const refreshCurrentUser = useRefreshCurrentUser()

  function acknowledgeCodes() {
    onActiveChange(false)
    void refreshCurrentUser()
  }

  if (confirmMutation.data) {
    return <RecoveryCodes codes={confirmMutation.data} onAcknowledge={acknowledgeCodes} />
  }

  if (startMutation.data) {
    return (
      <ConfirmStep
        setup={startMutation.data}
        onConfirm={(code) => confirmMutation.mutate(code, { onSuccess: () => onActiveChange(true) })}
        isPending={confirmMutation.isPending}
        error={confirmMutation.error}
      />
    )
  }

  return (
    <div className="space-y-3">
      <p className="text-sm text-muted">
        Vas a necesitar una app de autenticación (Google Authenticator, 1Password, Authy…) en tu celular.
      </p>
      <ErrorAlert error={startMutation.error} />
      <Button onClick={() => startMutation.mutate()} disabled={startMutation.isPending}>
        {startMutation.isPending ? 'Preparando…' : 'Activar verificación en dos pasos'}
      </Button>
    </div>
  )
}

interface ConfirmStepProps {
  setup: SetupData
  onConfirm: (code: string) => void
  isPending: boolean
  error: unknown
}

function ConfirmStep({ setup, onConfirm, isPending, error }: ConfirmStepProps) {
  const [code, setCode] = useState('')

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    onConfirm(code.trim())
  }

  return (
    <div className="space-y-4">
      <ol className="list-decimal space-y-1 pl-5 text-sm text-muted">
        <li>Escaneá el código QR con tu app de autenticación.</li>
        <li>Ingresá el código de 6 dígitos que te muestra la app.</li>
      </ol>
      <QrCode value={setup.otpauth_url} label="Código QR para la app de autenticación" />
      <p className="text-sm">
        ¿No podés escanear? Cargá esta clave a mano:{' '}
        <code className="break-all rounded bg-bg px-1 font-mono" data-testid="two-factor-secret">
          {setup.secret}
        </code>
      </p>
      <form onSubmit={handleSubmit} className="max-w-xs space-y-3">
        <TextField
          label="Código de verificación"
          required
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={10}
          value={code}
          error={fieldErrorOf(error, 'code')}
          onChange={(event) => setCode(event.target.value)}
        />
        <ErrorAlert error={error} />
        <Button type="submit" disabled={isPending}>
          {isPending ? 'Verificando…' : 'Confirmar'}
        </Button>
      </form>
    </div>
  )
}
