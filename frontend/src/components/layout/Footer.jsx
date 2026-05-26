import { Link } from 'react-router-dom'
import LanguageToggle from '@/components/ui/LanguageToggle'
import StripeTransparencyCard from '@/components/trust/StripeTransparencyCard'
import { footerUsefulLinks, mainNavigation } from '@/data/siteNavigation'
import { useMarketplace } from '@/hooks/useMarketplace'
import { useI18n } from '@/hooks/useI18n'

function Footer() {
  const { t } = useI18n()
  const { trustHighlights } = useMarketplace()

  return (
    <footer className="mt-20 border-t border-white/8 bg-[#07101e]/90">
      <div className="container py-12">
        <div className="grid gap-8 xl:grid-cols-[1.5fr,1fr,1fr,1fr]">
          <div className="space-y-5">
            <img src="/asset.php?f=logo.png" alt="Cardora" className="h-14 w-auto object-contain" />
            <p className="max-w-md text-sm leading-7 text-mist">{t('footer.description')}</p>
            <StripeTransparencyCard compact />
            <LanguageToggle className="!inline-flex" />
            <div className="grid gap-3 sm:grid-cols-2">
              {trustHighlights.map((item) => (
                <div
                  key={item}
                  className="rounded-xl border border-white/8 bg-white/5 px-3.5 py-2.5 text-sm text-white/80"
                >
                  {item}
                </div>
              ))}
            </div>
          </div>

          <div>
            <h3 className="font-display text-2xl text-white">{t('footer.navigation')}</h3>
            <div className="mt-4 space-y-3">
              {mainNavigation.map((item) => (
                <Link
                  key={item.href}
                  to={item.href}
                  className="block text-sm text-mist transition hover:text-gold-100"
                >
                  {t(`nav.${item.key}`)}
                </Link>
              ))}
            </div>
          </div>

          <div>
            <h3 className="font-display text-2xl text-white">{t('footer.useful')}</h3>
            <div className="mt-4 space-y-3">
              {footerUsefulLinks.map((item) => (
                <Link
                  key={item.href}
                  to={item.href}
                  className="block text-sm text-mist transition hover:text-gold-100"
                >
                  {t(`footer.${item.key}`, item.key)}
                </Link>
              ))}
            </div>

            <h3 className="mt-8 font-display text-2xl text-white">{t('footer.categories')}</h3>
            <div className="mt-4 space-y-3 text-sm text-mist">
              <Link to="/kartes" className="block transition hover:text-gold-100">
                {t('footer.cardsCategory')}
              </Link>
              <Link to="/figoures" className="block transition hover:text-gold-100">
                {t('footer.figuresCategory')}
              </Link>
              <Link to="/komik-vivlia" className="block transition hover:text-gold-100">
                {t('footer.comicsCategory')}
              </Link>
              <Link to="/diafora" className="block transition hover:text-gold-100">
                {t('footer.miscCategory')}
              </Link>
            </div>
          </div>

          <div>
            <h3 className="font-display text-2xl text-white">{t('footer.supportTrust')}</h3>
            <div className="mt-4 space-y-3 text-sm text-mist">
              <Link to="/epalithefsi-logariasmou" className="block transition hover:text-gold-100">
                {t('common.verificationCenter')}
              </Link>
              <Link to="/kentro-ypostiriksis" className="block transition hover:text-gold-100">
                {t('common.supportCenter')}
              </Link>
              <Link to="/faq" className="block transition hover:text-gold-100">
                {t('common.faq')}
              </Link>
              <Link to="/epikoinonia" className="block transition hover:text-gold-100">
                {t('common.contact')}
              </Link>
              <p>support@cardora.gr</p>
              <p>{t('footer.location')}</p>
            </div>
          </div>
        </div>

        <div className="mt-10 flex flex-col gap-4 border-t border-white/8 pt-5 text-sm text-mist md:flex-row md:items-center md:justify-between">
          <p>{t('footer.copyright')}</p>
          <div className="flex flex-wrap gap-4">
            <a href="https://www.instagram.com/cardora.gr" target="_blank" rel="noreferrer" className="transition hover:text-gold-100">
              Instagram
            </a>
            <a href="https://www.tiktok.com/@cardora.gr" target="_blank" rel="noreferrer" className="transition hover:text-gold-100">
              TikTok
            </a>
            <a href="https://discord.gg/cPzWg6S34" target="_blank" rel="noreferrer" className="transition hover:text-gold-100">
              Discord
            </a>
          </div>
        </div>
      </div>
    </footer>
  )
}

export default Footer
