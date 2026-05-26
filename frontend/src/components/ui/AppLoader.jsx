import { useEffect, useMemo, useState } from 'react'
import { useI18n } from '@/hooks/useI18n'
import { usePageLoader } from '@/hooks/usePageLoader'

const COPY = {
  en: {
    route: {
      eyebrow: 'Cardora',
      title: 'Opening the next page',
      description: 'Just a moment while we load everything you need.',
      chips: ['Listings', 'Profiles', 'Orders'],
      progressLeft: 'Cardora',
      progressRight: 'Almost ready',
    },
    page: {
      eyebrow: 'Cardora',
      title: 'Getting things ready',
      description: 'We are loading the latest details for this page.',
      chips: ['Account', 'Orders', 'Marketplace'],
      progressLeft: 'Cardora',
      progressRight: 'Loading',
    },
    boot: {
      eyebrow: 'Cardora',
      title: 'Welcome to Cardora',
      description: 'We are preparing your experience.',
      chips: ['Collectibles', 'Profiles', 'Orders'],
      progressLeft: 'Cardora',
      progressRight: 'Starting',
    },
  },
  el: {
    route: {
      eyebrow: 'Cardora',
      title: '\u0391\u03bd\u03bf\u03af\u03b3\u03bf\u03c5\u03bc\u03b5 \u03c4\u03b7\u03bd \u03b5\u03c0\u03cc\u03bc\u03b5\u03bd\u03b7 \u03c3\u03b5\u03bb\u03af\u03b4\u03b1',
      description:
        '\u039c\u03b9\u03c3\u03cc \u03bb\u03b5\u03c0\u03c4\u03cc \u03bd\u03b1 \u03c6\u03bf\u03c1\u03c4\u03ce\u03c3\u03bf\u03c5\u03bc\u03b5 \u03cc\u03bb\u03b1 \u03cc\u03c3\u03b1 \u03c7\u03c1\u03b5\u03b9\u03ac\u03b6\u03b5\u03c3\u03b1\u03b9.',
      chips: [
        '\u0391\u03b3\u03b3\u03b5\u03bb\u03af\u03b5\u03c2',
        '\u03a0\u03c1\u03bf\u03c6\u03af\u03bb',
        '\u03a0\u03b1\u03c1\u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b5\u03c2',
      ],
      progressLeft: 'Cardora',
      progressRight: '\u03a3\u03b5 \u03bb\u03af\u03b3\u03bf \u03ad\u03c4\u03bf\u03b9\u03bc\u03bf',
    },
    page: {
      eyebrow: 'Cardora',
      title: '\u0395\u03c4\u03bf\u03b9\u03bc\u03ac\u03b6\u03bf\u03c5\u03bc\u03b5 \u03c4\u03b7 \u03c3\u03b5\u03bb\u03af\u03b4\u03b1',
      description:
        '\u03a6\u03bf\u03c1\u03c4\u03ce\u03bd\u03bf\u03c5\u03bc\u03b5 \u03c4\u03b1 \u03c0\u03b9\u03bf \u03c0\u03c1\u03cc\u03c3\u03c6\u03b1\u03c4\u03b1 \u03c3\u03c4\u03bf\u03b9\u03c7\u03b5\u03af\u03b1 \u03b3\u03b9\u03b1 \u03bd\u03b1 \u03c6\u03b1\u03bd\u03b5\u03af \u03bf\u03bb\u03bf\u03ba\u03bb\u03b7\u03c1\u03c9\u03bc\u03ad\u03bd\u03b1.',
      chips: [
        '\u039b\u03bf\u03b3\u03b1\u03c1\u03b9\u03b1\u03c3\u03bc\u03cc\u03c2',
        '\u03a0\u03b1\u03c1\u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b5\u03c2',
        'Marketplace',
      ],
      progressLeft: 'Cardora',
      progressRight: '\u03a6\u03cc\u03c1\u03c4\u03c9\u03c3\u03b7',
    },
    boot: {
      eyebrow: 'Cardora',
      title: '\u039a\u03b1\u03bb\u03ce\u03c2 \u03ae\u03c1\u03b8\u03b5\u03c2 \u03c3\u03c4\u03b7\u03bd Cardora',
      description:
        '\u0395\u03c4\u03bf\u03b9\u03bc\u03ac\u03b6\u03bf\u03c5\u03bc\u03b5 \u03c4\u03b7\u03bd \u03b5\u03bc\u03c0\u03b5\u03b9\u03c1\u03af\u03b1 \u03c3\u03bf\u03c5.',
      chips: [
        '\u03a3\u03c5\u03bb\u03bb\u03b5\u03ba\u03c4\u03b9\u03ba\u03ac',
        '\u03a0\u03c1\u03bf\u03c6\u03af\u03bb',
        '\u03a0\u03b1\u03c1\u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b5\u03c2',
      ],
      progressLeft: 'Cardora',
      progressRight: '\u039e\u03b5\u03ba\u03b9\u03bd\u03ac\u03bc\u03b5',
    },
  },
}

