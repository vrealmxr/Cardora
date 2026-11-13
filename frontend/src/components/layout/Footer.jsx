import { Link } from 'react-router-dom'
import LanguageToggle from '@/components/ui/LanguageToggle'
import PartnerLogos from '@/components/trust/PartnerLogos'
import StripeTransparencyCard from '@/components/trust/StripeTransparencyCard'
import { footerUsefulLinks, mainNavigation } from '@/data/siteNavigation'
import { marketplaceTrust } from '@/data/marketplaceTrust'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'
import { openCookieSettings } from '@/utils/cookieConsent'

function Footer() {
  const { t, locale } = useI18n()
  const trust = marketplaceTrust[locale]
  const trustHighlights = trust.highlights

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
            <p className="max-w-md text-sm leading-7 text-mist">{trust.description}</p>
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
                  {t(`footer.${item.key}`, t(`common.${item.key}`, item.key))}
                </Link>
              ))}
              <a href={`/${locale === 'en' ? 'en' : 'el'}/oroi-giveaway-dekemvrios-2026`} hrefLang={locale === 'en' ? 'en' : 'el'} className="block text-sm text-mist underline underline-offset-4 hover:text-gold-700">
                {locale === 'en' ? 'Giveaway terms' : 'Όροι giveaway'}
              </a>
            </div>

            <h3 className="mt-8 font-display text-2xl text-slate-900">{t('footer.categories')}</h3>
            <div className="mt-4 space-y-3 text-sm text-mist">
              <Link to={localizePath('/syllektika', locale)} className="block transition hover:text-gold-100">{locale === 'en' ? 'All collectibles' : 'Όλα τα συλλεκτικά'}</Link>
              <Link to={localizePath('/odigos-polisis', locale)} className="block transition hover:text-gold-100">{locale === 'en' ? 'Selling guide and fees' : 'Οδηγός πώλησης και χρεώσεις'}</Link>
              <Link to={localizePath('/katastasi-kai-grading', locale)} className="block transition hover:text-gold-100">{locale === 'en' ? 'Condition and grading' : 'Κατάσταση και grading'}</Link>
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
              <a className="block underline" href="mailto:support@cardora.gr">support@cardora.gr</a>
              <p>{t('footer.location')}</p>
              <p>VRealm Ι.Κ.Ε. · {locale === 'en' ? 'VAT' : 'Α.Φ.Μ.'} 802264482</p>
            </div>
          </div>
        </div>

        <PartnerLogos className="mt-10 justify-center border-t border-[#ecdcb9] pt-6 md:justify-start" />

        <div className="mt-6 flex flex-col gap-4 border-t border-[#ecdcb9] pt-5 text-sm text-mist md:flex-row md:items-center md:justify-between">
          <p>{t('footer.copyright')}</p>
          <div className="flex flex-wrap gap-4">
            <button type="button" onClick={openCookieSettings} className="transition hover:text-gold-100">
              {locale === 'en' ? 'Cookie settings' : 'Ρυθμίσεις cookies'}
            </button>
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
