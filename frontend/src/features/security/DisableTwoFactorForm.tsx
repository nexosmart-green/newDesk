import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { useDisableTwoFactor } from './useTwoFactor'

export function DisableTwoFactorForm() {
  const [password, setPassword] = useState('')
  const mutation = useDisableTwoFactor()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate(password)
  }

  return (
    <form onSubmit={handleSubmit} className="max-w-xs space-y-3">
      <p className="text-sm text-muted">Para desactivarla, confirmá tu contraseña.</p>
      <TextField
        label="Contraseña actual"
        type="password"
        required
        autoComplete="current-password"
        value={password}
        error={fieldErrorOf(mutation.error, 'password')}
        onChange={(event) => setPassword(event.target.value)}
      />
      <ErrorAlert error={mutation.error} />
      <Button type="submit" variant="danger" disabled={mutation.isPending}>
        {mutation.isPending ? 'Desactivando…' : 'Desactivar verificación en dos pasos'}
      </Button>
    </form>
  )
}
