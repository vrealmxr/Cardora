const dimension = value => Number.isInteger(value) && value > 0 && value <= 50000
const hasDimensions = (width, height) => dimension(width) && dimension(height)

export function originalImageProps(media) {
  return {
    src: media?.url,
    ...(hasDimensions(media?.width, media?.height) ? { width: media.width, height: media.height } : {}),
  }
}

// Only for lazy images: auto measures the actual slot, including narrow carousels.
// Older browsers use the conservative viewport fallback. No invented image variants.
export function lazyListingImageProps(media) {
  const src = media?.thumbUrl ?? media?.previewUrl ?? media?.url
  const isThumb = src && src === media?.thumbUrl
  const width = isThumb ? media.thumbWidth : src === media?.url ? media?.width : undefined
  const height = isThumb ? media.thumbHeight : src === media?.url ? media?.height : undefined
  const props = { src, loading: 'lazy', decoding: 'async' }
  if (!hasDimensions(width, height)) return props
  Object.assign(props, { width, height })
  const original = originalImageProps(media)
  // A cropped preview is not a lower-resolution copy; never mix differing framing.
  const sameRatio = original.width && Math.abs(width / height - original.width / original.height) <= 2 / height
  const safeCandidate = url => typeof url === 'string' && url.length > 0 && !/[\s,]/.test(url)
  if (isThumb && src !== original.src && width < original.width && sameRatio && safeCandidate(src) && safeCandidate(original.src)) {
    props.srcSet = `${src} ${width}w, ${original.src} ${original.width}w`
    props.sizes = 'auto, 100vw'
  }
  return props
}
