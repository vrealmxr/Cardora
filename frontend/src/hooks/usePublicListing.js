import { startTransition, useEffect, useState } from 'react'
import { cardoraService } from '@/services/cardoraService'
import { normalizePublicListing } from '@/utils/listingPresentation'

export function usePublicListing(slug, locale, seed, serverFallback) {
  const key = `${locale}:${slug}`
  const [result, setResult] = useState(() => seed?.slug === slug && seed?.locale === locale
    ? { ...seed, product: normalizePublicListing(seed.product), key }
    : typeof window === 'undefined' && !seed
      ? { key, status: serverFallback ? 200 : 404, product: serverFallback || null } : null)
  const [revision, setRevision] = useState(0)
  useEffect(() => {
    let active = true
    cardoraService.getPublicListing(slug, locale).then(product => {
      if (active) startTransition(() => setResult({ key, status: product ? 200 : 404, product: normalizePublicListing(product) }))
    }).catch(error => {
      if (active) startTransition(() => setResult({ key, status: error.status === 404 ? 404 : 503, product: null }))
    })
    return () => { active = false }
  }, [key, slug, locale, revision])
  return { result: result?.key === key ? result : null, retry: () => setRevision(value => value + 1) }
}
