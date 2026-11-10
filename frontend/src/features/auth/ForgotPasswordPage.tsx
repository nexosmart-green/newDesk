import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { AuthCard } from '@/components/ui/AuthCard'
import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { useRequestPasswordReset } from './useAuth'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const mutation = useRequestPasswordReset()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate(email)
  }

  return (
    <AuthCard title="Recuperar contraseña">
      {mutation.isSuccess ? (
        <SuccessNotice message={mutation.data ?? 'Revisá tu email.'} />
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4">
          <p className="text-sm text-muted">Te mandamos un enlace para crear una contraseña nueva.</p>
          <TextField
            label="Email"
            type="email"
            required
            autoComplete="email"
            value={email}
            error={fieldErrorOf(mutation.error, 'email')}
            onChange={(event) => setEmail(event.target.value)}
          />
          <ErrorAlert error={mutation.error} />
          <Button type="submit" loading={mutation.isPending} className="w-full">
            Enviar enlace
          </Button>
        </form>
      )}
      <p className="text-center text-sm">
        <Link to="/login" className="text-accent hover:underline">
          Volver a ingresar
        </Link>
      </p>
    </AuthCard>
  )
}
