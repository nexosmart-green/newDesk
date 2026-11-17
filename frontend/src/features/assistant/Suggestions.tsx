/** What an owner asks most (RF-14 origin): a click asks it as is. */
const SUGGESTIONS = [
  '¿Qué bolsas se están por agotar?',
  '¿Qué tareas del equipo están vencidas?',
  'Horas por persona de esta semana',
  'Pasame las horas por cliente de este mes en Excel',
] as const

interface SuggestionsProps {
  onPick: (text: string) => void
  disabled: boolean
}

/** A new conversation: what it can do, said once, and the frequent asks at hand. */
export function Suggestions({ onPick, disabled }: SuggestionsProps) {
  return (
    <div className="max-w-xl space-y-4">
      <p className="text-[15px] leading-relaxed">
        Preguntale por horas, bolsas, tareas, clientes o turnos, o pedile un reporte en Excel o PDF. Si le pedís un cambio, te lo deja
        listo para que lo confirmes.
      </p>
      <ul className="space-y-2" aria-label="Pedidos frecuentes">
        {SUGGESTIONS.map((suggestion) => (
          <li key={suggestion}>
            <button
              type="button"
              disabled={disabled}
              onClick={() => onPick(suggestion)}
              className="min-h-9 w-full rounded-md border border-line bg-surface px-3.5 py-2 text-left text-sm hover:border-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:opacity-50 coarse:min-h-11"
            >
              {suggestion}
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}