function AppLoader() {
  const { locale } = useI18n()
  const { isLoading, loadingKind } = usePageLoader()
  const [isMounted, setIsMounted] = useState(isLoading)

  useEffect(() => {
    if (isLoading) {
      setIsMounted(true)
      return undefined
    }

    const timeoutId = window.setTimeout(() => {
      setIsMounted(false)
    }, 420)

    return () => {
      window.clearTimeout(timeoutId)
    }
  }, [isLoading])

  useEffect(() => {
    if (!isMounted) {
      document.body.style.removeProperty('overflow')
      return undefined
    }

    document.body.style.overflow = 'hidden'

    return () => {
      document.body.style.removeProperty('overflow')
    }
  }, [isMounted])

  const copy = useMemo(() => {
    const language = locale === 'en' ? 'en' : 'el'
    const kind = loadingKind === 'route' || loadingKind === 'page' ? loadingKind : 'boot'

    return COPY[language][kind]
  }, [loadingKind, locale])

  if (!isMounted) return null

  return (
    <div
      className={`pointer-events-auto fixed inset-0 z-[140] transition-opacity duration-500 ${
        isLoading ? 'opacity-100' : 'opacity-0'
      }`}
      aria-hidden={!isLoading}
    >
      <div className="absolute inset-0 bg-[#040a15]" />
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(243,202,87,0.13),transparent_34%),radial-gradient(circle_at_80%_20%,rgba(71,106,169,0.16),transparent_30%),linear-gradient(180deg,rgba(3,10,19,0.82),rgba(3,9,20,0.98))]" />
      <div className="cardora-loader-grid absolute inset-0 opacity-45" />

      <div className="relative flex min-h-screen items-center justify-center px-5 py-10">
        <div className="relative w-full max-w-4xl overflow-hidden rounded-[36px] border border-gold-300/18 bg-[#081223]/92 p-7 shadow-[0_20px_120px_rgba(0,0,0,0.5)] backdrop-blur-2xl sm:p-10">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(243,202,87,0.12),transparent_28%),radial-gradient(circle_at_bottom_right,rgba(51,92,143,0.18),transparent_30%)]" />
          <div className="cardora-loader-shimmer absolute inset-y-0 -left-1/3 w-1/2 bg-gradient-to-r from-transparent via-white/10 to-transparent" />

          <div className="relative grid items-center gap-10 lg:grid-cols-[0.92fr,1.08fr]">
            <div className="relative flex min-h-[260px] items-center justify-center">
              <div className="cardora-loader-orbit absolute h-56 w-56 rounded-full border border-gold-300/18" />
              <div className="cardora-loader-orbit-delayed absolute h-72 w-72 rounded-full border border-white/8" />

              <div className="cardora-loader-card cardora-loader-card-back" />
              <div className="cardora-loader-card cardora-loader-card-mid" />
              <div className="cardora-loader-card cardora-loader-card-front">
                <img
                  src="/asset.php?f=logo.png"
                  alt="Cardora"
                  className="w-24 drop-shadow-[0_10px_20px_rgba(243,202,87,0.2)] sm:w-28"
                />
              </div>

              <div className="cardora-loader-glow absolute bottom-6 h-10 w-40 rounded-full bg-gold-300/20 blur-2xl" />
            </div>

            <div>
              <div className="inline-flex items-center rounded-full border border-gold-300/18 bg-gold-300/10 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.34em] text-gold-100">
                {copy.eyebrow}
              </div>
              <h1 className="mt-5 max-w-xl font-display text-5xl leading-[0.95] text-white sm:text-6xl">
                {copy.title}
              </h1>
              <p className="mt-5 max-w-xl text-sm leading-8 text-mist sm:text-[15px]">
                {copy.description}
              </p>

              <div className="mt-6 flex flex-wrap gap-2.5">
                {copy.chips.map((chip) => (
                  <span
                    key={chip}
                    className="rounded-full border border-white/10 bg-white/6 px-3 py-1.5 text-[11px] uppercase tracking-[0.24em] text-white/80"
                  >
                    {chip}
                  </span>
                ))}
              </div>

              <div className="mt-8 space-y-3">
                <div className="flex items-center justify-between text-[11px] uppercase tracking-[0.28em] text-white/55">
                  <span>{copy.progressLeft}</span>
                  <span>{copy.progressRight}</span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-white/8">
                  <div className="cardora-loader-progress h-full w-1/2 rounded-full bg-[linear-gradient(90deg,rgba(243,202,87,0.84),rgba(255,245,214,0.96),rgba(104,151,223,0.8))]" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default AppLoader
