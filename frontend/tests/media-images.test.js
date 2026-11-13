import test from 'node:test'
import assert from 'node:assert/strict'
import { lazyListingImageProps, originalImageProps } from '../src/utils/mediaImages.js'

test('portrait and landscape candidates use actual widths, never an assumed fixed image size', () => {
  for (const [width, height, thumbWidth, thumbHeight] of [[1200, 1800, 400, 600], [1800, 1200, 600, 400]]) {
    const media = { url: '/original.jpg', thumbUrl: '/preview.webp', width, height, thumbWidth, thumbHeight }
    const props = lazyListingImageProps(media)
    assert.equal(props.srcSet, `/preview.webp ${thumbWidth}w, /original.jpg ${width}w`)
    assert.equal(props.width, thumbWidth)
    assert.equal(props.height, thumbHeight)
    assert.equal(props.loading, 'lazy', 'auto sizes is valid only for lazy images')
    assert.equal(props.sizes, 'auto, 100vw')
    assert.deepEqual(originalImageProps(media), { src: '/original.jpg', width, height }, 'Zoom keeps the original')
  }
})

test('unknown dimensions, cropping, ambiguous URLs and equal-width sources do not invent responsive candidates', () => {
  const media = { url: '/original.jpg', thumbUrl: '/preview.webp', width: 1200, height: 1800, thumbWidth: 400, thumbHeight: 600 }
  for (const patch of [{ width: undefined }, { thumbWidth: 0 }, { thumbHeight: -1 }, { thumbWidth: '400' },
    { thumbWidth: 400.5 }, { thumbWidth: 50001 }, { thumbHeight: 400 }, { width: 400, height: 600 },
    { url: '/has space.jpg' }, { thumbUrl: '/has,comma.webp' }, { url: '/preview.webp' }]) {
    const props = lazyListingImageProps({ ...media, ...patch })
    assert.equal(props.srcSet, undefined, JSON.stringify(patch))
    assert.equal(props.sizes, undefined)
  }
  assert.deepEqual(originalImageProps({ url: '/original.jpg' }), { src: '/original.jpg' })
  assert.equal(lazyListingImageProps({ url: '/original.jpg', previewUrl: '/legacy.jpg', width: 1200, height: 1800 }).width, undefined)
})
