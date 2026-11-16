import { Link } from 'react-router'

interface TaskCodeProps {
  code: string
  /** Links to the task (for places where the title next to it isn't already the link). */
  link?: boolean
  className?: string
}

const codeClassName = 'whitespace-nowrap text-[13px] font-medium text-muted tabular'

/**
 * A task's code exactly as it is stored and typed in URLs ("bhn-1"): quiet next to the title it labels,
 * never shouted (no caps, no monospace).
 */
export function TaskCode({ code, link = false, className = '' }: TaskCodeProps) {
  if (link) {
    return (
      <Link to={`/tareas/${code}`} className={`${codeClassName} underline-offset-2 hover:text-accent hover:underline ${className}`}>
        {code}
      </Link>
    )
  }
  return <span className={`${codeClassName} ${className}`}>{code}</span>
}
