import { useId, useRef, useState, type KeyboardEvent } from 'react'
import { inputClassName } from '@/components/ui/TextField'
import { mentionQueryAt, type Mentionable } from './mentions'

interface MentionTextareaProps {
  label: string
  value: string
  members: Mentionable[]
  onChange: (value: string) => void
  onMention: (member: Mentionable) => void
}

const MAX_SUGGESTIONS = 6

/** RF-04.06 comment box: typing `@` suggests project members (arrows + Enter, or click). */
export function MentionTextarea({ label, value, members, onChange, onMention }: MentionTextareaProps) {
  const id = useId()
  const listId = `${id}-mentions`
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const [caret, setCaret] = useState(0)
  const [active, setActive] = useState(0)
  const [dismissed, setDismissed] = useState(false)

  const query = dismissed ? null : mentionQueryAt(value, caret)
  const suggestions =
    query === null
      ? []
      : members.filter((member) => member.full_name.toLowerCase().includes(query.toLowerCase())).slice(0, MAX_SUGGESTIONS)
  const open = suggestions.length > 0
  const activeIndex = Math.min(active, suggestions.length - 1)

  function pick(member: Mentionable) {
    const before = value.slice(0, caret).replace(/@[\p{L}\p{M}]*$/u, `@${member.full_name} `)
    const next = before + value.slice(caret)
    onChange(next)
    onMention(member)
    setCaret(before.length)
    setActive(0)
    requestAnimationFrame(() => textareaRef.current?.setSelectionRange(before.length, before.length))
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (!open) return
    const chosen = suggestions[activeIndex]
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault()
      const step = event.key === 'ArrowDown' ? 1 : -1
      setActive((activeIndex + step + suggestions.length) % suggestions.length)
    } else if ((event.key === 'Enter' || event.key === 'Tab') && chosen) {
      event.preventDefault()
      pick(chosen)
    } else if (event.key === 'Escape') {
      event.stopPropagation()
      setDismissed(true)
    }
  }

  return (
    <div className="relative space-y-1">
      <label htmlFor={id} className="block text-sm text-muted">
        {label}
      </label>
      <textarea
        ref={textareaRef}
        id={id}
        rows={3}
        value={value}
        role="combobox"
        aria-expanded={open}
        aria-controls={listId}
        aria-autocomplete="list"
        aria-activedescendant={open ? `${listId}-${activeIndex}` : undefined}
        onChange={(event) => {
          setDismissed(false)
          setCaret(event.target.selectionStart)
          onChange(event.target.value)
        }}
        onSelect={(event) => setCaret(event.currentTarget.selectionStart)}
        onKeyDown={handleKeyDown}
        className={inputClassName}
      />
      <ul id={listId} role="listbox" aria-label="Mencionar a" hidden={!open} className="rounded border border-border bg-surface p-1 shadow">
        {suggestions.map((member, index) => (
          <li
            key={member.id}
            id={`${listId}-${index}`}
            role="option"
            aria-selected={index === activeIndex}
            onMouseDown={(event) => {
              event.preventDefault()
              pick(member)
            }}
            className={`cursor-pointer rounded px-2 py-1 text-sm ${index === activeIndex ? 'bg-accent text-white' : ''}`}
          >
            {member.full_name}
          </li>
        ))}
      </ul>
      <p className="text-xs text-muted">Escribí @ para mencionar a alguien del proyecto.</p>
    </div>
  )
}
