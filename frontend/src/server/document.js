export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[char]))

export const safeJson = (value) => JSON.stringify(value).replace(/[<>&\u2028\u2029]/g,
  (char) => `\\u${char.charCodeAt(0).toString(16).padStart(4, '0')}`)

export function buildDocument(template, { html = '', head }, initialData) {
  const meta = (attribute, name, content) => `<meta ${attribute}="${name}" content="${escapeHtml(content)}">`
  const tags = [
    `<title>${escapeHtml(head.title)}</title>`,
    meta('name', 'description', head.description), meta('name', 'robots', head.robots),
    meta('property', 'og:title', head.title), meta('property', 'og:description', head.description),
    meta('property', 'og:site_name', 'Cardora'),
    meta('property', 'og:url', head.canonical), meta('property', 'og:type', head.type || 'website'),
    meta('property', 'og:locale', head.locale === 'en' ? 'en_US' : 'el_GR'),
    meta('property', 'og:image', head.image), meta('name', 'twitter:image', head.image),
    meta('name', 'twitter:title', head.title), meta('name', 'twitter:description', head.description),
    meta('name', 'twitter:card', 'summary_large_image'),
    `<link rel="canonical" href="${escapeHtml(head.canonical)}">`,
    ...Object.entries(head.alternates || {}).map(([locale, href]) =>
      `<link rel="alternate" hreflang="${locale}" href="${escapeHtml(href)}">`),
  ].join('\n')
  const clean = template.replace(/<html\s+lang="[^"]*"/, `<html lang="${head.locale}"`)
    .replace(/<link\b[^>]*data-cardora-font-locale="([^"]+)"[^>]*>/gi, (tag, locale) => locale === head.locale ? tag : '')
    .replace(/<title>[\s\S]*?<\/title>/gi, '')
    .replace(/<meta\b[^>]*(?:name|property)="(?:description|robots|og:[^"\s]+|twitter:[^"\s]+)"[^>]*>/gi, '')
    .replace(/<link\b[^>]*rel="(?:canonical|alternate)"[^>]*>/gi, '')
  if (!clean.includes('<div id="root"></div>')) throw new Error('Missing application root')
  const payload = initialData ? `<script type="application/json" id="cardora-public-data">${safeJson(initialData)}</script>` : ''
  return clean.replace('</head>', `${tags}\n</head>`)
    .replace('<div id="root"></div>', () => `<div id="root">${html}</div>${payload}`)
}
