// Exact Worker routes cover internal backend files and retired identity folders,
// including previously cached objects. Normal /api, /index.php and assets are separate.
export default {
  fetch() {
    return new Response('Not found', { status: 404, headers: {
      'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff',
    } })
  },
}
