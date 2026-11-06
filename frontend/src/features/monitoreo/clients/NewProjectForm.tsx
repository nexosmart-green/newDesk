import { useState } from 'react'
import { useNavigate } from 'react-router'
import { fieldError } from '../api/monitoringApi'
import { useDiscordChannels, useTeamUsers } from '../api/usePortalAdmin'
import { useClientMutations } from '../api/useProjectMutations'
import { ErrorNote, Spinner } from '../components/Panel'
import { CODE_PATTERN, CodeInput } from './CodeInput'
import { Select } from './ComponentForm'
import { DiscordChannelOptions } from './DiscordChannelOptions'

// Valor del select de Discord para que el bot cree el canal al dar de alta el proyecto.
const NEW_CHANNEL = '__new__'

export function NewProjectForm({ clientId }: { clientId: number }) {
  const navigate = useNavigate()
  const { createProject } = useClientMutations()
  const team = useTeamUsers().data ?? []
  const channels = useDiscordChannels()
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [leadId, setLeadId] = useState('')
  const [channel, setChannel] = useState('')
  const error = fieldError(createProject.error, 'name', 'code_prefix', 'lead_user_id', 'discord_channel_id')

  return (
    <>
      <form
        className="flex flex-wrap items-end gap-3 px-5 pb-4"
        onSubmit={(event) => {
          event.preventDefault()
          const createChannel = channel === NEW_CHANNEL
          createProject.mutate(
            {
              clientId, name: name.trim(), code_prefix: code, ...(leadId ? { lead_user_id: Number(leadId) } : {}),
              ...(createChannel ? { create_discord_channel: true } : channel ? { discord_channel_id: channel } : {}),
            },
            {
              // El aviso del alta (por ejemplo, que el bot no pudo crear el canal) se ve en la página del proyecto.
              onSuccess: ({ data: project, message }) =>
                navigate(`/monitoreo/clientes/${clientId}/proyectos/${project.id}`, { state: { notice: message, warning: createChannel && !project.discord_channel_id } }),
            },
          )
        }}
      >
        <label className="min-w-[220px] flex-1">
          <span className="label mb-1.5 block">Nuevo proyecto</span>
          <input className="input" value={name} onChange={(e) => setName(e.target.value)} placeholder="Sitio y API de Playinn" />
        </label>
        {/* newDesk: el código del proyecto (RF-03.01). */}
        <label className="w-36">
          <span className="label mb-1.5 block">Código</span>
          <CodeInput value={code} onChange={setCode} />
        </label>
        <label className="min-w-[200px]">
          <span className="label mb-1.5 block">Dev principal</span>
          <Select value={leadId} onChange={(e) => setLeadId(e.target.value)}>
            <option value="">Sin asignar</option>
            {team.map((user) => <option key={user.id} value={user.id}>{user.email}</option>)}
          </Select>
        </label>
        {/* Sin bot (o si no responde) no hay de dónde elegir: el proyecto se crea sin canal. */}
        {channels.data && (
          <label className="min-w-[200px]">
            <span className="label mb-1.5 block">Discord</span>
            <Select value={channel} onChange={(e) => setChannel(e.target.value)}>
              <option value="">Sin canal</option>
              <option value={NEW_CHANNEL}>Crear canal nuevo</option>
              <DiscordChannelOptions channels={channels.data.filter((each) => each.can_send)} />
            </Select>
          </label>
        )}
        <button type="submit" className="btn btn-primary h-10" disabled={!name.trim() || !CODE_PATTERN.test(code) || createProject.isPending}>
          {createProject.isPending && <Spinner />}
          Crear proyecto
        </button>
      </form>
      {error && <div className="px-5 pb-4"><ErrorNote>{error}</ErrorNote></div>}
    </>
  )
}
