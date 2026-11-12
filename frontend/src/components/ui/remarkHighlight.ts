/**
 * Remark plugin: marks exact phrases (e.g. `@Ana García`) in text as `<span class=…>`, outside code and
 * links. Built on plain mdast nodes so it needs no extra dependency.
 */
interface MdNode {
  type: string
  value?: string
  children?: MdNode[]
  data?: Record<string, unknown>
}

const SKIP = new Set(['link', 'linkReference', 'inlineCode', 'code'])

function escapeRegExp(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function split(value: string, pattern: RegExp, className: string[]): MdNode[] {
  const nodes: MdNode[] = []
  let last = 0
  for (const match of value.matchAll(pattern)) {
    const start = match.index
    if (start > last) nodes.push({ type: 'text', value: value.slice(last, start) })
    nodes.push({
      type: 'highlight',
      data: { hName: 'span', hProperties: { className }, hChildren: [{ type: 'text', value: match[0] }] },
    })
    last = start + match[0].length
  }
  if (last < value.length) nodes.push({ type: 'text', value: value.slice(last) })
  return nodes
}

function walk(node: MdNode, pattern: RegExp, className: string[]) {
  if (!node.children || SKIP.has(node.type)) return
  node.children = node.children.flatMap((child) => {
    if (child.type === 'text' && child.value) return split(child.value, pattern, className)
    walk(child, pattern, className)
    return [child]
  })
}

export function remarkHighlight({ phrases, className }: { phrases: string[]; className: string[] }) {
  const unique = [...new Set(phrases.filter(Boolean))].sort((a, b) => b.length - a.length)
  return () => (tree: MdNode) => {
    if (unique.length === 0) return
    const pattern = new RegExp(unique.map(escapeRegExp).join('|'), 'gu')
    walk(tree, pattern, className)
  }
}
