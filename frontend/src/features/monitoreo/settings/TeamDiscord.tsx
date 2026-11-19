import { useState } from 'react'
import { errorMessage, fieldError } from '../api/monitoringApi'
import { useDiscordMembers, useTeamUsers } from '../api/usePortalAdmin'
import { useSetDiscordAccount } from '../api/useProjectMutations'
import type { DiscordMember, User } from '../api/types'
import { Select } from '../clients/ComponentForm'
import { DataTable } from '../components/DataTable'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'

// Who the bot pings in each project's notices. Picked from the server's people; without a bot, pasted as an id.
export function TeamDiscord() {
  const team = useTeamUsers()
  const members = useDiscordMembers()

  if (team.isError) return <ErrorNote onRetry={() => void team.refetch()}>{errorMessage(team.error)}</ErrorNote>

  return (
    <Panel title={team.data ? `Cuentas de Discord · ${team.data.length}` : 'Cuentas de Discord'} bodyClassName="">
      <div className="space-y-1.5 px-5 pb-4 text-sm text-muted">
        <p>El bot etiqueta a cada persona en los avisos de los proyectos que lidera.</p>
        <p>
          Si la persona conectó su cuenta desde el Asistente, cambiarla acá le corta ese vínculo: lo vuelve a hacer con
          un código nuevo.
        </p>
        {members.isError && <p className="text-text">{errorMessage(members.error)} Pegá el ID numérico de cada uno.</p>}
      </div>
      <DataTable<User>
        noun="personas"
        rows={team.data ?? []}
        loading={team.isPending}
        rowKey={(row) => String(row.id)}
        empty="No hay personas activas en el equipo."
        columns={[
          {
            header: 'Persona',
            cell: (row) => (
              <span className="block min-w-0">
                <span className="block font-medium">{row.name || row.email}</span>
                {row.name && <span className="block text-xs text-muted">{row.email}</span>}
              </span>
            ),
          },
          { header: 'Cuenta de Discord', cell: (row) => <AccountCell user={row} members={members.isError ? null : members.data} /> },
          { header: 'Origen', cell: (row) => <OriginBadge user={row} /> },
        ]}
      />
    </Panel>
  )
}

function OriginBadge({ user }: { user: User }) {
  if (!user.discord_user_id) return <span className="text-xs text-muted">Sin cuenta</span>
  return user.discord_verified ? <Badge tone="ok">La conectó la persona</Badge> : <Badge tone="idle">Cargada a mano</Badge>
}

// members: the server's people; undefined while loading, null when there is no bot (then the id is pasted).
function AccountCell({ user, members }: { user: User; members: DiscordMember[] | null | undefined }) {
  const save = useSetDiscordAccount()
  const label = `Cuenta de Discord de ${user.name || user.email}`
  const error = save.isError ? fieldError(save.error, 'discord_user_id') : null
  const set = (discordUserId: string | null) => save.mutate({ userId: user.id, discordUserId })

  return (
    <div className="min-w-56 max-w-80">
      <div className="flex items-center gap-2">
        {members === null ? (
          <PastedId key={user.discord_user_id ?? ''} label={label} saved={user.discord_user_id} pending={save.isPending} onSave={set} />
        ) : (
          <Select aria-label={label} value={user.discord_user_id ?? ''} disabled={!members || save.isPending} onChange={(e) => set(e.target.value || null)}>
            <option value="">Sin cuenta</option>
            {user.discord_user_id && members && !members.some((member) => member.id === user.discord_user_id) && (
              <option value={user.discord_user_id}>ID {user.discord_user_id} (no está en el servidor)</option>
            )}
            {(members ?? []).map((member) => (
              <option key={member.id} value={member.id}>
                {member.display_name} (@{member.username})
              </option>
            ))}
          </Select>
        )}
        {save.isPending && <Spinner label="Guardando" />}
      </div>
      {error && (
        <p role="alert" className="fade-in mt-1.5 text-xs text-crit">
          {error}
        </p>
      )}
    </div>
  )
}

function PastedId({ label, saved, pending, onSave }: { label: string; saved: string | null; pending: boolean; onSave: (id: string | null) => void }) {
  const [value, setValue] = useState(saved ?? '')
  const trimmed = value.trim()
  return (
    <form
      className="flex w-full items-center gap-1.5"
      onSubmit={(e) => {
        e.preventDefault()
        onSave(trimmed || null)
      }}
    >
      <input aria-label={label} className="input font-mono text-xs" inputMode="numeric" placeholder="Sin cuenta" value={value} onChange={(e) => setValue(e.target.value)} />
      <button type="submit" className="btn btn-sm shrink-0" disabled={pending || trimmed === (saved ?? '')}>
        Guardar
      </button>
    </form>
  )
}
