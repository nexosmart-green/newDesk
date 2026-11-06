import type { ButtonHTMLAttributes } from 'react'

type Variant = 'primary' | 'secondary' | 'danger' | 'link'

const variants: Record<Variant, string> = {
  primary: 'rounded bg-accent px-3 py-2 text-white',
  secondary: 'rounded border border-border bg-surface px-3 py-2 text-text',
  danger: 'rounded bg-danger px-3 py-2 text-white',
  link: 'text-accent underline-offset-2 hover:underline',
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
}

export function Button({ variant = 'primary', className, type = 'button', ...props }: ButtonProps) {
  return (
    <button
      type={type}
      className={`text-sm font-medium focus-visible:outline-2 focus-visible:outline-accent disabled:opacity-60 ${variants[variant]} ${className ?? ''}`}
      {...props}
    />
  )
}
