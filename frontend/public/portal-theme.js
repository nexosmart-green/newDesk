// Tema del portal del cliente antes del primer pintado (el public/theme.js de monitoreo, solo en /portal). Archivo aparte
// y no inline: un CSP con script-src 'self' lo deja correr. Misma key que src/features/monitoreo/lib/theme.ts.
(function () {
  if (!/^\/portal(\/|$)/.test(location.pathname)) return
  var pref = 'auto'
  try { var stored = localStorage.getItem('mon-theme-pref'); if (stored === 'light' || stored === 'dark') pref = stored } catch {}
  document.documentElement.dataset.portalTheme = pref
})()
