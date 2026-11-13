import { ChevronDown } from 'lucide-react'
import { Link } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import StripeTransparencyCard from '@/components/trust/StripeTransparencyCard'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useSeo } from '@/context/SeoContext'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { getMarketplaceFaq } from '@/data/marketplaceFaq'
import { localizePath } from '@/utils/helpers'

function FaqPage() {
  const { locale } = useI18n()
  const seo = useSeo('faq')
  const { faqGroups } = useMarketplace()
  const groups = getMarketplaceFaq(faqGroups, locale)

  const copy =
    locale === 'en'
      ? {
          eyebrow: 'FAQ / Help',
          title: 'Common questions about buying, selling and orders',
          description:
            'Quick answers to the questions collectors ask most often before they buy, sell or complete account verification.',
        }
      : {
          eyebrow: 'FAQ / Βοήθεια',
          title: 'Συχνές ερωτήσεις για αγορές, πωλήσεις και παραγγελίες',
          description:
            'Γρήγορες απαντήσεις στις ερωτήσεις που κάνουν πιο συχνά οι συλλέκτες πριν αγοράσουν, πουλήσουν ή ολοκληρώσουν την επαλήθευσή τους.',
        }

  return (
    <div className="container pb-16">
      <PageSeo pageKey="faq" fallbackTitle={copy.title} fallbackDescription={copy.description} />
      <SectionHeader as="h1" eyebrow={copy.eyebrow} title={seo?.h1 || copy.title} description={copy.description} />
      <div className="mb-8">
        <StripeTransparencyCard />
      </div>
      <div className="space-y-8">
        {groups.map((group) => (
          <CardSurface key={group.title}>
            <h2 className="font-display text-4xl text-white">{group.title}</h2>
            <div className="mt-5 space-y-3">
              {group.items.map((item) => (
                <details key={item.question} className="group rounded-[24px] border border-white/8 bg-white/5">
                  <summary className="flex w-full cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-left [&::-webkit-details-marker]:hidden">
                    <span className="text-lg font-semibold text-white">{item.question}</span>
                    <ChevronDown className="h-5 w-5 shrink-0 text-gold-100 transition group-open:rotate-180" aria-hidden="true" />
                  </summary>
                  <p className="px-5 pb-5 text-sm leading-7 text-mist">{item.answer}</p>
                </details>
              ))}
            </div>
          </CardSurface>
        ))}
      </div>
      <nav aria-label={locale === 'en' ? 'Related help' : 'Σχετική βοήθεια'} className="mt-8 flex flex-wrap gap-5 text-sm text-ink">
        {[
          ['/odigos-polisis', locale === 'en' ? 'Selling guide and fees' : 'Οδηγός πώλησης και χρεώσεις'],
          ['/katastasi-kai-grading', locale === 'en' ? 'Condition and grading' : 'Κατάσταση και grading'],
          ['/epistrofes-kai-diafores', locale === 'en' ? 'Refunds and disputes' : 'Επιστροφές και διαφορές'],
          ['/epikoinonia', locale === 'en' ? 'Contact support' : 'Επικοινωνία με την υποστήριξη'],
        ].map(([to, label]) => <Link key={to} className="underline" to={localizePath(to, locale)}>{label}</Link>)}
      </nav>
    </div>
  )
}

export default FaqPage
