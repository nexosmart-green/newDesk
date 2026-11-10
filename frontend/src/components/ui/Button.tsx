import type { ButtonHTMLAttributes } from 'react'

type Variant = 'primary' | 'secondary' | 'danger' | 'link'

const variants: Record<Variant, string> = {
  // Brand teal from desk.nexosmart.es, the AA-safe shade for white text.
  primary: 'min-h-10 rounded-lg bg-brand-strong px-4 py-2 text-white transition-colors hover:bg-brand',
  secondary: 'min-h-10 rounded-lg border border-border bg-surface px-4 py-2 text-text transition-colors hover:bg-surface-2',
  danger: 'min-h-10 rounded-lg bg-danger px-4 py-2 text-white transition-opacity hover:opacity-90',
  link: 'text-accent underline-offset-2 hover:underline',
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
}

export function Button({ variant = 'primary', className, type = 'button', ...props }: ButtonProps) {
  return (
    <button
      type={type}
      className={`cursor-pointer text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50 ${variants[variant]} ${className ?? ''}`}
      {...props}
    />
  )
}
