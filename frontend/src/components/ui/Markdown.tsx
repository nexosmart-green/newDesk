import Markdown, { type Components } from 'react-markdown'
import remarkGfm from 'remark-gfm'

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

export function MarkdownView({ source, className = '' }: { source: string; className?: string }) {
  return (
    <div className={`markdown ${className}`}>
      <Markdown remarkPlugins={[remarkGfm]} components={components}>
        {source}
      </Markdown>
    </div>
  )
}
