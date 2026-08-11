import { Link } from 'react-router-dom'
import LanguageToggle from '@/components/ui/LanguageToggle'
import StripeTransparencyCard from '@/components/trust/StripeTransparencyCard'
import { footerUsefulLinks, mainNavigation } from '@/data/siteNavigation'
import { useMarketplace } from '@/hooks/useMarketplace'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function Footer() {
  const { t, locale } = useI18n()
  const { trustHighlights } = useMarketplace()

  return (
    <footer className="mt-20 border-t border-[#ecdcb9] bg-[rgba(255,252,246,0.96)]">
      <div className="container py-12">
        <div className="grid gap-8 xl:grid-cols-[1.5fr,1fr,1fr,1fr]">
          <div className="space-y-5">
            <img
              src="/asset.php?f=logo-20260716-compact.png"
              alt="Cardora"
              width="420"
              height="150"
              className="h-14 w-auto object-contain"
            />
            <p className="max-w-md text-sm leading-7 text-mist">{t('footer.description')}</p>
            <StripeTransparencyCard compact />
            <LanguageToggle className="!inline-flex" />
            <div className="grid gap-3 sm:grid-cols-2">
              {trustHighlights.map((item) => (
                <div
                  key={item}
                  className="rounded-xl border border-[#ead9b1] bg-white px-3.5 py-2.5 text-sm text-slate-700"
                >
                  {item}
                </div>
              ))}
            </div>
          </div>

          <div>
            <h3 className="font-display text-2xl text-slate-900">{t('footer.navigation')}</h3>
            <div className="mt-4 space-y-3">
              {mainNavigation.map((item) => (
                <Link
                  key={item.href}
                  to={localizePath(item.href, locale)}
                  className="block text-sm text-mist transition hover:text-gold-100"
                >
                  {t(`nav.${item.key}`)}
                </Link>
              ))}
            </div>
          </div>

          <div>
            <h3 className="font-display text-2xl text-slate-900">{t('footer.useful')}</h3>
            <div className="mt-4 space-y-3">
              {footerUsefulLinks.map((item) => (
                <Link
                  key={item.href}
                  to={localizePath(item.href, locale)}
                  className="block text-sm text-mist transition hover:text-gold-100"
                >
                  {t(`footer.${item.key}`, item.key)}
                </Link>
              ))}
            </div>

            <h3 className="mt-8 font-display text-2xl text-slate-900">{t('footer.categories')}</h3>
            <div className="mt-4 space-y-3 text-sm text-mist">
              <Link to={localizePath('/kartes', locale)} className="block transition hover:text-gold-100">
                {t('footer.cardsCategory')}
              </Link>
              <Link to={localizePath('/figoures', locale)} className="block transition hover:text-gold-100">
                {t('footer.figuresCategory')}
              </Link>
              <Link to={localizePath('/komik-vivlia', locale)} className="block transition hover:text-gold-100">
                {t('footer.comicsCategory')}
              </Link>
              <Link to={localizePath('/diafora', locale)} className="block transition hover:text-gold-100">
                {t('footer.miscCategory')}
              </Link>
            </div>
          </div>

          <div>
            <h3 className="font-display text-2xl text-slate-900">{t('footer.supportTrust')}</h3>
            <div className="mt-4 space-y-3 text-sm text-mist">
              <Link to={localizePath('/epalithefsi-logariasmou', locale)} className="block transition hover:text-gold-100">
                {t('common.verificationCenter')}
              </Link>
              <Link to={localizePath('/kentro-ypostiriksis', locale)} className="block transition hover:text-gold-100">
                {t('common.supportCenter')}
              </Link>
              <Link to={localizePath('/faq', locale)} className="block transition hover:text-gold-100">
                {t('common.faq')}
              </Link>
              <Link to={localizePath('/epikoinonia', locale)} className="block transition hover:text-gold-100">
                {t('common.contact')}
              </Link>
              <p>support@cardora.gr</p>
              <p>{t('footer.location')}</p>
            </div>
          </div>
        </div>

        <div className="mt-10 flex flex-col gap-4 border-t border-[#ecdcb9] pt-5 text-sm text-mist md:flex-row md:items-center md:justify-between">
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
