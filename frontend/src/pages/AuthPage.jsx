import { CheckCircle2 } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Textarea } from '@/components/ui/Input'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { resolveApiUrl } from '@/services/apiClient'
import { toSlug } from '@/utils/helpers'

const initialForm = {
  name: '',
  displayName: '',
  handle: '',
  email: '',
  city: '',
  collectorTagline: '',
  favoriteCategories: ['cards'],
  password: '',
  passwordConfirmation: '',
  terms: true,
  privacy: true,
  marketing: false,
}

const GoogleMark = ({ className = '' }) => (
  <svg viewBox="0 0 24 24" aria-hidden="true" className={className}>
    <path
      d="M21.805 12.23c0-.71-.064-1.39-.184-2.045H12v3.873h5.5a4.702 4.702 0 0 1-2.04 3.085v2.563h3.303c1.934-1.78 3.042-4.404 3.042-7.476Z"
      fill="#4285F4"
    />
    <path
      d="M12 22c2.76 0 5.073-.914 6.763-2.473l-3.303-2.563c-.915.614-2.086.977-3.46.977-2.66 0-4.913-1.796-5.72-4.212H2.865v2.644A9.998 9.998 0 0 0 12 22Z"
      fill="#34A853"
    />
    <path
      d="M6.28 13.729A5.998 5.998 0 0 1 5.96 12c0-.6.108-1.183.32-1.73V7.626H2.865A9.998 9.998 0 0 0 2 12c0 1.613.386 3.137 1.065 4.374l3.214-2.645Z"
      fill="#FBBC05"
    />
    <path
      d="M12 6.06c1.5 0 2.846.516 3.907 1.53l2.93-2.93C17.067 3.034 14.754 2 12 2A9.998 9.998 0 0 0 2.865 7.626L6.28 10.27C7.087 7.856 9.34 6.06 12 6.06Z"
      fill="#EA4335"
    />
  </svg>
)

