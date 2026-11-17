import { useState } from 'react'
import { Link } from 'react-router'
import { ApiError } from '@/lib/api'
import { errorMessage } from '../api/monitoringApi'
import { useDiscordChannels } from '../api/usePortalAdmin'
import { useProjectMutations } from '../api/useProjectMutations'
import type { DevelopersInput, ProjectAdmin, ProjectInput } from '../api/types'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { formatRelative } from '../lib/format'
import { Field, Select } from './ComponentForm'
import { DevelopersFields, DevelopersSummary } from './Developers'
import { DiscordChannelOptions } from './DiscordChannelOptions'

// Discord select value that asks the bot to create the project's channel on save.
const NEW_CHANNEL = '__new__'

export function ProjectForm({ project, readOnly }: { project: ProjectAdmin; readOnly: boolean }) {
  const { update, testDiscord } = useProjectMutations(project.id)
  const channels = useDiscordChannels()
  const [values, setValues] = useState<ProjectInput>({
    slug: project.slug, primary_color: project.primary_color, domain: project.domain,
    domain_expires_manual: project.domain_expires_manual, active: project.active, errors_url: project.errors_url,
    discord_channel_id: project.discord_channel_id,
  })
  const [developers, setDevelopers] = useState<DevelopersInput>(() => ({
    lead_user_id: project.developers.find((developer) => developer.role === 'lead')?.user_id ?? null,
    secondary_user_ids: project.developers.filter((developer) => developer.role === 'secondary').map((developer) => developer.user_id),
  }))
  // El token nunca vuelve del back: vacío = no se toca el guardado.
  const [errorsToken, setErrorsToken] = useState('')
  const set = (patch: Partial<ProjectInput>) => setValues((current) => ({ ...current, ...patch }))
  const apiError = update.error instanceof ApiError ? update.error : null
  const canTestDiscord = !readOnly && Boolean(project.discord_channel_id)
  // Si el bot ya no ve el canal guardado (o no responde), igual queda elegido: guardar no lo borra.
  const savedChannelMissing = Boolean(project.discord_channel_id) && !channels.data?.some((channel) => channel.id === project.discord_channel_id)
  // The project is saved even when the bot could not create the channel: the envelope message says why.
  const channelWarning = update.data && update.variables?.create_discord_channel && !update.data.data.discord_channel_id ? update.data.message : null

  // El PUT reemplaza todo el proyecto: se manda el formulario completo.
  const save = () => {
    const { discord_channel_id: channel, ...rest } = values
    const createChannel = channel === NEW_CHANNEL
    update.mutate(
      {
        ...rest, slug: rest.slug.trim(), domain: rest.domain?.trim() || null,
        errors_url: rest.errors_url?.trim() || null, ...(errorsToken.trim() ? { errors_token: errorsToken.trim() } : {}),
        // Without discord_channel_id the saved one is kept until the bot picks the channel it creates.
        ...(createChannel ? { create_discord_channel: true } : { discord_channel_id: channel || null }),
        ...(readOnly ? {} : { developers }),
      },
      {
        onSuccess: ({ data }) => {
          setErrorsToken('')
          testDiscord.reset()
          if (createChannel) set({ discord_channel_id: data.discord_channel_id })
        },
      },
    )
  }

  return (
    <Panel title="Datos del proyecto">
      <form
        className="grid gap-4 sm:grid-cols-2"
        noValidate
        onSubmit={(event) => {
          event.preventDefault()
          save()
        }}
      >
        {/* Name and code (RF-03.01) belong to the newDesk project: shown here, edited in Proyectos. */}
        <p className="flex flex-wrap items-baseline gap-x-4 gap-y-1 text-sm sm:col-span-2">
          <span><span className="text-muted">Nombre:</span> {project.name}</span>
          <span><span className="text-muted">Código:</span> <span className="font-mono text-xs">{project.code_prefix}</span></span>
          <Link to={`/proyectos/${project.id}`} className="link">Editar nombre y código en Proyectos</Link>
        </p>
        <fieldset disabled={readOnly} className="contents">
          <Field label="Dirección en el portal" error={apiError?.field('slug')}>
            <span className="flex items-center gap-1.5">
              <span className="font-mono text-xs text-muted">/portal/</span>
              <input className="input font-mono text-xs" value={values.slug} onChange={(e) => set({ slug: e.target.value.toLowerCase() })} />
            </span>
          </Field>
          <Field label="Color de la marca" error={apiError?.field('primary_color')}>
            <span className="flex items-center gap-2">
              <input
                type="color"
                aria-label="Elegir color"
                className="h-10 w-12 cursor-pointer rounded-[10px] border border-line bg-surface p-1"
                value={values.primary_color ?? '#1d1d1f'}
                onChange={(e) => set({ primary_color: e.target.value })}
              />
              <input className="input font-mono text-xs" value={values.primary_color ?? ''} placeholder="sin color (usa el del panel)" onChange={(e) => set({ primary_color: e.target.value.trim() || null })} />
            </span>
          </Field>
          <Field label="Dominio (para avisar el vencimiento)" error={apiError?.field('domain')}>
            <input className="input" value={values.domain ?? ''} placeholder="playinn.com" onChange={(e) => set({ domain: e.target.value || null })} />
          </Field>
          <Field label="Vencimiento del dominio (si el WHOIS no lo da)" error={apiError?.field('domain_expires_manual')}>
            <input className="input num" type="date" value={values.domain_expires_manual ?? ''} onChange={(e) => set({ domain_expires_manual: e.target.value || null })} />
          </Field>
          <label className="flex items-center gap-2 self-end pb-2.5 text-sm">
            <input type="checkbox" className="size-4" checked={values.active} onChange={(e) => set({ active: e.target.checked })} />
            Visible para el cliente
          </label>
          <Field label="URL del /api/errors del proyecto" error={apiError?.field('errors_url')}>
            <input className="input" type="url" autoComplete="off" value={values.errors_url ?? ''} placeholder="https://api.playinn.com/api/errors" onChange={(e) => set({ errors_url: e.target.value || null })} />
          </Field>
          <Field label="Token del /api/errors" error={apiError?.field('errors_token')}>
            <input
              className="input"
              type="password"
              autoComplete="new-password" // "off" is ignored on password fields: Chrome filled a saved login here
              value={errorsToken}
              placeholder={project.has_errors_token ? '•••• guardado (vacío = no cambia)' : 'Token del bug-report'}
              onChange={(e) => setErrorsToken(e.target.value)}
            />
          </Field>
          <p className="-mt-2 text-xs text-muted sm:col-span-2">
            Opcional. Con esto, cada reporte del cliente llega con los errores recientes del proyecto. Sin URL se borra también el token.
          </p>
          {/* La nota va fuera del <label>: si no, cambia el nombre accesible del select. */}
          <div>
            <Field label="Canal de Discord" error={apiError?.field('discord_channel_id')}>
              <Select value={values.discord_channel_id ?? ''} onChange={(e) => set({ discord_channel_id: e.target.value || null })}>
                <option value="">Sin canal</option>
                {/* Sin bot (o si no responde) no hay quién lo cree. */}
                {channels.data && <option value={NEW_CHANNEL}>Crear canal nuevo</option>}
                {savedChannelMissing && <option value={project.discord_channel_id ?? ''}>Canal {project.discord_channel_id}</option>}
                <DiscordChannelOptions channels={channels.data ?? []} />
              </Select>
            </Field>
            {channels.isError && <p className="mt-1.5 text-xs text-muted">{errorMessage(channels.error)}</p>}
          </div>
          {canTestDiscord && (
            <div className="flex flex-wrap items-center gap-2 self-end pb-1.5">
              <button type="button" className="btn btn-sm" disabled={testDiscord.isPending} onClick={() => testDiscord.mutate()}>
                {testDiscord.isPending && <Spinner />}
                Probar
              </button>
              {testDiscord.isSuccess && <span role="status" className="fade-in text-sm text-ok">{testDiscord.data || 'Mensaje de prueba enviado.'}</span>}
            </div>
          )}
          <p className="-mt-2 text-xs text-muted sm:col-span-2">
            Ahí llegan los avisos del proyecto (caídas, vulnerabilidades, reportes y errores) y se etiqueta al dev principal. Un canal sin acceso necesita que le den permiso al rol Monitoreo en Discord.
          </p>
          {canTestDiscord && testDiscord.isError && <div className="sm:col-span-2"><ErrorNote>{errorMessage(testDiscord.error)}</ErrorNote></div>}
          {channelWarning && <div className="sm:col-span-2"><ErrorNote>{channelWarning}</ErrorNote></div>}
          {readOnly ? (
            <DevelopersSummary developers={project.developers} />
          ) : (
            <DevelopersFields value={developers} onChange={setDevelopers} error={apiError?.field('developers')} />
          )}
        </fieldset>
        {project.domain_expires_at && (
          <p className="text-xs text-muted sm:col-span-2">
            WHOIS: vence {formatRelative(project.domain_expires_at)} · consultado {formatRelative(project.domain_checked_at)}
          </p>
        )}
        {apiError && Object.keys(apiError.fieldErrors).length === 0 && <div className="sm:col-span-2"><ErrorNote>{apiError.message}</ErrorNote></div>}
        {!readOnly && (
          <div className="flex items-center gap-3 sm:col-span-2">
            <button type="submit" className="btn btn-primary" disabled={update.isPending}>
              {update.isPending && <Spinner />}
              Guardar
            </button>
            {update.isSuccess && <span className="fade-in text-sm text-ok">Guardado.</span>}
          </div>
        )}
      </form>
    </Panel>
  )
}
