import { useCurrentUser } from '@/features/auth/useAuth'

export function HomePage() {
  const { data: user } = useCurrentUser()
  return (
    <section>
      <h1 className="text-lg font-semibold">Hola, {user?.first_name}</h1>
      <p className="text-muted">Mis tareas llega con E3.</p>
    </section>
  )
}
