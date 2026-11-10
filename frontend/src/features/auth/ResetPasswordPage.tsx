import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { AuthCard } from '@/components/ui/AuthCard'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { NewPasswordFields } from './NewPasswordFields'
import { emptyNewPassword } from './types'
import { useResetPassword } from './useAuth'

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') ?? ''
  const [email, setEmail] = useState(searchParams.get('email') ?? '')
  const [passwords, setPasswords] = useState(emptyNewPassword)
  const mutation = useResetPassword()
  const navigate = useNavigate()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate(
      { token, email, ...passwords },
      { onSuccess: (message) => navigate('/login', { replace: true, state: { notice: message } }) },
    )
  }

  if (!token) {
    return (
      <AuthCard title="Restablecer contraseña">
        <p role="alert" className="text-sm text-danger">
          El enlace está incompleto. Pedí uno nuevo.
        </p>
        <Link to="/recuperar-contrasena" className="text-sm text-accent hover:underline">
          Pedir otro enlace
        </Link>
      </AuthCard>
    )
  }

  return (
    <AuthCard title="Restablecer contraseña">
      <form onSubmit={handleSubmit} className="space-y-4">
        <TextField
          label="Email"
          type="email"
          required
          autoComplete="email"
          value={email}
          error={fieldErrorOf(mutation.error, 'email')}
          onChange={(event) => setEmail(event.target.value)}
        />
        <NewPasswordFields value={passwords} onChange={setPasswords} error={mutation.error} />
        <ErrorAlert error={mutation.error} />
        <Button type="submit" loading={mutation.isPending} className="w-full">
          Guardar contraseña
        </Button>
      </form>
    </AuthCard>
  )
}
