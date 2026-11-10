import '@testing-library/jest-dom/vitest'
import { configure } from '@testing-library/react'
import { toast } from '@/components/ui/toast/toastStore'

// The full suite runs jsdom files in parallel; 1s for findBy* flakes on slower machines.
configure({ asyncUtilTimeout: 3000 })

// Toasts live in a module-level store: one test's leftovers must not leak into the next.
afterEach(() => toast.clear())
