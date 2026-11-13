import test from 'node:test'
import assert from 'node:assert/strict'
import { renderPublicPage } from '../dist-ssr/entry-server.js'
import { editorialGuideIndex, mergeEditorialGuides } from '../src/data/editorialGuideIndex.js'
import { getEditorialGuide } from '../src/data/editorialGuides.js'
import { createSitemap } from '../src/server/sitemap.js'
import { escapeHtml } from '../src/server/document.js'

const data = { products: [], categories: [], blogPosts: [] }

test('all four guides have substantial Greek and English text and valid references', () => {
  assert.equal(new Set(editorialGuideIndex.map(item => item.slug)).size, 4)
  for (const { slug } of editorialGuideIndex) {
    const greek = getEditorialGuide(slug, 'el')
    const english = getEditorialGuide(slug, 'en')
    assert.notEqual(greek.intro, english.intro)
    assert.equal(greek.sections.length, english.sections.length)
    for (const guide of [greek, english]) {
      const words = [guide.intro, ...guide.sections.flatMap(section => section.paragraphs)].join(' ').trim().split(/\s+/).length
      assert.ok(words >= 800 && words <= 1200, `${slug}: ${words} words`)
      const references = guide.sections.flatMap(section => section.sources || [])
      assert.ok(references.length > 0)
      for (const index of references) assert.match(guide.sources[index]?.url || '', /^https:\/\//)
    }
  }
})

test('each guide renders complete localized content, navigation and sources without client JavaScript', async () => {
  for (const locale of ['el', 'en']) {
    for (const { slug } of editorialGuideIndex) {
      const guide = getEditorialGuide(slug, locale)
      const result = await renderPublicPage(`/${locale}/blog/${slug}`, data)
      assert.equal(result.head.status, 200)
      assert.equal(result.head.locale, locale)
      assert.equal(result.head.canonical, `https://cardora.gr/${locale}/blog/${slug}`)
      assert.equal(result.head.title, `${guide.title} | Cardora`)
      assert.equal((result.html.match(/<h1[\s>]/g) || []).length, 1)
      assert.ok(result.html.includes(escapeHtml(guide.intro)))
      for (const [index, section] of guide.sections.entries()) {
        assert.ok(result.html.includes(`id="guide-section-${index + 1}"`))
        assert.ok(result.html.includes(`href="#guide-section-${index + 1}"`))
        for (const paragraph of section.paragraphs) assert.ok(result.html.includes(escapeHtml(paragraph)), `${slug}: missing paragraph`)
        for (const source of section.sources || []) assert.ok(result.html.includes(`href="${escapeHtml(guide.sources[source].url)}"`))
      }
      assert.ok(result.html.includes(`href="/${locale}/dimiourgia-aggelias"`))
      for (const path of guide.relatedPaths) assert.ok(result.html.includes(`href="/${locale}${path}"`))
    }
  }
})

test('guides are discoverable through the blog, hub and sitemap without losing existing articles', async () => {
  const existing = { id: 'existing', slug: 'existing-article', title: 'Existing article' }
  const collision = { slug: editorialGuideIndex[0].slug, title: 'Outdated duplicate' }
  const merged = mergeEditorialGuides([existing, collision], 'en')
  assert.equal(merged.length, 5)
  assert.ok(merged.includes(existing))
  assert.ok(!merged.includes(collision))
  const sitemap = createSitemap({ ...data, blogPosts: [existing, collision] }, []).xml
  for (const locale of ['el', 'en']) {
    const blog = await renderPublicPage(`/${locale}/blog`, data)
    const hub = await renderPublicPage(`/${locale}/syllektika`, data)
    for (const { slug } of editorialGuideIndex) {
      assert.ok(blog.html.includes(`href="/${locale}/blog/${slug}"`))
      assert.ok(hub.html.includes(`href="/${locale}/blog/${slug}"`))
      assert.equal(sitemap.split(`<loc>https://cardora.gr/${locale}/blog/${slug}</loc>`).length - 1, 1)
    }
    assert.ok(sitemap.includes(`/${locale}/blog/existing-article`))
  }
  assert.equal((await renderPublicPage('/el/blog/missing-guide-qa', data)).head.status, 404)
})
