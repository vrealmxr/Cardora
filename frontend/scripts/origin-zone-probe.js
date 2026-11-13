import worker from '../dist/_worker.js'
import { resolvePublicRoute } from '../src/server/routePolicy.js'

export default { async fetch(request, env) {
  const url = new URL(request.url)
  const path = url.pathname.replace(/^\/__cardora_release_check/, '')
  const route = resolvePublicRoute(path)
  if (request.method !== 'GET' || url.href.length > 4096 || !['public', 'notfound'].includes(route.kind)) return new Response('Not found',{status:404})
  const response = await worker.fetch(new Request(`https://cardora.gr${path}${url.search}`,request),env)
  const result = new Response(response.body,response)
  result.headers.set('X-Robots-Tag','noindex, nofollow')
  return result
} }
