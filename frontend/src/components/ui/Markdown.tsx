import { useMemo } from 'react'
import Markdown, { type Components, type Options } from 'react-markdown'
import remarkBreaks from 'remark-breaks'
import remarkGfm from 'remark-gfm'
import { remarkHighlight } from './remarkHighlight'

/**
 * Rendering is safe by construction: react-markdown builds React elements (no raw HTML — no
 * rehype-raw) and drops `javascript:` and other unsafe URLs. Links leave the app in a new tab.
 */
const OWN_IMAGE_PREFIX = '/api/v1/task-images/'

const components: Components = {
  a: ({ node: _node, ...props }) => <a {...props} target="_blank" rel="noopener noreferrer" />,
  // Only our own uploads render inline (relative `/api/v1/task-images/…`, same-origin cookie). A remote
  // image would be a tracking pixel leaking every viewer's IP, so it shows as a link instead.
  img: ({ node: _node, alt, src, ...props }) =>
    typeof src === 'string' && src.startsWith(OWN_IMAGE_PREFIX) ? (
      <img {...props} src={src} alt={alt ?? ''} loading="lazy" decoding="async" />
    ) : (
      <a href={typeof src === 'string' ? src : undefined} target="_blank" rel="noopener noreferrer">
        {alt || 'Imagen externa'}
      </a>
    ),
}

const HIGHLIGHT_CLASS = ['rounded', 'bg-brand/15', 'px-1', 'font-medium', 'text-accent']

interface MarkdownViewProps {
  source: string
  className?: string
  /** Chat-like text (comments): a single line break is a break, not a space. */
  breaks?: boolean
  /** Exact phrases to stand out, such as `@Ana García` in a comment. */
  highlights?: string[]
}

export function MarkdownView({ source, className = '', breaks = false, highlights = [] }: MarkdownViewProps) {
  const highlightKey = highlights.join('\u0000')
  const plugins = useMemo<NonNullable<Options['remarkPlugins']>>(
    () => [
      remarkGfm,
      ...(breaks ? [remarkBreaks] : []),
      ...(highlightKey ? [remarkHighlight({ phrases: highlightKey.split('\u0000'), className: HIGHLIGHT_CLASS })] : []),
    ],
    [breaks, highlightKey],
  )
  return (
    <div className={`markdown ${className}`}>
      <Markdown remarkPlugins={plugins} components={components}>
        {source}
      </Markdown>
    </div>
  )
}
