import { ArrowRight, ListChecks } from 'lucide-react'
import { Link } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { tutorials } from '@/data/tutorials'
import { useSeo } from '@/context/SeoContext'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function TutorialsPage() {
  const { locale } = useI18n()
  const seo = useSeo('tutorials')
  const lang = locale === 'en' ? 'en' : 'el'

  const copy =
    lang === 'en'
      ? {
          eyebrow: 'Tutorials',
          title: 'Step-by-step guides for using Cardora',
          description:
            'Practical, illustrated walkthroughs for sellers and collectors — from setting up payouts to getting the most out of the marketplace.',
          steps: 'steps',
          open: 'Open guide',
          soonBadge: 'Coming soon',
          soonTitle: 'More guides are on the way',
          soonText:
            'We are adding new tutorials covering listings, orders and the Binder. Need something explained sooner? Tell us.',
          contact: 'Contact us',
          seoTitle: 'Tutorials & Seller Guides | Cardora',
        }
      : {
          eyebrow: 'Tutorials',
          title: 'Οδηγοί βήμα-βήμα για να χρησιμοποιείς το Cardora',
          description:
            'Πρακτικοί οδηγοί με εικόνες για πωλητές και συλλέκτες — από τη ρύθμιση των payouts μέχρι το πώς θα αξιοποιήσεις στο έπακρο το marketplace.',
          steps: 'βήματα',
          open: 'Άνοιγμα οδηγού',
          soonBadge: 'Σύντομα',
          soonTitle: 'Έρχονται κι άλλοι οδηγοί',
          soonText:
            'Προσθέτουμε νέα tutorials για αγγελίες, παραγγελίες και το Binder. Θέλεις κάτι να εξηγηθεί νωρίτερα; Πες μας.',
          contact: 'Επικοινωνία',
          seoTitle: 'Tutorials & Οδηγοί Πωλητή | Cardora',
        }

  return (
    <div className="container pb-16">
      <PageSeo pageKey="tutorials" fallbackTitle={copy.seoTitle} fallbackDescription={copy.description} />
      <SectionHeader eyebrow={copy.eyebrow} title={seo?.h1 || copy.title} description={copy.description} />

      <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        {tutorials.map((tutorial) => {
          const item = tutorial.copy[lang]
          return (
            <Link
              key={tutorial.slug}
              to={localizePath(`/tutorials/${tutorial.slug}`, locale)}
              className="group block h-full"
            >
              <CardSurface className="flex h-full flex-col overflow-hidden !p-0">
                <div className="overflow-hidden border-b border-[#ead7ae] bg-[#fbf7ef]">
                  <img
                    src={tutorial.cover}
                    alt=""
                    width="1400"
                    height="670"
                    loading="lazy"
                    className="aspect-[2/1] w-full object-cover object-top transition duration-500 group-hover:scale-[1.03]"
                  />
                </div>
                <div className="flex flex-1 flex-col p-5">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge tone="gold">{item.audienceLabel}</Badge>
                    <Badge tone="muted">
                      <ListChecks className="mr-1 h-3 w-3" />
                      {tutorial.steps.length} {copy.steps}
                    </Badge>
                  </div>
                  <h2 className="mt-4 font-display text-3xl leading-tight text-ink">{item.title}</h2>
                  <p className="mt-2 flex-1 text-sm leading-7 text-mist">{item.short}</p>
                  <span className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#8b6a2f]">
                    {copy.open}
                    <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" />
                  </span>
                </div>
              </CardSurface>
            </Link>
          )
        })}

        <CardSurface hover={false} className="flex flex-col justify-center border-dashed bg-[#fffaf0]">
          <Badge tone="muted" className="self-start">
            {copy.soonBadge}
          </Badge>
          <h2 className="mt-4 font-display text-3xl leading-tight text-ink">{copy.soonTitle}</h2>
          <p className="mt-2 text-sm leading-7 text-mist">{copy.soonText}</p>
          <div className="mt-5">
            <Button as={Link} to={localizePath('/epikoinonia', locale)} variant="secondary">
              {copy.contact}
            </Button>
          </div>
        </CardSurface>
      </div>
    </div>
  )
}

export default TutorialsPage
