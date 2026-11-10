import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import type { TwoFactorChallengeInput } from './types'

interface TwoFactorChallengeFormProps {
  onSubmit: (input: TwoFactorChallengeInput) => void
  onCancel: () => void
  isPending: boolean
  error: unknown
}

export function TwoFactorChallengeForm({ onSubmit, onCancel, isPending, error }: TwoFactorChallengeFormProps) {
  const [useRecoveryCode, setUseRecoveryCode] = useState(false)
  const [value, setValue] = useState('')

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const trimmed = value.trim()
    onSubmit(useRecoveryCode ? { recovery_code: trimmed } : { code: trimmed })
  }

  function toggleMode() {
    setUseRecoveryCode((current) => !current)
    setValue('')
  }

  const codeError = fieldErrorOf(error, 'code') ?? fieldErrorOf(error, 'recovery_code')

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <p className="text-sm text-muted">
        {useRecoveryCode
          ? 'Ingresá uno de los códigos de recuperación que guardaste al activar la verificación.'
          : 'Abrí tu app de autenticación e ingresá el código de 6 dígitos.'}
      </p>
      {useRecoveryCode ? (
        <TextField
          key="recovery"
          label="Código de recuperación"
          required
          autoComplete="off"
          value={value}
          error={codeError}
          onChange={(event) => setValue(event.target.value)}
        />
      ) : (
        <TextField
          key="totp"
          label="Código de verificación"
          required
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="[0-9]*"
          maxLength={6}
          value={value}
          error={codeError}
          onChange={(event) => setValue(event.target.value)}
        />
      )}
      <ErrorAlert error={error} />
      <Button type="submit" disabled={isPending} className="w-full">
        {isPending ? 'Verificando…' : 'Verificar'}
      </Button>
      <div className="flex flex-wrap justify-between gap-2 text-sm">
        <Button variant="link" onClick={toggleMode}>
          {useRecoveryCode ? 'Usar código de la app' : 'Usar código de recuperación'}
        </Button>
        <Button variant="link" onClick={onCancel}>
          Volver
        </Button>
      </div>
    </form>
  )
}
