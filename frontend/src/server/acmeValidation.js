// Only the standard public HTTP-01 token path. Never proxies application data.
export default {
  async fetch(request) {
    const url = new URL(request.url);
    if (!['cardora.gr', 'www.cardora.gr'].includes(url.hostname) ||
        !/^\/\.well-known\/acme-challenge\/[A-Za-z0-9_-]{1,256}$/.test(url.pathname) ||
        !['GET','HEAD'].includes(request.method)) return new Response('Not found', {status:404});
    const response = await fetch('http://cardora.gr'+url.pathname, {
      method: request.method,
      headers: {Host:'cardora.gr','User-Agent':'Cardora-ACME-Validation/1.0'},
      redirect:'manual',
      cf:{resolveOverride:'origin-proxy.cardora.gr',cacheTtl:0,cacheEverything:false},
    });
    if (response.status !== 200) return new Response('Not found',{status:404});
    return new Response(response.body,{headers:{'Content-Type':'text/plain; charset=utf-8','Cache-Control':'no-store','X-Content-Type-Options':'nosniff'}});
  }
};
