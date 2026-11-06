import type { DiscordChannel } from '../api/types'

// Los canales del bot agrupados por categoría, en el orden en que los manda el back (el de Discord).
// Los que el bot no puede usar quedan a la vista pero deshabilitados.
export function DiscordChannelOptions({ channels }: { channels: DiscordChannel[] }) {
  const groups = new Map<string, DiscordChannel[]>()
  for (const channel of channels) {
    const category = channel.category ?? 'Sin categoría'
    groups.set(category, [...(groups.get(category) ?? []), channel])
  }
  return (
    <>
      {[...groups].map(([category, items]) => (
        <optgroup key={category} label={category}>
          {items.map((channel) => (
            <option key={channel.id} value={channel.id} disabled={!channel.can_send}>
              {`# ${channel.name}${channel.kind === 'thread' ? ' (hilo)' : ''}${channel.can_send ? '' : ' — el bot no tiene acceso'}`}
            </option>
          ))}
        </optgroup>
      ))}
    </>
  )
}
