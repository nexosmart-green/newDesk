/** "a@x.com, b@y.com" → ["a@x.com", "b@y.com"] (commas, semicolons or spaces); the backend validates each one. */
export function parseEmailList(text: string): string[] {
  return text
    .split(/[,;\s]+/)
    .map((email) => email.trim())
    .filter(Boolean)
}
