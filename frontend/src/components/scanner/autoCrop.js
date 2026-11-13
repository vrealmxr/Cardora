// Local CPU processing, lazy loaded and isolated from the UI. No remote image service.
let queue = Promise.resolve()
let worker = null
let idleTimer
const stopWorker = () => { worker?.terminate(); worker = null; clearTimeout(idleTimer) }
function locate(image) {
  const task = queue.then(() => new Promise(resolve => {
    clearTimeout(idleTimer)
    let timer
    const finish = result => {
      clearTimeout(timer)
      if (worker) { worker.onmessage = null; worker.onerror = null }
      idleTimer = setTimeout(stopWorker, 30000)
      resolve(result)
    }
    try {
      worker ||= new Worker('/vendor/cardora-crop-v2/crop-worker-v2.js')
      worker.onmessage = event => finish(event.data)
      worker.onerror = () => { stopWorker(); finish({ status: 'unavailable' }) }
      timer = setTimeout(() => { stopWorker(); finish({ status: 'unavailable' }) }, 45000)
      worker.postMessage(image, [image.data.buffer])
    } catch { stopWorker(); finish({ status: 'unavailable' }) }
  }))
  queue = task.catch(() => {})
  return task
}
const encode = canvas => new Promise((resolve, reject) => canvas.toBlob(blob => {
  if (!blob || blob.size > 10 * 1024 * 1024) reject(new Error('image_size'))
  else resolve(new File([blob], 'card.png', { type: 'image/png' }))
}, 'image/png'))
export async function prepareCardPhoto(file) {
  const image = await createImageBitmap(file, { imageOrientation: 'from-image' })
  try {
    if (image.width * image.height > 16000000 || Math.min(image.width, image.height) < 128) throw new Error('image_size')
    const scale = Math.min(1, 1800 / Math.max(image.width, image.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(image.width * scale); canvas.height = Math.round(image.height * scale)
    const ctx = canvas.getContext('2d', { willReadFrequently: true })
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height)
    // If geometry is uncertain, recognition still works but this submission cannot enter training.
    const normalized = await encode(canvas)
    const result = await locate(ctx.getImageData(0, 0, canvas.width, canvas.height))
    if (result.status !== 'cropped') return { file: normalized, cropped: false }
    canvas.width = result.width; canvas.height = result.height
    ctx.putImageData(new ImageData(result.data, result.width, result.height), 0, 0)
    return { file: await encode(canvas), cropped: true }
  } finally { image.close() }
}
