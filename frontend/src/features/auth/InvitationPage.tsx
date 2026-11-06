import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { AuthCard } from '@/components/ui/AuthCard'
import { Button } from '@/components/ui/Button'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { ApiError } from '@/lib/api'
import { NewPasswordFields } from './NewPasswordFields'
import { emptyNewPassword, type InvitationSummary } from './types'
import { useAcceptInvitation, useInvitation } from './useAuth'

export function InvitationPage() {
  const { token = '' } = useParams()
  const invitation = useInvitation(token)

  if (invitation.isPending) {
    return (
      <AuthCard title="Invitación">
        <p className="text-muted">Cargando…</p>
      </AuthCard>
    )
  }

  if (invitation.isError) {
    const message =
      invitation.error instanceof ApiError && invitation.error.status === 404
        ? invitation.error.message
        : 'No pudimos cargar la invitación. Probá de nuevo en un rato.'
    return <InvalidInvitation message={message} />
  }

  return <AcceptInvitationForm token={token} invitation={invitation.data} />
}

function InvalidInvitation({ message }: { message: string }) {
  return (
    <AuthCard title="Invitación no válida">
      <p role="alert" className="text-sm text-danger">
        {message}
      </p>
      <Link to="/login" className="text-sm text-accent hover:underline">
        Ir a ingresar
      </Link>
    </AuthCard>
  )
}

function AcceptInvitationForm({ token, invitation }: { token: string; invitation: InvitationSummary }) {
  const [passwords, setPasswords] = useState(emptyNewPassword)
  const mutation = useAcceptInvitation(token)
  const navigate = useNavigate()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate(passwords, {
      onSuccess: (message) => navigate('/login', { replace: true, state: { notice: message } }),
    })
  }

  return (
    <AuthCard title={`Hola ${invitation.first_name}`}>
      <p className="text-sm text-muted">
        Te invitaron a <strong className="text-text">{invitation.company}</strong>. Elegí una contraseña para
        ingresar con <strong className="text-text">{invitation.email}</strong>.
      </p>
      <form onSubmit={handleSubmit} className="space-y-4">
        <NewPasswordFields value={passwords} onChange={setPasswords} error={mutation.error} />
        <ErrorAlert error={mutation.error} />
        <Button type="submit" disabled={mutation.isPending} className="w-full">
          {mutation.isPending ? 'Guardando…' : 'Crear cuenta'}
        </Button>
      </form>
    </AuthCard>
  )
}
