import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router'
import { ApiError } from '@/lib/api'
import { useCurrentUser, useLogin } from './useAuth'

export function LoginPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const loginMutation = useLogin()
  const { data: user } = useCurrentUser()
  const navigate = useNavigate()
  const location = useLocation()
  const redirectTo = (location.state as { from?: string } | null)?.from ?? '/'

  if (user) return <Navigate to={redirectTo} replace />

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    loginMutation.mutate({ email, password }, { onSuccess: () => navigate(redirectTo, { replace: true }) })
  }

  const error = loginMutation.error instanceof ApiError ? loginMutation.error : null

  return (
    <main className="flex min-h-screen items-center justify-center p-4">
      <form onSubmit={handleSubmit} className="w-full max-w-sm space-y-4 rounded-lg border border-border bg-surface p-6">
        <h1 className="text-xl font-semibold">Ingresar</h1>
        <label className="block space-y-1">
          <span className="text-sm text-muted">Email</span>
          <input
            type="email"
            required
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            className="w-full rounded border border-border bg-bg px-3 py-2"
          />
        </label>
        <label className="block space-y-1">
          <span className="text-sm text-muted">Contraseña</span>
          <input
            type="password"
            required
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            className="w-full rounded border border-border bg-bg px-3 py-2"
          />
        </label>
        {error && (
          <p role="alert" className="text-sm text-danger">
            {error.fieldErrors.email?.[0] ?? error.message}
          </p>
        )}
        <button
          type="submit"
          disabled={loginMutation.isPending}
          className="w-full rounded bg-accent px-3 py-2 font-medium text-white disabled:opacity-60"
        >
          {loginMutation.isPending ? 'Ingresando…' : 'Ingresar'}
        </button>
      </form>
    </main>
  )
}
