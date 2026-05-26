import { createContext, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useAuthContext } from '@/context/AuthContext'
import { useMarketplaceContext } from '@/context/MarketplaceContext'

const PageLoaderContext = createContext(null)

const createToken = (prefix = 'page') =>
  `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`

export function PageLoaderProvider({ children }) {
  const location = useLocation()
  const { isAuthReady, isAuthenticating } = useAuthContext()
  const { isReady } = useMarketplaceContext()
  const [pendingTokens, setPendingTokens] = useState([])
  const hasCompletedFirstBootRef = useRef(false)
  const previousPathRef = useRef(location.pathname)

  const bootLoading = !isAuthReady || !isReady || isAuthenticating

  useEffect(() => {
    if (!bootLoading) {
      hasCompletedFirstBootRef.current = true
    }
  }, [bootLoading])

  const startLoading = useCallback((providedToken) => {
    const token = providedToken || createToken()

    setPendingTokens((current) => (current.includes(token) ? current : [...current, token]))

    return token
  }, [])

  const stopLoading = useCallback((token) => {
    if (!token) return

    setPendingTokens((current) => current.filter((entry) => entry !== token))
  }, [])

  const withPageLoader = useCallback(
    async (task, options = {}) => {
      const { key, minDuration = 380 } = options
      const token = startLoading(key || createToken('task'))
      const startedAt = Date.now()

      try {
        return await task()
      } finally {
        const elapsed = Date.now() - startedAt
        const remaining = Math.max(minDuration - elapsed, 0)

        if (remaining > 0) {
          await new Promise((resolve) => window.setTimeout(resolve, remaining))
        }

        stopLoading(token)
      }
    },
    [startLoading, stopLoading],
  )

  useLayoutEffect(() => {
    if (!hasCompletedFirstBootRef.current) {
      previousPathRef.current = location.pathname
      return undefined
    }

    if (previousPathRef.current === location.pathname) {
      return undefined
    }

    previousPathRef.current = location.pathname

    const token = startLoading(`route:${location.pathname}`)
    const timeoutId = window.setTimeout(() => {
      stopLoading(token)
    }, 320)

    return () => {
      window.clearTimeout(timeoutId)
      stopLoading(token)
    }
  }, [location.pathname, startLoading, stopLoading])

  const value = useMemo(() => {
    const loadingKind = bootLoading
      ? 'boot'
      : pendingTokens.some((token) => token.startsWith('route:'))
        ? 'route'
        : pendingTokens.length
          ? 'page'
          : 'idle'

    return {
      isLoading: bootLoading || pendingTokens.length > 0,
      bootLoading,
      loadingKind,
      activeLoadCount: pendingTokens.length,
      startLoading,
      stopLoading,
      withPageLoader,
    }
  }, [bootLoading, pendingTokens, startLoading, stopLoading, withPageLoader])

  return <PageLoaderContext.Provider value={value}>{children}</PageLoaderContext.Provider>
}

export const usePageLoaderContext = () => {
  const context = useContext(PageLoaderContext)

  if (!context) {
    throw new Error('usePageLoaderContext must be used within PageLoaderProvider')
  }

  return context
}
