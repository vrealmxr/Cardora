import { ChevronDown } from 'lucide-react'
import { useEffect, useState } from 'react'
import StripeTransparencyCard from '@/components/trust/StripeTransparencyCard'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'

function FaqPage() {
  const { locale } = useI18n()
  const { faqGroups } = useMarketplace()
  const [openItems, setOpenItems] = useState([])

  const copy =
    locale === 'en'
      ? {
          eyebrow: 'FAQ / Help',
          title: 'Common questions about buying, selling and protected orders',
          description:
            'Quick answers to the questions collectors ask most often before they buy, sell or complete account verification.',
        }
      : {
          eyebrow: 'FAQ / Βοήθεια',
          title: 'Συχνές ερωτήσεις για αγορές, πωλήσεις και προστατευμένες παραγγελίες',
          description:
            'Γρήγορες απαντήσεις στις ερωτήσεις που κάνουν πιο συχνά οι συλλέκτες πριν αγοράσουν, πουλήσουν ή ολοκληρώσουν την επαλήθευσή τους.',
        }

  useEffect(() => {
    setOpenItems(faqGroups[0]?.items?.[0]?.question ? [faqGroups[0].items[0].question] : [])
  }, [faqGroups])

  const toggleItem = (question) => {
    setOpenItems((previous) =>
      previous.includes(question)
        ? previous.filter((item) => item !== question)
        : [...previous, question],
    )
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />
      <div className="mb-8">
        <StripeTransparencyCard />
      </div>
      <div className="space-y-8">
        {faqGroups.map((group) => (
          <CardSurface key={group.title}>
            <h2 className="font-display text-4xl text-white">{group.title}</h2>
            <div className="mt-5 space-y-3">
              {group.items.map((item) => {
                const open = openItems.includes(item.question)
                return (
                  <div key={item.question} className="rounded-[24px] border border-white/8 bg-white/5">
                    <button
                      type="button"
                      onClick={() => toggleItem(item.question)}
                      className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
                    >
                      <span className="text-lg font-semibold text-white">{item.question}</span>
                      <ChevronDown className={`h-5 w-5 text-gold-100 transition ${open ? 'rotate-180' : ''}`} />
                    </button>
                    {open ? <p className="px-5 pb-5 text-sm leading-7 text-mist">{item.answer}</p> : null}
                  </div>
                )
              })}
            </div>
          </CardSurface>
        ))}
      </div>
    </div>
  )
}

export default FaqPage
