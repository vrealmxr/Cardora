import test from 'node:test'
import assert from 'node:assert/strict'
import { renderPublicPage } from '../dist-ssr/entry-server.js'
import { escapeHtml } from '../src/server/document.js'
import privacy from '../src/data/legal/privacy.js'
import cookies from '../src/data/legal/cookies.js'
import refunds from '../src/data/legal/refunds.js'
import prohibited from '../src/data/legal/prohibited.js'

const policies = [
  ['politiki-aporritou', privacy], ['politiki-cookies', cookies],
  ['epistrofes-kai-diafores', refunds], ['apagorevmena-antikeimena', prohibited],
]
const data = { products: [], categories: [], blogPosts: [] }

test('English policies retain all source sections, evidence lists, notices and operator identifiers', () => {
  for (const [route, document] of policies) {
    const { el, en } = document
    assert.deepEqual(Object.keys(en).sort(), Object.keys(el).sort(), route)
    assert.deepEqual(en.sections.map(section => section.id), el.sections.map(section => section.id), route)
    assert.equal(en.principles.length, el.principles.length, route)
    assert.equal(en.companyRows.length, el.companyRows.length, route)
    assert.equal(en.companyRows.find(([, value]) => value === '802264482')?.[1], '802264482')
    assert.equal(en.companyRows.find(([, value]) => value === '173245503000')?.[1], '173245503000')
    assert.deepEqual(en.related.map(item => item.to), el.related.map(item => item.to), route)
    for (const [index, section] of en.sections.entries()) {
      assert.equal(section.paragraphs?.length, el.sections[index].paragraphs?.length, `${route}/${section.id}`)
      assert.equal(section.bullets?.length, el.sections[index].bullets?.length, `${route}/${section.id}`)
      assert.equal(Boolean(section.notice), Boolean(el.sections[index].notice), `${route}/${section.id}`)
    }
    assert.ok(!/[\u0370-\u03ff\u1f00-\u1fff]/.test(JSON.stringify(en)), `${route}: untranslated Greek text`)
  }
})

test('both locales render complete legal text, correct metadata and localized policy/help links', async () => {
  for (const [route, document] of policies) {
    for (const locale of ['el', 'en']) {
      const copy = document[locale]
      const result = await renderPublicPage(`/${locale}/${route}`, data)
      assert.equal(result.head.status, 200)
      assert.equal(result.head.locale, locale)
      assert.equal(result.head.title, copy.title)
      assert.equal(result.head.canonical, `https://cardora.gr/${locale}/${route}`)
      assert.equal((result.html.match(/<h1[\s>]/g) || []).length, 1)
      for (const text of [copy.intro, copy.introSecondary, ...copy.sections.flatMap(section => [...section.paragraphs, ...(section.bullets || []), ...(section.notice ? [section.notice] : [])])]) {
        assert.ok(result.html.includes(escapeHtml(text)), `${route}/${locale}: missing text`)
      }
      for (const path of [...copy.related.map(item => item.to), copy.ctaPrimaryTo, copy.ctaSecondaryTo]) {
        assert.ok(result.html.includes(`href="/${locale}${path}"`))
        assert.ok(!result.html.includes(`href="${path}"`), `${route}/${locale}: link drops locale`)
      }
    }
  }
})

test('legal support and report sign-in actions resolve to existing localized routes', async () => {
  for (const locale of ['el', 'en']) {
    const terms = await renderPublicPage(`/${locale}/oroi-xrisis`, data)
    assert.ok(terms.html.includes(`href="/${locale}/kentro-ypostiriksis"`))
    assert.ok(!terms.html.includes('href="/kentro-ypostiriksis"'))
    const report = await renderPublicPage(`/${locale}/dsa-notice-action`, data)
    assert.ok(report.html.includes(`href="/${locale}/eisodos"`))
    assert.ok(!report.html.includes('href="/auth"'))
  }
})
