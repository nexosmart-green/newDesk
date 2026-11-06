interface AvatarProps {
  name: string
  url: string | null | undefined
  size?: 'sm' | 'md' | 'lg'
}

const sizes = { sm: 'size-8 text-xs', md: 'size-12 text-sm', lg: 'size-24 text-2xl' } as const

function initialsOf(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
}

/** Photo when there is one, initials otherwise. Decorative: the name is always shown next to it. */
export function Avatar({ name, url, size = 'md' }: AvatarProps) {
  return url ? (
    <img src={url} alt="" className={`${sizes[size]} shrink-0 rounded-full object-cover`} />
  ) : (
    <span
      aria-hidden="true"
      className={`${sizes[size]} flex shrink-0 items-center justify-center rounded-full bg-accent/20 font-semibold text-accent`}
    >
      {initialsOf(name)}
    </span>
  )
}
