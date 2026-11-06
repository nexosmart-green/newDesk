// Set mínimo de íconos en SVG inline (trazo 1.5, 16px). Sin librerías.
const paths = {
  copy: 'M5.5 5.5V3.25c0-.41.34-.75.75-.75h6.5c.41 0 .75.34.75.75v6.5c0 .41-.34.75-.75.75H10.5M3.25 5.5h6.5c.41 0 .75.34.75.75v6.5c0 .41-.34.75-.75.75h-6.5a.75.75 0 0 1-.75-.75v-6.5c0-.41.34-.75.75-.75Z',
  check: 'M3 8.5l3 3 7-7',
  refresh: 'M13.5 8A5.5 5.5 0 1 1 11.9 4.1M13.5 2.5v3h-3',
  servers: 'M2.5 3.5h11v4h-11zM2.5 8.5h11v4h-11zM4.5 5.5h.01M4.5 10.5h.01',
  bell: 'M4 11V7a4 4 0 0 1 8 0v4l1 1.5H3L4 11ZM6.5 14h3',
  cog: 'M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM8 1.5v2M8 12.5v2M1.5 8h2M12.5 8h2M3.4 3.4l1.4 1.4M11.2 11.2l1.4 1.4M3.4 12.6l1.4-1.4M11.2 4.8l1.4-1.4',
  logout: 'M6 2.5H3.25a.75.75 0 0 0-.75.75v9.5c0 .41.34.75.75.75H6M10.5 11l3-3-3-3M13.5 8h-7',
  sun: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3 3l1 1M12 12l1 1M3 13l1-1M12 4l1-1',
  moon: 'M13.5 9.5A5.5 5.5 0 0 1 6.5 2.5a5.5 5.5 0 1 0 7 7Z',
  display: 'M2.5 3.5h11v7h-11zM6 13.5h4M8 10.5v3',
  bars: 'M3.75 9.5v3M8 6v6.5M12.25 3.5v9',
  chevron: 'M6 4l4 4-4 4',
  alert: 'M8 5.5v3M8 11h.01M7.13 2.5 1.7 12a1 1 0 0 0 .87 1.5h10.86a1 1 0 0 0 .87-1.5L8.87 2.5a1 1 0 0 0-1.74 0Z',
  download: 'M8 2.5v8M4.5 7 8 10.5 11.5 7M2.5 13.5h11',
  plus: 'M8 3v10M3 8h10',
  menu: 'M2.5 4.5h11M2.5 11.5h11',
  search: 'M7 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10ZM10.75 10.75 14 14',
  inbox: 'M2.5 9.5h3l1 2h3l1-2h3M2.5 9.5 4 3.5h8l1.5 6v3a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5Z',
  image: 'M2.5 3.5h11v9h-11zM2.5 10.5l3-3 3 3 2-2 3 3M10.25 6.25h.01',
  users: 'M6 7.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1.5 13.5c0-2.2 2-4 4.5-4s4.5 1.8 4.5 4M10.5 2.6a2.5 2.5 0 0 1 0 4.8M12.5 9.7c1.2.6 2 1.9 2 3.8',
  close: 'M4 4l8 8M12 4l-8 8',
  minimize: 'M3.5 12.5h9',
  float: 'M2.5 3.5h11v9h-11zM8.5 8.5h3.5v2.5H8.5z',
  roadmap: 'M5.5 4h8M5.5 8h8M5.5 12h8M2.5 4h.01M2.5 8h.01M2.5 12h.01',
  grip: 'M6 4h.01M10 4h.01M6 8h.01M10 8h.01M6 12h.01M10 12h.01',
  up: 'M4 10l4-4 4 4',
  down: 'M4 6l4 4 4-4',
} as const

export type IconName = keyof typeof paths

export function Icon({ name, size = 16, className }: { name: IconName; size?: number; className?: string }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 16 16"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.5}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={className}
    >
      <path d={paths[name]} />
    </svg>
  )
}
