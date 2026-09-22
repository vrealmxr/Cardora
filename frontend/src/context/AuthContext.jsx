import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { cardoraService } from '@/services/cardoraService'
import {
  AUTH_EXPIRED_EVENT,
  clearStoredAuthToken,
  getStoredAuthToken,
  setStoredAuthToken,
} from '@/services/apiClient'
import { toSlug } from '@/utils/helpers'
import { normalizeNotificationPreferences } from '@/utils/notificationPreferences'

const AuthContext = createContext(null)

const isInvalidTokenError = (error) => Number(error?.status ?? 0) === 401

const hydrateAuthUser = (user) => {
  if (!user) return null

  const favoriteCategories = Array.isArray(user.favoriteCategories)
    ? user.favoriteCategories
    : Array.isArray(user.favorite_categories)
      ? user.favorite_categories
      : []

  const recentActivity = Array.isArray(user.recentActivity)
    ? user.recentActivity
    : Array.isArray(user.recent_activity)
      ? user.recent_activity
      : []

  const listedItems = Number(user.listedItems ?? user.listed_items ?? user.listings_count ?? 0)
  const soldItems = Number(user.soldItems ?? user.sold_items ?? user.salesCount ?? user.sales_count ?? 0)
  const purchasedItems = Number(
    user.purchasedItems ?? user.purchased_items ?? user.purchaseCount ?? user.purchase_count ?? 0,
  )
  const notificationPreferences = normalizeNotificationPreferences(
    user.notificationPreferences ?? user.notification_preferences,
  )

  return {
    ...user,
    name: user.name ?? user.display_name ?? '',
    displayName:
      user.displayName ??
      user.display_name ??
      user.nickname ??
      user.handle ??
      user.name,
    handle: user.handle ?? toSlug(user.display_name ?? user.name ?? 'collector'),
    favoriteCategories,
    notificationPreferences,
    recentActivity,
    bio: user.bio ?? user.collector_tagline ?? '',
    city: user.city ?? '',
    emailVerifiedAt: user.emailVerifiedAt ?? user.email_verified_at ?? null,
    emailVerified: Boolean(user.emailVerified ?? user.email_verified ?? user.email_verified_at),
    trustStatus: user.trustStatus ?? user.trust_status ?? '',
    responseTime: user.responseTime ?? user.response_time ?? '',
    rating: Number(user.rating ?? 0),
    listedItems,
    soldItems,
    purchasedItems,
    verified: user.verified ?? user.is_verified_seller ?? false,
    salesCount: user.salesCount ?? user.sales_count ?? 0,
    purchaseCount: user.purchaseCount ?? user.purchase_count ?? 0,
    stripeConnect:
      user.stripeConnect ??
      user.stripe_connect ?? {
        stripe_account_id: null,
        onboarding_completed: false,
        charges_enabled: false,
        payouts_enabled: false,
        details_submitted: false,
      },
    marketplaceAccess: user.marketplaceAccess ?? user.marketplace_access ?? null,
    isPro: Boolean(user.isPro ?? user.is_pro ?? (user.plan === 'pro')),
    plan: user.plan ?? (user.isPro || user.is_pro ? 'pro' : 'free'),
    pro: user.pro ?? {
      status: null,
      currentPeriodEnd: null,
      cancelAtPeriodEnd: false,
      trialAvailable: true,
    },
  }
}

export function AuthProvider({ children }) {
  const [currentUser, setCurrentUser] = useState(null)
  const [isAuthenticating, setIsAuthenticating] = useState(false)
  const [isAuthReady, setIsAuthReady] = useState(false)

  useEffect(() => {
    const bootstrapAuth = async () => {
      const token = getStoredAuthToken()

      if (!token) {
        setIsAuthReady(true)
        return
      }

      setIsAuthenticating(true)

      try {
        const user = await cardoraService.me()
        setCurrentUser(hydrateAuthUser(user))
      } catch (error) {
        if (isInvalidTokenError(error)) {
          clearStoredAuthToken()
          setCurrentUser(null)
        }
      } finally {
        setIsAuthenticating(false)
        setIsAuthReady(true)
      }
    }

    bootstrapAuth()
  }, [])

  useEffect(() => {
    if (typeof window === 'undefined') return undefined

    const handleAuthExpired = () => {
      clearStoredAuthToken()
      setCurrentUser(null)
      setIsAuthenticating(false)
      setIsAuthReady(true)
    }

    window.addEventListener(AUTH_EXPIRED_EVENT, handleAuthExpired)

    return () => {
      window.removeEventListener(AUTH_EXPIRED_EVENT, handleAuthExpired)
    }
  }, [])

  const login = useCallback(async (credentials) => {
    setIsAuthenticating(true)

    try {
      const { token, user } = await cardoraService.login(credentials)
      setStoredAuthToken(token)
      const hydratedUser = hydrateAuthUser(user)
      setCurrentUser(hydratedUser)
      return hydratedUser
    } finally {
      setIsAuthenticating(false)
      setIsAuthReady(true)
    }
  }, [])

  const register = useCallback(async (payload) => {
    setIsAuthenticating(true)

    try {
      const { token, user, verificationEmailSent } = await cardoraService.register(payload)
      setStoredAuthToken(token)
      const hydratedUser = hydrateAuthUser(user)
      setCurrentUser(hydratedUser)
      return {
        ...hydratedUser,
        verificationEmailSent,
      }
    } finally {
      setIsAuthenticating(false)
      setIsAuthReady(true)
    }
  }, [])

  const authenticateWithToken = useCallback(async (token) => {
    if (!token) {
      throw new Error('Missing authentication token.')
    }

    setIsAuthenticating(true)
    setStoredAuthToken(token)

    try {
      const user = await cardoraService.me()
      const hydratedUser = hydrateAuthUser(user)
      setCurrentUser(hydratedUser)
      return hydratedUser
    } catch (error) {
      if (isInvalidTokenError(error)) {
        clearStoredAuthToken()
        setCurrentUser(null)
      }
      throw error
    } finally {
      setIsAuthenticating(false)
      setIsAuthReady(true)
    }
  }, [])

  const logout = useCallback(async () => {
    try {
      await cardoraService.logout()
    } catch (error) {
      // Clear local auth even if the token is already invalid on the backend.
    } finally {
      clearStoredAuthToken()
      setCurrentUser(null)
    }
  }, [])

  const refreshCurrentUser = useCallback(async () => {
    if (!getStoredAuthToken()) {
      setCurrentUser(null)
      return null
    }

    try {
      const user = await cardoraService.me()
      const hydratedUser = hydrateAuthUser(user)
      setCurrentUser(hydratedUser)
      return hydratedUser
    } catch (error) {
      if (isInvalidTokenError(error)) {
        clearStoredAuthToken()
        setCurrentUser(null)
        return null
      }

      throw error
    }
  }, [])

  const contextValue = useMemo(
    () => ({
      currentUser,
      isAuthenticated: Boolean(currentUser),
      isAuthenticating,
      isAuthReady,
      login,
      register,
      authenticateWithToken,
      logout,
      refreshCurrentUser,
      setCurrentUser,
    }),
    [
      currentUser,
      isAuthenticating,
      isAuthReady,
      login,
      register,
      authenticateWithToken,
      logout,
      refreshCurrentUser,
    ],
  )

  return (
    <AuthContext.Provider value={contextValue}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuthContext = () => {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuthContext must be used within AuthProvider')
  }

  return context
}
