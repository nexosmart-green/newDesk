import { useCallback, useEffect, useId } from 'react'

/** Open dialogs, innermost last: only the topmost one answers Escape / Tab. Also locks page scroll. */
const stack: string[] = []

export function useDialogStack(): () => boolean {
  const id = useId()

  useEffect(() => {
    stack.push(id)
    document.body.style.overflow = 'hidden'
    return () => {
      stack.splice(stack.indexOf(id), 1)
      if (stack.length === 0) document.body.style.overflow = ''
    }
  }, [id])

  return useCallback(() => stack[stack.length - 1] === id, [id])
}
