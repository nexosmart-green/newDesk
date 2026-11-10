import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import type { LoginInput } from './authApi'

interface LoginPasswordFormProps {
  onSubmit: (input: LoginInput) => void
  isPending: boolean
  error: unknown
}

export function LoginPasswordForm({ onSubmit, isPending, error }: LoginPasswordFormProps) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    onSubmit({ email, password, remember })
  }

  const credentialsError = fieldErrorOf(error, 'email') ?? fieldErrorOf(error, 'password')

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <TextField
        label="Email"
        type="email"
        required
        autoComplete="email"
        value={email}
        onChange={(event) => setEmail(event.target.value)}
      />
      <TextField
        label="Contraseña"
        type="password"
        required
        autoComplete="current-password"
        value={password}
        onChange={(event) => setPassword(event.target.value)}
      />
      <label className="flex items-center gap-2 text-sm text-muted">
        <input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} />
        Recordarme en este dispositivo
      </label>
      {credentialsError ? (
        <p role="alert" className="text-sm text-danger">
          {credentialsError}
        </p>
      ) : (
        <ErrorAlert error={error} />
      )}
      <Button type="submit" loading={isPending} className="w-full">
        Ingresar
      </Button>
      <p className="text-center text-sm">
        <Link to="/recuperar-contrasena" className="text-accent hover:underline">
          ¿Olvidaste tu contraseña?
        </Link>
      </p>
    </form>
  )
}
