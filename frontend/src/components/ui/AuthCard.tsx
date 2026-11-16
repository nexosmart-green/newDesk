import type { ReactNode } from 'react'

/**
 * Guest pages (login, password recovery, invitation): the product on the sidebar's ink — the same
 * place you'll find it once inside — and one card to fill in.
 */
export function AuthCard({ title, children }: { title: string; children: ReactNode }) {
  return (
    <main className="flex min-h-dvh flex-col items-center justify-center gap-8 bg-sidebar px-4 py-10">
      <p className="flex items-center gap-3">
        <span
          aria-hidden="true"
          className="grid size-10 place-items-center rounded-[10px] bg-brand text-xl font-bold leading-none text-white shadow-[inset_0_-2px_0_rgba(0,0,0,0.18)]"
        >
          n
        </span>
        <span className="text-xl font-semibold tracking-tight text-white">newDesk</span>
      </p>
      <section className="w-full max-w-sm space-y-4 rounded-xl border border-border bg-surface p-6 sm:p-7">
        <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
        {children}
      </section>
    </main>
  )
}
