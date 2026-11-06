import '@testing-library/jest-dom/vitest'
import { configure } from '@testing-library/react'

// The full suite runs jsdom files in parallel; 1s for findBy* flakes on slower machines.
configure({ asyncUtilTimeout: 3000 })
