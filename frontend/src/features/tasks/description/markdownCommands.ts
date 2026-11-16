/** What the editor holds: the text and the selection. Commands are pure so they're tested without a DOM. */
export interface EditorState {
  value: string
  start: number
  end: number
}

export type MarkdownCommand = 'bold' | 'italic' | 'heading' | 'list' | 'checklist' | 'link' | 'code'

function replaceRange(state: EditorState, text: string, selectFrom: number, selectTo: number): EditorState {
  const value = state.value.slice(0, state.start) + text + state.value.slice(state.end)
  return { value, start: state.start + selectFrom, end: state.start + selectTo }
}

/** `**x**`: wraps the selection, or inserts a placeholder selected so typing replaces it. */
function wrapSelection(state: EditorState, before: string, after: string, placeholder: string): EditorState {
  const selected = state.value.slice(state.start, state.end)
  const inner = selected || placeholder
  return replaceRange(state, `${before}${inner}${after}`, before.length, before.length + inner.length)
}

/** `## ` / `- ` / `- [ ] ` on every line the selection touches; applying it again removes it. */
export function toggleLinePrefix(state: EditorState, prefix: string): EditorState {
  const lineStart = state.value.lastIndexOf('\n', state.start - 1) + 1
  const nextBreak = state.value.indexOf('\n', state.end)
  const lineEnd = nextBreak === -1 ? state.value.length : nextBreak
  const lines = state.value.slice(lineStart, lineEnd).split('\n')
  const allPrefixed = lines.every((line) => line.startsWith(prefix))
  const changed = lines.map((line) => (allPrefixed ? line.slice(prefix.length) : `${prefix}${line}`)).join('\n')
  const value = state.value.slice(0, lineStart) + changed + state.value.slice(lineEnd)
  const delta = allPrefixed ? -prefix.length : prefix.length
  return { value, start: Math.max(lineStart, state.start + delta), end: lineStart + changed.length }
}

export function insertText(state: EditorState, text: string): EditorState {
  return replaceRange(state, text, text.length, text.length)
}

/** `[texto](https://)` with the URL part selected, ready to paste the link. */
function link(state: EditorState): EditorState {
  const text = state.value.slice(state.start, state.end) || 'texto'
  const url = 'https://'
  const markup = `[${text}](${url})`
  return replaceRange(state, markup, text.length + 3, text.length + 3 + url.length)
}

/** Inline backticks for a fragment; a fenced block when the selection spans lines. */
function code(state: EditorState): EditorState {
  const selected = state.value.slice(state.start, state.end)
  if (!selected.includes('\n')) return wrapSelection(state, '`', '`', 'código')
  const needsBreak = state.start > 0 && state.value[state.start - 1] !== '\n'
  return wrapSelection(state, `${needsBreak ? '\n' : ''}\`\`\`\n`, '\n```\n', selected)
}

export function applyCommand(state: EditorState, command: MarkdownCommand): EditorState {
  switch (command) {
    case 'bold':
      return wrapSelection(state, '**', '**', 'negrita')
    case 'italic':
      return wrapSelection(state, '_', '_', 'cursiva')
    case 'heading':
      return toggleLinePrefix(state, '## ')
    case 'list':
      return toggleLinePrefix(state, '- ')
    case 'checklist':
      return toggleLinePrefix(state, '- [ ] ')
    case 'link':
      return link(state)
    case 'code':
      return code(state)
  }
}

/** Uploads in flight show as a marker in the text, swapped for the image (or removed) when they end. */
export function uploadMarker(id: number): string {
  return `![Subiendo imagen ${id}…]()`
}

export function imageMarkdown(url: string, name: string): string {
  const alt = name.replace(/\.[a-z0-9]+$/i, '').replace(/[[\]]/g, '')
  return `![${alt}](${url})`
}
