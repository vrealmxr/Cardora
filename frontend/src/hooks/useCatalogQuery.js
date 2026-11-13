import { useLocation, useSearchParams } from 'react-router-dom'
import { changeCatalogQuery, pageSearch, readCatalogQuery } from '@/utils/catalogQuery'

// The URL is the single source of truth, including browser Back/Forward and reloads.
export function useCatalogQuery(defaultFilters, allValue) {
  const { pathname } = useLocation()
  const [params, setParams] = useSearchParams()
  const state = readCatalogQuery(params, defaultFilters)
  return {
    ...state,
    setFilters: (filters) => setParams(changeCatalogQuery(params, filters, allValue)),
    setSort: (sort) => setParams(changeCatalogQuery(params, { sort }, allValue)),
    pageHref: (page) => `${pathname}${pageSearch(params, page)}`,
  }
}
