export interface Mentionable {
  id: number
  full_name: string
}

/** `@` + letters right before the caret, e.g. "hola @Ju" → "Ju"; null when not mentioning. */
export function mentionQueryAt(text: string, caret: number): string | null {
  const match = /(?:^|\s)@([\p{L}\p{M}]*)$/u.exec(text.slice(0, caret))
  return match ? (match[1] ?? '') : null
}

/** Only people whose `@Full Name` is still in the text get notified (they may have been deleted). */
export function mentionedIdsIn(body: string, picked: Mentionable[]): number[] {
  return [...new Set(picked.filter((member) => body.includes(`@${member.full_name}`)).map((member) => member.id))]
}