function AuthPage({ mode = 'login' }) {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { isAuthenticating, isAuthenticated, isAuthReady, login, register } = useAuth()
  const { locale } = useI18n()
  const [form, setForm] = useState(initialForm)
  const [errors, setErrors] = useState({})
  const [authError, setAuthError] = useState('')
  const [isGoogleRedirecting, setIsGoogleRedirecting] = useState(false)
  const [isCompletingRegisterRedirect, setIsCompletingRegisterRedirect] = useState(false)
  const [citySuggestions, setCitySuggestions] = useState([])
  const [showCitySuggestions, setShowCitySuggestions] = useState(false)
  const [isLoadingCitySuggestions, setIsLoadingCitySuggestions] = useState(false)
  const cityDebounceRef = useRef(null)
  const cityRequestAbortRef = useRef(null)
  const skipNextCityLookupRef = useRef(false)

  const isRegister = mode === 'register'
  const passwordResetSuccess = searchParams.get('reset') === '1'

  useEffect(() => {
    if (!isAuthReady || !isAuthenticated || isCompletingRegisterRedirect) return

    navigate('/profil', { replace: true })
  }, [isAuthReady, isAuthenticated, isCompletingRegisterRedirect, navigate])

  useEffect(() => {
    if (!isRegister) return

    const apiKey = String(import.meta.env.VITE_GOOGLE_MAPS_API_KEY ?? '').trim()
    const query = form.city.trim()

    if (!apiKey || query.length < 2) {
      setIsLoadingCitySuggestions(false)
      setCitySuggestions([])
      return
    }

    if (skipNextCityLookupRef.current) {
      skipNextCityLookupRef.current = false
      return
    }

    if (cityDebounceRef.current) {
      window.clearTimeout(cityDebounceRef.current)
    }
    if (cityRequestAbortRef.current) {
      cityRequestAbortRef.current.abort()
    }

    cityDebounceRef.current = window.setTimeout(async () => {
      const controller = new AbortController()
      cityRequestAbortRef.current = controller
      setIsLoadingCitySuggestions(true)

      try {
        const response = await fetch('https://places.googleapis.com/v1/places:autocomplete', {
          method: 'POST',
          signal: controller.signal,
          headers: {
            'Content-Type': 'application/json',
            'X-Goog-Api-Key': apiKey,
            'X-Goog-FieldMask': 'suggestions.placePrediction.text.text,suggestions.placePrediction.placeId',
          },
          body: JSON.stringify({
            input: query,
            languageCode: locale === 'en' ? 'en' : 'el',
            includedPrimaryTypes: ['locality', 'administrative_area_level_3'],
          }),
        })

        if (!response.ok) {
          setCitySuggestions([])
          return
        }

        const payload = await response.json()
        const suggestions =
          payload?.suggestions
            ?.map((item) => item?.placePrediction?.text?.text)
            .filter((value) => typeof value === 'string' && value.trim() !== '') ?? []

        setCitySuggestions([...new Set(suggestions)])
      } catch {
        setCitySuggestions([])
      } finally {
        if (!controller.signal.aborted) {
          setIsLoadingCitySuggestions(false)
        }
      }
    }, 250)

    return () => {
      if (cityDebounceRef.current) {
        window.clearTimeout(cityDebounceRef.current)
      }
      if (cityRequestAbortRef.current) {
        cityRequestAbortRef.current.abort()
      }
    }
  }, [isRegister, locale, form.city])

  const copy =
    locale === 'en'
      ? {
          tabs: { login: 'Login', register: 'Register' },
          sideBadge: isRegister ? 'Join Cardora' : 'Welcome back',
          sideTitle: isRegister ? 'Create a collector profile that feels like yours.' : 'Sign back in to Cardora.',
          sideText:
            isRegister
              ? 'Set up your nickname, public handle and account details so you can buy, sell and show your collection with confidence.'
              : 'Pick up where you left off and return to your saved items, listings, messages and collection profile.',
          sideCards: [
            {
              title: locale === 'en' ? 'A profile collectors remember' : '',
              text: locale === 'en' ? 'Your nickname and handle become the public face of your account inside the community.' : '',
            },
            {
              title: locale === 'en' ? 'Ready for safer transactions' : '',
              text: locale === 'en' ? 'Your account can later connect with verification, protected checkout and selling tools.' : '',
            },
          ],
          baseSection: 'Basic details',
          baseSectionText: 'These details shape your account and public collector profile.',
          identitySection: 'Collector profile',
          identitySectionText: 'A short introduction helps other members understand what you collect.',
          securitySection: 'Security & permissions',
          securitySectionText: 'Set your password and confirm the basic terms for using the marketplace.',
          fields: {
            fullName: 'Full name',
            nickname: 'Nickname',
            handle: 'Public handle',
            email: 'Email',
            city: 'City',
            tagline: 'Short profile intro',
            interests: 'Collecting interests',
            password: 'Password',
            passwordConfirmation: 'Confirm password',
          },
          placeholders: {
            fullName: 'For example: Andreas Maniatis',
            nickname: 'For example: AndreasVault',
            handle: 'For example: andreas-vault',
            email: 'collector@cardora.com',
            city: 'For example: Athens',
            tagline: 'For example: I collect graded Pokémon, Marvel keys and sealed collector pieces.',
            password: 'At least 8 characters',
            passwordConfirmation: 'Repeat your password',
          },
          remember: 'Remember me',
          forgot: 'Forgot password?',
          terms: 'I accept the terms of use and the protected payment model used by Cardora.',
          privacy: 'I accept the privacy policy and the use of my details for safer marketplace activity.',
          marketing: 'I would like to receive updates about new categories, articles, draws and featured collectibles.',
          createAccount: 'Create account',
          loggingIn: 'Signing in...',
          creating: 'Creating account...',
          signIn: 'Sign in',
          categories: [
            { key: 'cards', label: 'Cards' },
            { key: 'figures', label: 'Figures' },
            { key: 'comics', label: 'Comics & Books' },
            { key: 'misc', label: 'Other collectibles' },
          ],
          errors: {
            name: 'Please enter your full name.',
            displayName: 'Please choose the nickname that will appear publicly.',
            handle: 'A public handle is required for your profile.',
            email: 'Please enter your email.',
            city: 'Please enter your city.',
            tagline: 'Add a short profile intro.',
            password: 'Please enter a password.',
            passwordLength: 'Your password must be at least 8 characters long.',
            passwordConfirmation: 'The passwords do not match.',
            categories: 'Choose at least one collecting interest.',
            terms: 'You need to accept the terms of use.',
            privacy: 'You need to accept the privacy policy.',
          },
        }
      : {
          tabs: { login: 'Είσοδος', register: 'Εγγραφή' },
          sideBadge: isRegister ? 'Γίνε μέλος της Cardora' : 'Καλωσόρισες ξανά',
          sideTitle: isRegister ? 'Στήσε ένα collector profile που να σε εκφράζει.' : 'Συνδέσου ξανά στην Cardora.',
          sideText:
            isRegister
              ? 'Όρισε nickname, public handle και βασικά στοιχεία λογαριασμού ώστε να μπορείς να αγοράζεις, να πουλάς και να δείχνεις τη συλλογή σου με μεγαλύτερη σιγουριά.'
              : 'Συνέχισε από εκεί που σταμάτησες και γύρισε στα αγαπημένα, τις αγγελίες, τα μηνύματα και το συλλεκτικό σου προφίλ.',
          sideCards: [
            {
              title: 'Ένα προφίλ που μένει στο μυαλό',
              text: 'Το nickname και το handle γίνονται το δημόσιο πρόσωπο του λογαριασμού σου μέσα στην κοινότητα.',
            },
            {
              title: 'Έτοιμο για πιο ασφαλείς συναλλαγές',
              text: 'Ο λογαριασμός σου μπορεί αργότερα να συνδεθεί με επαλήθευση, protected checkout και seller εργαλεία.',
            },
          ],
          baseSection: 'Βασικά στοιχεία',
          baseSectionText: 'Αυτές οι πληροφορίες διαμορφώνουν το account και το δημόσιο collector profile σου.',
          identitySection: 'Collector profile',
          identitySectionText: 'Μια σύντομη περιγραφή βοηθά τα άλλα μέλη να καταλάβουν τι συλλέγεις.',
          securitySection: 'Ασφάλεια & όροι',
          securitySectionText: 'Όρισε τον κωδικό σου και επιβεβαίωσε τα βασικά που χρειάζονται για τη χρήση του marketplace.',
          fields: {
            fullName: 'Πλήρες όνομα',
            nickname: 'Nickname',
            handle: 'Public handle',
            email: 'Email',
            city: 'Πόλη',
            tagline: 'Σύντομο προφίλ',
            interests: 'Συλλεκτικά ενδιαφέροντα',
            password: 'Κωδικός',
            passwordConfirmation: 'Επιβεβαίωση κωδικού',
          },
          placeholders: {
            fullName: 'π.χ. Ανδρέας Μανιάτης',
            nickname: 'π.χ. AndreasVault',
            handle: 'π.χ. andreas-vault',
            email: 'collector@cardora.gr',
            city: 'π.χ. Αθήνα',
            tagline: 'π.χ. Συλλέγω graded Pokémon, Marvel keys και sealed collector pieces.',
            password: 'τουλάχιστον 8 χαρακτήρες',
            passwordConfirmation: 'επανάληψη κωδικού',
          },
          remember: 'Να με θυμάσαι',
          forgot: 'Ξέχασες τον κωδικό;',
          terms: 'Αποδέχομαι τους όρους χρήσης και το προστατευμένο μοντέλο πληρωμής που χρησιμοποιεί η Cardora.',
          privacy: 'Αποδέχομαι την πολιτική απορρήτου και τη χρήση των στοιχείων μου για ασφαλέστερη λειτουργία του marketplace.',
          marketing: 'Θέλω να λαμβάνω ενημερώσεις για νέες κατηγορίες, άρθρα, κληρώσεις και ξεχωριστά συλλεκτικά.',
          createAccount: 'Δημιουργία λογαριασμού',
          loggingIn: 'Γίνεται σύνδεση...',
          creating: 'Δημιουργία λογαριασμού...',
          signIn: 'Είσοδος',
          categories: [
            { key: 'cards', label: 'Κάρτες' },
            { key: 'figures', label: 'Φιγούρες' },
            { key: 'comics', label: 'Κόμικς & Βιβλία' },
            { key: 'misc', label: 'Άλλα συλλεκτικά' },
          ],
          errors: {
            name: 'Συμπλήρωσε το πλήρες όνομά σου.',
            displayName: 'Συμπλήρωσε το nickname που θα φαίνεται δημόσια.',
            handle: 'Χρειάζεται public handle για το προφίλ σου.',
            email: 'Συμπλήρωσε email.',
            city: 'Συμπλήρωσε πόλη.',
            tagline: 'Γράψε μια σύντομη collector περιγραφή.',
            password: 'Συμπλήρωσε κωδικό.',
            passwordLength: 'Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες.',
            passwordConfirmation: 'Οι κωδικοί δεν ταιριάζουν.',
            categories: 'Διάλεξε τουλάχιστον μία κατηγορία ενδιαφέροντος.',
            terms: 'Χρειάζεται αποδοχή όρων χρήσης.',
            privacy: 'Χρειάζεται αποδοχή πολιτικής απορρήτου.',
          },
        }

  const previewHandle = useMemo(
    () => form.handle || toSlug(form.displayName || form.name || 'collector'),
    [form.displayName, form.handle, form.name],
  )

  const updateField = (key, value) => {
    setForm((previous) => ({ ...previous, [key]: value }))
    setErrors((previous) => ({ ...previous, [key]: '' }))
    setAuthError('')
  }

  const toggleCategory = (category) => {
    setForm((previous) => {
      const exists = previous.favoriteCategories.includes(category)
      const nextCategories = exists
        ? previous.favoriteCategories.filter((item) => item !== category)
        : [...previous.favoriteCategories, category]

      return {
        ...previous,
        favoriteCategories: nextCategories.length ? nextCategories : [category],
      }
    })
  }

  const selectCitySuggestion = (value) => {
    skipNextCityLookupRef.current = true
    updateField('city', value)
    setCitySuggestions([])
    setShowCitySuggestions(false)
  }

  const validateRegister = () => {
    const nextErrors = {}

    if (!form.name.trim()) nextErrors.name = copy.errors.name
    if (!form.displayName.trim()) nextErrors.displayName = copy.errors.displayName
    if (!previewHandle) nextErrors.handle = copy.errors.handle
    if (!form.email.trim()) nextErrors.email = copy.errors.email
    if (!form.city.trim()) nextErrors.city = copy.errors.city
    if (!form.collectorTagline.trim()) nextErrors.collectorTagline = copy.errors.tagline
    if (!form.password.trim()) nextErrors.password = copy.errors.password
    if (form.password.length < 8) nextErrors.password = copy.errors.passwordLength
    if (form.password !== form.passwordConfirmation) {
      nextErrors.passwordConfirmation = copy.errors.passwordConfirmation
    }
    if (!form.favoriteCategories.length) {
      nextErrors.favoriteCategories = copy.errors.categories
    }
    if (!form.terms) nextErrors.terms = copy.errors.terms
    if (!form.privacy) nextErrors.privacy = copy.errors.privacy

    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setAuthError('')

    if (isRegister) {
      if (!validateRegister()) return

      setIsCompletingRegisterRedirect(true)

      try {
        const registeredUser = await register({
          name: form.name,
          display_name: form.displayName,
          handle: previewHandle,
          email: form.email,
          city: form.city,
          collector_tagline: form.collectorTagline,
          favorite_categories: form.favoriteCategories,
          password: form.password,
          password_confirmation: form.passwordConfirmation,
          locale,
          terms: form.terms,
          privacy: form.privacy,
          marketing: form.marketing,
        })
        const verificationSent = registeredUser?.verificationEmailSent !== false
        navigate(`/epivevaiosi-email?sent=${verificationSent ? '1' : '0'}&welcome=1`, {
          replace: true,
        })
      } catch (error) {
        setIsCompletingRegisterRedirect(false)
        setAuthError(
          error?.message ||
            (locale === 'en'
              ? 'Unable to create account right now.'
              : 'Δεν ήταν δυνατή η δημιουργία λογαριασμού.'),
        )
      }
    } else {
      try {
        await login({
          email: form.email,
          password: form.password,
        })
        navigate('/profil?activation=1')
      } catch (error) {
        setAuthError(
          error?.errors?.email?.[0] ||
            error?.message ||
            (locale === 'en' ? 'Email or password is incorrect.' : 'Λάθος email ή κωδικός.'),
        )
      }
    }
  }

  const handleGoogleAuth = () => {
    setAuthError('')
    setIsGoogleRedirecting(true)
    window.location.assign(resolveApiUrl('/auth/google/redirect'))
  }

  return (
    <div className="container pb-16">
      <div className="mx-auto max-w-[980px]">
        <CardSurface className="p-8 sm:p-10">
          {isRegister ? (
            <div className="mb-5">
              <Badge tone="gold">{copy.sideBadge}</Badge>
            </div>
          ) : null}

          <div className="mb-8 flex gap-3">
            <Link
              to="/eisodos"
              className={`rounded-full px-4 py-2 text-sm font-semibold ${!isRegister ? 'bg-gold-300/15 text-gold-100' : 'text-white/60'}`}
            >
              {copy.tabs.login}
            </Link>
            <Link
              to="/eggrafi"
              className={`rounded-full px-4 py-2 text-sm font-semibold ${isRegister ? 'bg-gold-300/15 text-gold-100' : 'text-white/60'}`}
            >
              {copy.tabs.register}
            </Link>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            {passwordResetSuccess ? (
              <div className="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                {locale === 'en'
                  ? 'Your password was updated successfully. You can now sign in with the new one.'
                  : 'Ο κωδικός σου ενημερώθηκε επιτυχώς. Μπορείς τώρα να συνδεθείς με τον νέο κωδικό.'}
              </div>
            ) : null}

            {authError ? (
              <div className="rounded-xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                {authError}
              </div>
            ) : null}

            <div className="space-y-3">
              <Button
                type="button"
                variant="secondary"
                size="lg"
                className="w-full border-white/15 bg-white/5"
                onClick={handleGoogleAuth}
                disabled={isAuthenticating || isGoogleRedirecting}
              >
                <GoogleMark className="h-4 w-4 shrink-0" />
                {isGoogleRedirecting
                  ? locale === 'en'
                    ? 'Redirecting to Google...'
                    : 'Μεταφορά στο Google...'
                  : locale === 'en'
                    ? 'Continue with Google'
                    : 'Συνέχεια με Google'}
              </Button>
              <div className="flex items-center gap-3">
                <span className="h-px flex-1 bg-white/12" />
                <span className="text-[11px] uppercase tracking-[0.24em] text-white/40">
                  {locale === 'en' ? 'or continue with email' : 'ή συνέχεια με email'}
                </span>
                <span className="h-px flex-1 bg-white/12" />
              </div>
            </div>

            {isRegister ? (
              <>
                <div className="rounded-[24px] border border-white/8 bg-white/5 p-4">
                  <div className="mb-4">
                    <p className="text-[11px] uppercase tracking-[0.3em] text-gold-100">{copy.baseSection}</p>
                    <p className="mt-2 text-sm text-mist">{copy.baseSectionText}</p>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="md:col-span-2">
                      <label className="mb-2 block text-sm text-mist">{copy.fields.fullName}</label>
                      <Input
                        value={form.name}
                        onChange={(event) => updateField('name', event.target.value)}
                        placeholder={copy.placeholders.fullName}
                      />
                      {errors.name ? <p className="mt-2 text-xs text-rose-200">{errors.name}</p> : null}
                    </div>

                    <div>
                      <label className="mb-2 block text-sm text-mist">{copy.fields.nickname}</label>
                      <Input
                        value={form.displayName}
                        onChange={(event) => updateField('displayName', event.target.value)}
                        placeholder={copy.placeholders.nickname}
                      />
                      {errors.displayName ? <p className="mt-2 text-xs text-rose-200">{errors.displayName}</p> : null}
                    </div>

                    <div>
                      <label className="mb-2 block text-sm text-mist">{copy.fields.handle}</label>
                      <Input
                        value={form.handle}
                        onChange={(event) => updateField('handle', toSlug(event.target.value))}
                        placeholder={copy.placeholders.handle}
                      />
                      <p className="mt-2 text-xs text-gold-100">cardora.gr/sylloges/{previewHandle}</p>
                      {errors.handle ? <p className="mt-2 text-xs text-rose-200">{errors.handle}</p> : null}
                    </div>

                    <div>
                      <label className="mb-2 block text-sm text-mist">{copy.fields.email}</label>
                      <Input
                        type="email"
                        value={form.email}
                        onChange={(event) => updateField('email', event.target.value)}
                        placeholder={copy.placeholders.email}
                      />
                      {errors.email ? <p className="mt-2 text-xs text-rose-200">{errors.email}</p> : null}
                    </div>

                    <div className="relative md:col-span-2">
                      <label className="mb-2 block text-sm text-mist">{copy.fields.city}</label>
                      <Input
                        value={form.city}
                        onChange={(event) => {
                          updateField('city', event.target.value)
                          setShowCitySuggestions(true)
                        }}
                        onFocus={() => {
                          if (citySuggestions.length) {
                            setShowCitySuggestions(true)
                          }
                        }}
                        onBlur={() => {
                          window.setTimeout(() => setShowCitySuggestions(false), 120)
                        }}
                        placeholder={copy.placeholders.city}
                        autoComplete="address-level2"
                      />
                      {showCitySuggestions && (citySuggestions.length > 0 || isLoadingCitySuggestions) ? (
                        <div className="absolute left-0 right-0 top-full z-[120] mt-2 overflow-hidden rounded-xl border border-white/12 bg-[#0c182b] shadow-2xl">
                          {isLoadingCitySuggestions ? (
                            <div className="px-3.5 py-2.5 text-xs text-white/60">
                              {locale === 'en' ? 'Searching cities...' : 'Αναζήτηση πόλεων...'}
                            </div>
                          ) : null}
                          {!isLoadingCitySuggestions
                            ? citySuggestions.slice(0, 6).map((suggestion) => (
                                <button
                                  key={suggestion}
                                  type="button"
                                  onMouseDown={() => selectCitySuggestion(suggestion)}
                                  className="block w-full border-0 border-b border-white/6 bg-transparent px-3.5 py-2.5 text-left text-sm text-white/85 transition hover:bg-white/8 last:border-b-0"
                                >
                                  {suggestion}
                                </button>
                              ))
                            : null}
                        </div>
                      ) : null}
                      {errors.city ? <p className="mt-2 text-xs text-rose-200">{errors.city}</p> : null}
                    </div>
                  </div>
                </div>

                <div className="rounded-[24px] border border-white/8 bg-white/5 p-4">
                  <div className="mb-4">
                    <p className="text-[11px] uppercase tracking-[0.3em] text-gold-100">{copy.identitySection}</p>
                    <p className="mt-2 text-sm text-mist">{copy.identitySectionText}</p>
                  </div>

                  <div className="space-y-4">
                    <div>
                      <label className="mb-2 block text-sm text-mist">{copy.fields.tagline}</label>
                      <Textarea
                        className="min-h-[110px]"
                        value={form.collectorTagline}
                        onChange={(event) => updateField('collectorTagline', event.target.value)}
                        placeholder={copy.placeholders.tagline}
                      />
                      {errors.collectorTagline ? (
                        <p className="mt-2 text-xs text-rose-200">{errors.collectorTagline}</p>
                      ) : null}
                    </div>

                    <div>
                      <label className="mb-3 block text-sm text-mist">{copy.fields.interests}</label>
                      <div className="grid gap-3 sm:grid-cols-2">
                        {copy.categories.map((category) => {
                          const active = form.favoriteCategories.includes(category.key)

                          return (
                            <button
                              key={category.key}
                              type="button"
                              onClick={() => toggleCategory(category.key)}
                              className={`rounded-2xl border px-4 py-3 text-left text-sm transition ${
                                active
                                  ? 'border-gold-300/30 bg-gold-300/12 text-gold-50'
                                  : 'border-white/10 bg-white/4 text-white/75 hover:border-white/20 hover:bg-white/6'
                              }`}
                            >
                              <div className="flex items-center justify-between gap-3">
                                <span className="font-semibold">{category.label}</span>
                                {active ? <CheckCircle2 className="h-4 w-4 text-gold-100" /> : null}
                              </div>
                            </button>
                          )
                        })}
                      </div>
                      {errors.favoriteCategories ? (
                        <p className="mt-2 text-xs text-rose-200">{errors.favoriteCategories}</p>
                      ) : null}
                    </div>
                  </div>
                </div>
              </>
            ) : null}

            {!isRegister ? (
              <>
                <div>
                  <label className="mb-2 block text-sm text-mist">{copy.fields.email}</label>
                  <Input
                    type="email"
                    value={form.email}
                    onChange={(event) => updateField('email', event.target.value)}
                    placeholder={copy.placeholders.email}
                  />
                </div>
                <div>
                  <label className="mb-2 block text-sm text-mist">{copy.fields.password}</label>
                  <Input
                    type="password"
                    value={form.password}
                    onChange={(event) => updateField('password', event.target.value)}
                    placeholder="••••••••"
                  />
                </div>
              </>
            ) : null}

            {isRegister ? (
              <div className="rounded-[24px] border border-white/8 bg-white/5 p-4">
                <div className="mb-4">
                  <p className="text-[11px] uppercase tracking-[0.3em] text-gold-100">{copy.securitySection}</p>
                  <p className="mt-2 text-sm text-mist">{copy.securitySectionText}</p>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <label className="mb-2 block text-sm text-mist">{copy.fields.password}</label>
                    <Input
                      type="password"
                      value={form.password}
                      onChange={(event) => updateField('password', event.target.value)}
                      placeholder={copy.placeholders.password}
                    />
                    {errors.password ? <p className="mt-2 text-xs text-rose-200">{errors.password}</p> : null}
                  </div>

                  <div>
                    <label className="mb-2 block text-sm text-mist">{copy.fields.passwordConfirmation}</label>
                    <Input
                      type="password"
                      value={form.passwordConfirmation}
                      onChange={(event) => updateField('passwordConfirmation', event.target.value)}
                      placeholder={copy.placeholders.passwordConfirmation}
                    />
                    {errors.passwordConfirmation ? (
                      <p className="mt-2 text-xs text-rose-200">{errors.passwordConfirmation}</p>
                    ) : null}
                  </div>
                </div>

                <div className="mt-4 space-y-3">
                  <label className="flex items-start gap-3 rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm text-white/80">
                    <input
                      type="checkbox"
                      checked={form.terms}
                      onChange={(event) => updateField('terms', event.target.checked)}
                      className="mt-1 rounded border-white/20 bg-transparent"
                    />
                    <span>{copy.terms}</span>
                  </label>
                  <p className="pl-1 text-xs text-mist">
                    <Link to="/oroi-xrisis" className="transition hover:text-gold-100">
                      {locale === 'en' ? 'Read the Terms of Use' : 'Διάβασε τους Όρους Χρήσης'}
                    </Link>
                  </p>
                  {errors.terms ? <p className="text-xs text-rose-200">{errors.terms}</p> : null}

                  <label className="flex items-start gap-3 rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm text-white/80">
                    <input
                      type="checkbox"
                      checked={form.privacy}
                      onChange={(event) => updateField('privacy', event.target.checked)}
                      className="mt-1 rounded border-white/20 bg-transparent"
                    />
                    <span>{copy.privacy}</span>
                  </label>
                  {errors.privacy ? <p className="text-xs text-rose-200">{errors.privacy}</p> : null}

                  <label className="flex items-start gap-3 rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm text-white/80">
                    <input
                      type="checkbox"
                      checked={form.marketing}
                      onChange={(event) => updateField('marketing', event.target.checked)}
                      className="mt-1 rounded border-white/20 bg-transparent"
                    />
                    <span>{copy.marketing}</span>
                  </label>
                </div>
              </div>
            ) : (
              <div className="flex items-center justify-between text-sm text-mist">
                <label className="flex items-center gap-2">
                  <input type="checkbox" defaultChecked className="rounded border-white/20 bg-transparent" />
                  {copy.remember}
                </label>
                <Link to="/xechasa-kodiko" className="transition hover:text-gold-100">
                  {copy.forgot}
                </Link>
              </div>
            )}

            <Button type="submit" className="w-full" size="lg" disabled={isAuthenticating}>
              {isAuthenticating
                ? isRegister
                  ? copy.creating
                  : copy.loggingIn
                : isRegister
                  ? copy.createAccount
                  : copy.signIn}
            </Button>
          </form>
        </CardSurface>
      </div>
    </div>
  )
}

export default AuthPage

