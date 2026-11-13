export const PUBLIC_STATIC_PATHS = [
  'syllektika', 'odigos-polisis', 'katastasi-kai-grading',
  '', 'kartes', 'figoures', 'komik-vivlia', 'diafora', 'cardora', 'faq', 'epikoinonia',
  'blog', 'tutorials', 'kentro-ypostiriksis', 'oroi-xrisis', 'politiki-aporritou',
  'politiki-cookies', 'dsa-notice-action', 'epistrofes-kai-diafores', 'apagorevmena-antikeimena', 'kliroseis',
]
export const ROUTE_ALIASES = {
  'koinotikes-kliroseis': 'kliroseis', 'verify-email': 'epivevaiosi-email',
  'email-verification': 'epivevaiosi-email', 'email/verification': 'epivevaiosi-email',
  login: 'eisodos', register: 'eggrafi', search: 'anazitisi', terms: 'oroi-xrisis',
  privacy: 'politiki-aporritou', cookies: 'politiki-cookies', 'notice-and-action': 'dsa-notice-action',
  refunds: 'epistrofes-kai-diafores', 'prohibited-items': 'apagorevmena-antikeimena',
}
const CLIENT_PATHS = new Set([
  'eisodos', 'eggrafi', 'auth/google/callback', 'epivevaiosi-email', 'xechasa-kodiko',
  'epanafora-kodikou', 'profil', 'rythmiseis-logariasmou', 'rythmiseis-eidopoiiseon',
  'epalithefsi-logariasmou', 'epalithefsi-kinitou', 'epalithefsi-apotelesma',
  'dashboard-agorasti', 'dashboard-politi', 'dashboard-politi/kliroseis', 'kalathi',
  'agapimena', 'checkout', 'checkout/success', 'paraggelies', 'oi-aggelies-mou',
  'dimiourgia-aggelias', 'minymata', 'cardora-pro', 'cardora-pro/success',
  'cardora-scanner', 'cardora-binder', 'cardora-binder/library', 'cardora-binder/alerts',
  'cardora-binder/duplicates', 'cardora-binder/games',
])

export function resolvePublicRoute(pathname) {
  let path
  try { path = decodeURIComponent(pathname) } catch { return { kind: 'invalid', status: 400 } }
  if ([...path].some((char) => char.charCodeAt(0) < 32) || path.includes('\\') || path.includes('//') || /%2f|%5c/i.test(pathname)) return { kind: 'invalid', status: 400 }
  const matched = path.match(/^\/(el|en)(?=\/|$)/)
  const locale = matched?.[1] || 'el'
  const slug = path.replace(/^\/(el|en)(?=\/|$)/, '').replace(/^\/|\/$/g, '')
  let kind = 'notfound'
  if (PUBLIC_STATIC_PATHS.includes(slug) || slug === 'anazitisi') kind = 'public'
  else if (/^(proion|blog|tutorials|sylloges)\/[^/]+$/.test(slug)) kind = 'public'
  else if (CLIENT_PATHS.has(slug)
    || /^cardora-binder\/(games|sets)\/[^/]+$/.test(slug)) kind = 'client'
  const alias = ROUTE_ALIASES[slug]
  if (alias) return { kind: 'redirect', locale, target: `/${locale}/${alias}` }
  if (!matched || (kind !== 'notfound' && pathname.endsWith('/'))) {
    return { kind: 'redirect', locale, target: `/${locale}${slug ? '/' + slug : ''}` }
  }
  return { kind, locale, slug, status: kind === 'notfound' ? 404 : 200 }
}
