import authenticity from './guides/authenticity.js'
import grading from './guides/grading.js'
import shipping from './guides/shipping.js'
import storage from './guides/storage.js'
import { getEditorialGuideIndex } from './editorialGuideIndex.js'

const guides = {
  'pos-na-steileis-kartes-me-asfaleia': shipping,
  'pos-na-fylaxeis-kartes-kai-komiks': storage,
  'pos-na-elegxeis-gnisiotita-kartas-pokemon': authenticity,
  'ti-simainei-psa-10-kai-pote-axizei-to-grading': grading,
}
export function getEditorialGuide(slug, locale = 'el') {
  const guide = guides[slug]
  if (!guide) return null
  const lang = locale === 'en' ? 'en' : 'el'
  return { ...getEditorialGuideIndex(lang).find(item => item.slug === slug), ...guide[lang], sources: guide.sources, relatedPaths: guide.relatedPaths }
}
