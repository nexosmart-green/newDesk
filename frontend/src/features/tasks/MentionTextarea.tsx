import { useState, type KeyboardEvent, type RefObject, type TextareaHTMLAttributes } from 'react'
import { inputClassName } from '@/components/ui/TextField'
import { mentionQueryAt, type Mentionable } from './mentions'

type NativeProps = Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'value' | 'onChange' | 'role'>

interface MentionTextareaProps extends NativeProps {
  id: string
  value: string
  members: Mentionable[]
  onChange: (value: string) => void
  onMention: (member: Mentionable) => void
  textareaRef: RefObject<HTMLTextAreaElement | null>
}

const MAX_SUGGESTIONS = 6

/**
 * RF-04.06 comment box: typing `@` suggests project members (arrows + Enter, or click). The label and
 * the frame are the caller's; keys the suggestions don't use go on to the caller's `onKeyDown`.
 */
export function MentionTextarea({ id, value, members, onChange, onMention, textareaRef, onKeyDown, className, ...native }: MentionTextareaProps) {
  const listId = `${id}-mentions`
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
    const chosen = suggestions[activeIndex]
    if (open && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
      event.preventDefault()
      const step = event.key === 'ArrowDown' ? 1 : -1
      setActive((activeIndex + step + suggestions.length) % suggestions.length)
    } else if (open && (event.key === 'Enter' || event.key === 'Tab') && chosen && !event.ctrlKey && !event.metaKey) {
      event.preventDefault()
      pick(chosen)
    } else if (open && event.key === 'Escape') {
      event.stopPropagation()
      setDismissed(true)
    } else {
      onKeyDown?.(event)
    }
  }

  return (
    <div className="relative">
      <textarea
        ref={textareaRef}
        id={id}
        rows={3}
        {...native}
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
        className={className ?? inputClassName}
      />
      <ul
        id={listId}
        role="listbox"
        aria-label="Mencionar a"
        hidden={!open}
        className="absolute inset-x-2 top-full z-20 mt-1 max-w-xs rounded-lg border border-border bg-surface p-1 shadow-lg"
      >
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
            className={`cursor-pointer rounded-md px-2 py-1.5 text-sm ${index === activeIndex ? 'bg-brand-strong text-white' : 'hover:bg-surface-2'}`}
          >
            {member.full_name}
          </li>
        ))}
      </ul>
    </div>
  )
}
