import type { ReactNode } from 'react'

/** Centered card used by the guest pages (login, password recovery, invitation). */
export function AuthCard({ title, children }: { title: string; children: ReactNode }) {
  return (
    <main className="flex min-h-screen items-center justify-center p-4">
      <section className="w-full max-w-sm space-y-4 rounded-lg border border-border bg-surface p-6">
        <h1 className="text-xl font-semibold">{title}</h1>
        {children}
      </section>
    </main>
  )
}
