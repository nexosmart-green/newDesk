import { LoaderCircle } from 'lucide-react'
import type { ButtonHTMLAttributes } from 'react'

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'live' | 'icon' | 'link'

/** 36px with a mouse, 44px on touch screens (RNF-13): one density for desk work, one for fingers. */
const control = 'min-h-9 rounded-md px-3.5 py-1.5 coarse:min-h-11 coarse:px-4'

const variants: Record<Variant, string> = {
  // Brand teal from desk.nexosmart.es, the AA-safe shade for white text. Flat: tone, not shadow.
  primary: `${control} bg-brand-strong text-white transition-colors hover:bg-brand active:bg-brand-strong`,
  secondary: `${control} border border-border bg-surface text-text transition-colors hover:border-muted/50 hover:bg-surface-2`,
  danger: `${control} bg-danger text-white transition-opacity hover:opacity-90`,
  ghost: `${control} text-muted transition-colors hover:bg-surface-2 hover:text-text`,
  /** Only for the running timer: desk's pink means "running now" and nothing else. */
  live: `${control} bg-live text-on-live transition-opacity hover:opacity-90`,
  /** Square, icon only: always pass an `aria-label`. */
  icon: 'size-9 shrink-0 rounded-md text-muted transition-colors hover:bg-surface-2 hover:text-text coarse:size-11',
  link: 'text-accent underline-offset-2 hover:underline',
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  /** While the action runs: spinner, disabled, same width (the label stays, invisible, for size and name). */
  loading?: boolean
}

export function Button({ variant = 'primary', loading = false, className, type = 'button', disabled, children, ...props }: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={`relative inline-flex cursor-pointer items-center justify-center gap-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50 aria-busy:cursor-progress aria-busy:opacity-80 ${variants[variant]} ${className ?? ''}`}
      {...props}
    >
      <span className={`inline-flex items-center justify-center gap-1.5 ${loading ? 'opacity-0' : ''}`}>{children}</span>
      {loading && (
        <span className="absolute inset-0 grid place-items-center" aria-hidden="true">
          <LoaderCircle className="size-4 animate-spin" />
        </span>
      )}
    </button>
  )
}
