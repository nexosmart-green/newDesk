import type { UseMutationResult } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { apiUrl } from '../api/monitoringApi'
import type { ReportMessage } from '../api/types'
import { formatDateTime, formatRelative } from '../lib/format'
import { Composer } from './Composer'

type Side = ReportMessage['author_kind']

export interface ReportThreadProps {
  // De qué lado está quien mira: sus mensajes van a la derecha.
  mine: Side
  // El reporte es el primer mensaje del chat (siempre del cliente).
  original: { author: string; created_at: string; description: string; screenshot_url: string | null }
  messages: ReportMessage[]
  send: UseMutationResult<ReportMessage, Error, FormData>
}

// Chat de un reporte (D-049): el mismo para el cliente en su portal y para el equipo.
export function ReportThread({ mine, original, messages, send }: ReportThreadProps) {
  const list = useRef<HTMLOListElement>(null)
  // Al llegar un mensaje, se baja hasta el último (dentro del chat, sin mover la página).
  useEffect(() => {
    if (list.current) list.current.scrollTop = list.current.scrollHeight
  }, [messages])

  // Lo propio se distingue: el equipo en el azul suave del panel; el cliente, en el color de su marca (accent del portal).
  const ownClass = mine === 'team' ? 'bg-info/10' : 'bg-accent-soft'
  // El equipo puede leer solo sus notas internas (los diagnósticos), sin el resto del chat. Al cliente nunca le llegan.
  const [onlyNotes, setOnlyNotes] = useState(false)
  const notes = messages.filter((message) => message.internal)
  const shown = onlyNotes ? notes : messages

  return (
    <div className="space-y-4">
      {mine === 'team' && (
        <label className="flex w-fit items-center gap-2 text-sm text-muted" title={notes.length === 0 ? 'Este reporte no tiene notas internas.' : undefined}>
          <input type="checkbox" checked={onlyNotes} disabled={notes.length === 0 && !onlyNotes} onChange={(event) => setOnlyNotes(event.target.checked)} />
          Solo notas internas ({notes.length})
        </label>
      )}
      <ol ref={list} aria-label="Conversación" className="max-h-[32rem] space-y-3 overflow-y-auto">
        {!onlyNotes && (
          <Bubble
            own={mine === 'client'}
            ownClass={ownClass}
            author={original.author}
            createdAt={original.created_at}
            body={original.description}
            images={original.screenshot_url ? [original.screenshot_url] : []}
          />
        )}
        {onlyNotes && notes.length === 0 && <li className="py-4 text-center text-sm text-muted">No hay notas internas en este reporte.</li>}
        {shown.map((message) => (
          <Bubble
            key={message.id}
            own={message.author_kind === mine}
            ownClass={message.internal ? 'bg-warn/10' : ownClass}
            internal={message.internal}
            author={message.author}
            createdAt={message.created_at}
            body={message.body}
            images={message.images}
          />
        ))}
      </ol>
      <Composer send={send} canNote={mine === 'team'} />
    </div>
  )
}

interface BubbleProps { own: boolean; ownClass: string; internal?: boolean; author: string; createdAt: string; body: string; images: string[] }

function Bubble({ own, ownClass, internal, author, createdAt, body, images }: BubbleProps) {
  return (
    <li className={`flex ${own ? 'justify-end' : ''}`}>
      <div className={`max-w-[85%] min-w-0 rounded-2xl px-4 py-2.5 ${own ? ownClass : 'bg-surface-2'}`}>
        <div className="mb-1 flex flex-wrap items-baseline gap-x-2 text-xs text-muted">
          {internal && <span className="font-medium text-warn">Nota interna</span>}
          <span className="font-medium text-text">{author}</span>
          <span title={formatDateTime(createdAt)}>{formatRelative(createdAt)}</span>
        </div>
        {body && <p className="text-sm break-words whitespace-pre-line">{body}</p>}
        {images.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-2">
            {images.map((url, index) => (
              <a key={url} href={apiUrl(url)} target="_blank" rel="noopener noreferrer" title="Abrir en tamaño completo">
                <img src={apiUrl(url)} alt={`Imagen ${index + 1} de ${author}`} className="h-24 w-32 rounded-lg bg-surface object-cover" />
              </a>
            ))}
          </div>
        )}
      </div>
    </li>
  )
}
