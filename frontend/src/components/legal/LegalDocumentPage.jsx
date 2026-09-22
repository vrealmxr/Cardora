import {
  BadgeAlert,
  Building2,
  FileClock,
  FileSearch2,
  Gavel,
  Globe2,
  LockKeyhole,
  MessageSquareWarning,
  PackageSearch,
  ScrollText,
  ShieldCheck,
  Truck,
  UserRoundCheck,
  WalletCards,
} from 'lucide-react'
import { Link } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useSeo } from '@/context/SeoContext'
import { useI18n } from '@/hooks/useI18n'

const ICONS = {
  general: ScrollText,
  privacy: LockKeyhole,
  rights: UserRoundCheck,
  legal: Gavel,
  company: Building2,
  payments: WalletCards,
  shipping: Truck,
  moderation: MessageSquareWarning,
  security: ShieldCheck,
  retention: FileClock,
  transfers: Globe2,
  disputes: BadgeAlert,
  prohibited: PackageSearch,
  reporting: FileSearch2,
}

function LegalDocumentPage({ document, pageKey }) {
  const { locale } = useI18n()
  const copy = document[locale] ?? document.el
  const seo = useSeo(pageKey)

  return (
    <div className="container pb-16">
      {pageKey ? (
        <PageSeo pageKey={pageKey} fallbackTitle={copy.title} fallbackDescription={copy.description} />
      ) : null}
      <SectionHeader
        eyebrow={copy.eyebrow}
        title={seo?.h1 || copy.title}
        description={copy.description}
      />

      <CardSurface className="mt-6">
        <div className="grid gap-6 xl:grid-cols-[1.2fr,0.8fr]">
          <div>
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.updated}</p>
            <p className="mt-5 text-sm leading-8 text-mist">{copy.intro}</p>
            {copy.introSecondary ? <p className="mt-4 text-sm leading-8 text-mist">{copy.introSecondary}</p> : null}
          </div>

          <div className="rounded-[24px] border border-gold-200/30 bg-white p-5">
            <div className="flex items-center gap-2">
              <Building2 className="h-4 w-4 text-gold-100" />
              <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.companyTitle}</p>
            </div>
            <div className="mt-4 space-y-3">
              {copy.companyRows.map(([label, value]) => (
                <div key={label} className="rounded-2xl border border-gold-200/25 bg-white px-4 py-3">
                  <p className="text-[11px] uppercase tracking-[0.24em] text-mist">{label}</p>
                  <p className="mt-2 text-sm leading-7 text-ink">{value}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </CardSurface>

      {copy.principles?.length ? (
        <section className="mt-8">
          <SectionHeader eyebrow={copy.principlesEyebrow} title={copy.principlesTitle} description={copy.principlesDescription} />
          <div className="grid gap-5 lg:grid-cols-2">
            {copy.principles.map((item) => (
              <CardSurface key={item.title}>
                <h2 className="font-display text-3xl text-ink">{item.title}</h2>
                <p className="mt-4 text-sm leading-8 text-mist">{item.text}</p>
              </CardSurface>
            ))}
          </div>
        </section>
      ) : null}

      <section className="mt-8 space-y-5">
        {copy.sections.map((section) => {
          const Icon = ICONS[section.icon] || ScrollText
          return (
            <CardSurface key={section.id}>
              <div className="flex items-start gap-4">
                <div className="mt-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-gold-200/20 bg-gold-200/10 text-gold-100">
                  <Icon className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                  <h2 className="font-display text-4xl text-ink">{section.title}</h2>
                  <div className="mt-4 space-y-4">
                    {section.paragraphs?.map((paragraph) => (
                      <p key={paragraph} className="text-sm leading-8 text-mist">
                        {paragraph}
                      </p>
                    ))}
                    {section.bullets?.length ? (
                      <div className="space-y-3">
                        {section.bullets.map((bullet) => (
                          <div
                            key={bullet}
                            className="rounded-2xl border border-gold-200/25 bg-white px-4 py-3 text-sm leading-7 text-mist"
                          >
                            {bullet}
                          </div>
                        ))}
                      </div>
                    ) : null}
                    {section.notice ? (
                      <div className="rounded-2xl border border-gold-200/25 bg-gold-200/10 px-4 py-3 text-sm leading-7 text-gold-50">
                        {section.notice}
                      </div>
                    ) : null}
                  </div>
                </div>
              </div>
            </CardSurface>
          )
        })}
      </section>

      {copy.related?.length ? (
        <section className="mt-10">
          <SectionHeader eyebrow={copy.relatedEyebrow} title={copy.relatedTitle} description={copy.relatedDescription} />
          <div className="grid gap-4 md:grid-cols-3">
            {copy.related.map((item) => (
              <CardSurface key={item.to}>
                <Badge tone="gold">{item.badge}</Badge>
                <h3 className="mt-4 font-display text-3xl text-ink">{item.title}</h3>
                <p className="mt-3 text-sm leading-7 text-mist">{item.text}</p>
                <Button as={Link} to={item.to} variant="secondary" className="mt-5">
                  {item.cta}
                </Button>
              </CardSurface>
            ))}
          </div>
        </section>
      ) : null}

      <CardSurface className="mt-10">
        <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.ctaEyebrow}</p>
            <h2 className="mt-3 font-display text-4xl text-ink">{copy.ctaTitle}</h2>
            <p className="mt-4 max-w-3xl text-sm leading-8 text-mist">{copy.ctaText}</p>
          </div>
          <div className="flex flex-wrap gap-3">
            <Button as={Link} to={copy.ctaPrimaryTo}>{copy.ctaPrimary}</Button>
            <Button as={Link} to={copy.ctaSecondaryTo} variant="secondary">{copy.ctaSecondary}</Button>
          </div>
        </div>
      </CardSurface>
    </div>
  )
}

export default LegalDocumentPage
