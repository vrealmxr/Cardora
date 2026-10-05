import { ArrowLeft, ArrowUpRight, Check, Lightbulb, ListChecks, ShieldCheck } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { getTutorial } from '@/data/tutorials'
import { useI18n } from '@/hooks/useI18n'
import NotFoundPage from '@/pages/NotFoundPage'
import { cn, localizePath } from '@/utils/helpers'

const pad = (value) => String(value).padStart(2, '0')

function TutorialPage() {
  const { slug } = useParams()
  const { locale } = useI18n()
  const lang = locale === 'en' ? 'en' : 'el'
  const tutorial = getTutorial(slug)
  const [activeStep, setActiveStep] = useState(0)

  useEffect(() => {
    if (!tutorial) return undefined

    const elements = tutorial.steps.map((_, index) => document.getElementById(`step-${index + 1}`)).filter(Boolean)
    if (!elements.length) return undefined

    let frame = 0
    const update = () => {
      frame = 0
      const line = Math.max(window.innerHeight * 0.3, 140)
      let current = 0
      elements.forEach((element, index) => {
        if (element.getBoundingClientRect().top <= line) current = index
      })
      setActiveStep(current)
    }
    const onScroll = () => {
      if (!frame) frame = window.requestAnimationFrame(update)
    }

    update()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)
    return () => {
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
      if (frame) window.cancelAnimationFrame(frame)
    }
  }, [tutorial])

  const copy =
    lang === 'en'
      ? {
          eyebrow: 'Tutorials',
          back: 'All tutorials',
          steps: 'steps',
          start: 'Start the guide',
          openSetup: 'Open Stripe setup',
          journey: 'The process at a glance',
          needs: 'What you will need',
          stepLabel: 'Step',
          action: 'What to do',
          onThisPage: 'Steps',
          noteTitle: 'Good to know',
          helpTitle: 'Stuck on a step?',
          helpText: 'Our support team can help you finish onboarding and activate payouts.',
          support: 'Open Support Center',
          done: 'Finished? Head back to your profile to check your Stripe status.',
          profile: 'Go to my profile',
        }
      : {
          eyebrow: 'Tutorials',
          back: 'Όλα τα tutorials',
          steps: 'βήματα',
          start: 'Ξεκίνα τον οδηγό',
          openSetup: 'Άνοιγμα Stripe setup',
          journey: 'Η διαδικασία με μια ματιά',
          needs: 'Τι θα χρειαστείς',
          stepLabel: 'Βήμα',
          action: 'Τι κάνεις',
          onThisPage: 'Βήματα',
          noteTitle: 'Καλό είναι να ξέρεις',
          helpTitle: 'Κόλλησες σε κάποιο βήμα;',
          helpText: 'Η ομάδα υποστήριξης μπορεί να σε βοηθήσει να ολοκληρώσεις το onboarding και να ενεργοποιήσεις τα payouts.',
          support: 'Άνοιγμα Κέντρου Υποστήριξης',
          done: 'Τελείωσες; Γύρνα στο προφίλ σου για να δεις την κατάσταση του Stripe.',
          profile: 'Μετάβαση στο προφίλ μου',
        }

  const phaseForStep = useMemo(() => {
    if (!tutorial) return []
    return tutorial.steps.map((_, index) => {
      let phase = 0
      tutorial.phaseStart.forEach((start, phaseIndex) => {
        if (index >= start) phase = phaseIndex
      })
      return phase
    })
  }, [tutorial])

  if (!tutorial) return <NotFoundPage />

  const item = tutorial.copy[lang]
  const currentPhase = phaseForStep[activeStep] ?? 0

  return (
    <div className="container pb-16">
      <PageSeo pageKey={`tutorial.${tutorial.slug}`} fallbackTitle={`${item.title} | Cardora`} fallbackDescription={item.description} />

      <nav className="mb-5 flex flex-wrap items-center gap-2 text-sm text-mist" aria-label="Breadcrumb">
        <Link to={localizePath('/tutorials', locale)} className="inline-flex items-center gap-1.5 transition hover:text-[#8b6a2f]">
          <ArrowLeft className="h-3.5 w-3.5" />
          {copy.back}
        </Link>
      </nav>

      <CardSurface hover={false} className="relative overflow-hidden !p-0">
        <div className="h-1.5 w-full bg-[linear-gradient(90deg,rgba(212,170,92,0.82),rgba(250,240,214,0.96),rgba(231,211,171,0.84))]" />
        <div className="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1.1fr,0.9fr] lg:items-center">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="gold">{item.audienceLabel}</Badge>
              <Badge tone="muted">
                <ListChecks className="mr-1 h-3 w-3" />
                {tutorial.steps.length} {copy.steps}
              </Badge>
            </div>
            <h1 className="mt-5 font-display text-4xl leading-[1.02] text-ink sm:text-5xl">{item.title}</h1>
            <p className="mt-4 max-w-2xl text-sm leading-8 text-mist sm:text-[15px]">{item.description}</p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Button as="a" href="#step-1">
                {copy.start}
              </Button>
              <Button as={Link} to={localizePath(tutorial.cta.href, locale)} variant="secondary">
                {copy.openSetup}
                <ArrowUpRight className="h-4 w-4" />
              </Button>
            </div>
          </div>

          <div className="rounded-[24px] border border-[#eadab7] bg-[#fffaf0] p-5">
            <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#8d7a58]">{copy.needs}</p>
            <ul className="mt-4 space-y-3">
              {item.needs.map((need) => (
                <li key={need} className="flex items-start gap-3 text-sm leading-6 text-ink">
                  <span className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#f1e2bd] text-[#8b6a2f]">
                    <Check className="h-3 w-3" />
                  </span>
                  {need}
                </li>
              ))}
            </ul>
          </div>
        </div>
      </CardSurface>

      <div className="mt-8">
        <p className="mb-3 text-[11px] font-semibold uppercase tracking-[0.28em] text-[#8d7a58]">{copy.journey}</p>
        <div className="flex flex-wrap gap-2.5">
          {item.phases.map((phase, index) => (
            <span
              key={phase}
              className={cn(
                'rounded-full border px-3.5 py-2 text-xs font-semibold transition',
                index === currentPhase
                  ? 'border-[#d4aa5c] bg-[#fbf0d6] text-[#5a3a13] shadow-[0_6px_16px_rgba(199,157,98,0.2)]'
                  : 'border-[#eadab7] bg-white text-[#6d5b3d]',
              )}
            >
              <span className="mr-2 text-[#b8963f]">{index + 1}</span>
              {phase}
            </span>
          ))}
        </div>
      </div>

      <div className="mt-10 grid gap-8 lg:grid-cols-[250px,1fr]">
        <aside className="hidden lg:block">
          <div className="sticky top-28 rounded-[24px] border border-[#ead7ae] bg-white p-4 shadow-[0_12px_30px_rgba(193,164,111,0.1)]">
            <p className="px-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-[#8d7a58]">{copy.onThisPage}</p>
            <ol className="mt-3 max-h-[60vh] space-y-1 overflow-y-auto pr-1">
              {tutorial.steps.map((step, index) => (
                <li key={step[lang].title}>
                  <a
                    href={`#step-${index + 1}`}
                    className={cn(
                      'flex items-start gap-2.5 rounded-xl px-2 py-2 text-[13px] leading-5 transition',
                      index === activeStep
                        ? 'bg-[#fbf0d6] font-semibold text-[#5a3a13]'
                        : 'text-mist hover:bg-[#fff8e8] hover:text-[#5a3a13]',
                    )}
                  >
                    <span className="mt-px w-5 shrink-0 text-[11px] font-bold text-[#b8963f]">{pad(index + 1)}</span>
                    <span>{step[lang].title}</span>
                  </a>
                </li>
              ))}
            </ol>
          </div>
        </aside>

        <div className="space-y-8">
          {tutorial.steps.map((step, index) => (
            <section key={step[lang].title} id={`step-${index + 1}`} data-index={index} className="scroll-mt-28">
              <CardSurface hover={false} className="!p-5 sm:!p-7">
                <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#b8963f]">
                  {copy.stepLabel} {pad(index + 1)}
                </p>
                <h2 className="mt-2 font-display text-3xl leading-tight text-ink sm:text-4xl">{step[lang].title}</h2>

                <a
                  href={step.image}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-5 block overflow-hidden rounded-[20px] border border-[#ead7ae] bg-[#fbf7ef] shadow-[0_10px_26px_rgba(193,164,111,0.1)]"
                >
                  <img
                    src={step.image}
                    alt={step[lang].title}
                    width={index < 2 ? 1400 : 1180}
                    height={index < 2 ? 670 : 912}
                    loading={index < 2 ? 'eager' : 'lazy'}
                    className="w-full"
                  />
                </a>

                <div className="mt-5 rounded-[20px] border border-[#e9d8ae] bg-[linear-gradient(135deg,#fffaf0,#fbf1dc)] p-5">
                  <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#b8963f]">{copy.action}</p>
                  <p className="mt-2 text-sm leading-7 text-ink sm:text-[15px] sm:leading-8">{step[lang].body}</p>
                </div>
              </CardSurface>
            </section>
          ))}

          <CardSurface hover={false} className="bg-[#fffaf0]">
            <div className="flex items-start gap-3">
              <Lightbulb className="mt-1 h-5 w-5 shrink-0 text-[#b8963f]" />
              <div>
                <h2 className="font-display text-2xl text-ink">{copy.noteTitle}</h2>
                <p className="mt-2 text-sm leading-7 text-mist">{item.note}</p>
              </div>
            </div>
          </CardSurface>

          <CardSurface hover={false}>
            <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
              <div className="flex items-start gap-3">
                <ShieldCheck className="mt-1 h-5 w-5 shrink-0 text-[#b8963f]" />
                <div>
                  <h2 className="font-display text-2xl text-ink">{copy.helpTitle}</h2>
                  <p className="mt-1 text-sm leading-7 text-mist">{copy.helpText}</p>
                  <p className="mt-1 text-sm leading-7 text-mist">{copy.done}</p>
                </div>
              </div>
              <div className="flex flex-wrap gap-3">
                <Button as={Link} to={localizePath('/kentro-ypostiriksis', locale)} variant="secondary">
                  {copy.support}
                </Button>
                <Button as={Link} to={localizePath(tutorial.cta.href, locale)}>
                  {copy.profile}
                </Button>
              </div>
            </div>
          </CardSurface>
        </div>
      </div>
    </div>
  )
}

export default TutorialPage
