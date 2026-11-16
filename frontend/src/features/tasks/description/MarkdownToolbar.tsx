import { Bold, Code, Heading2, ImagePlus, Italic, Link2, List, ListChecks, type LucideIcon } from 'lucide-react'
import type { MarkdownCommand } from './markdownCommands'

interface ToolbarAction {
  command: MarkdownCommand
  label: string
  icon: LucideIcon
  shortcut?: string
}

const actions: ToolbarAction[] = [
  { command: 'bold', label: 'Negrita', icon: Bold, shortcut: 'Ctrl+B' },
  { command: 'italic', label: 'Cursiva', icon: Italic, shortcut: 'Ctrl+I' },
  { command: 'heading', label: 'Encabezado', icon: Heading2 },
  { command: 'list', label: 'Lista', icon: List },
  { command: 'checklist', label: 'Lista de tareas', icon: ListChecks },
  { command: 'link', label: 'Enlace', icon: Link2, shortcut: 'Ctrl+K' },
  { command: 'code', label: 'Código', icon: Code },
]

const buttonClass =
  'grid size-9 shrink-0 cursor-pointer place-items-center rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-40'

interface MarkdownToolbarProps {
  onCommand: (command: MarkdownCommand) => void
  onPickImage: () => void
  canUpload: boolean
  /** A shorter bar (comments) shows only these. */
  commands?: MarkdownCommand[]
}

/** Icon buttons with their name (and shortcut) as label and tooltip. Mouse down keeps the textarea selection. */
export function MarkdownToolbar({ onCommand, onPickImage, canUpload, commands }: MarkdownToolbarProps) {
  const shown = commands ? actions.filter((action) => commands.includes(action.command)) : actions
  return (
    <div role="toolbar" aria-label="Formato" className="flex flex-wrap items-center gap-0.5">
      {shown.map(({ command, label, icon: Icon, shortcut }) => {
        const name = shortcut ? `${label} (${shortcut})` : label
        return (
          <button
            key={command}
            type="button"
            aria-label={name}
            title={name}
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => onCommand(command)}
            className={buttonClass}
          >
            <Icon aria-hidden="true" className="size-4" strokeWidth={2} />
          </button>
        )
      })}
      <span aria-hidden="true" className="mx-1 h-5 w-px bg-border" />
      <button
        type="button"
        aria-label="Insertar imagen"
        title={canUpload ? 'Insertar imagen (también podés pegarla o arrastrarla)' : 'Guardá la tarea en un proyecto para subir imágenes'}
        disabled={!canUpload}
        onMouseDown={(event) => event.preventDefault()}
        onClick={onPickImage}
        className={buttonClass}
      >
        <ImagePlus aria-hidden="true" className="size-4" strokeWidth={2} />
      </button>
    </div>
  )
}
