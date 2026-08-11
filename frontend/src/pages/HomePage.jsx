import { ArrowRight, ShieldCheck, Sparkles, Ticket, Trophy, WalletCards } from 'lucide-react'
import { Link } from 'react-router-dom'
import CategoryCard from '@/components/catalog/CategoryCard'
import DrawCard from '@/components/draws/DrawCard'
import ProductCard from '@/components/catalog/ProductCard'
import SearchBar from '@/components/catalog/SearchBar'
import SellerCard from '@/components/people/SellerCard'
import StripeTransparencyCard from '@/components/trust/StripeTransparencyCard'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency, formatNumber } from '@/utils/formatters'
function HomePage() {
  const { locale } = useI18n()
  const {
    categories,
    myDrawParticipations,
    platformDraws,
    trendingProducts,
    recentProducts,
    topSellers,
    platformStats,
    reviews,
    trustHighlights,
    howItWorksSteps,
  } = useMarketplace()

  const officialCampaigns = [...platformDraws]
    .filter((draw) => ['active', 'locked'].includes(draw.status))
    .sort((left, right) => {
      if (Number(right.featured) !== Number(left.featured)) {
        return Number(right.featured) - Number(left.featured)
      }

      const leftTime = new Date(left.drawAt ?? left.endsAt ?? 0).getTime()
      const rightTime = new Date(right.drawAt ?? right.endsAt ?? 0).getTime()

      return rightTime - leftTime
    })

  const [featuredPlatformDraw, ...secondaryPlatformDraws] = officialCampaigns
  const quickTrendingProducts = trendingProducts.slice(0, 4)
  const liveOfficialCampaigns = officialCampaigns.length
  const officialCampaignIds = new Set(officialCampaigns.map((draw) => draw.id))
  const officialEntries = myDrawParticipations
    .filter((entry) => officialCampaignIds.has(entry.drawId))
    .reduce((sum, entry) => sum + Number(entry.entries ?? 0), 0)
  const officialTrackedVolume = officialCampaigns.reduce(
    (sum, draw) => sum + Number(draw.currentAmount ?? 0),
    0,
  )

  const copy =
    locale === 'en'
      ? {
          heroBadge: 'Trusted collector marketplace',
          heroTitle: 'Buy, sell and trade collectibles with confidence.',
          heroDescription:
            'Cardora is the premium marketplace for cards, figures, comics, books and one-of-a-kind collectibles. Built for secure transactions, trusted sellers and 100% protected payments.',
          ctaBuy: 'Start browsing',
          ctaSell: 'List an item',
          trustStrip:
            'Funds stay with the platform and are released only after the buyer confirms everything arrived as described.',
          rightCards: [
            {
              title: 'Protected payments',
              text: 'The payment stays secured until the order is received and confirmed.',
            },
            {
              title: 'Clear listings',
              text: 'Condition, photos and key details are shown upfront so buyers know what they are getting.',
            },
            {
              title: 'Verified members',
              text: 'Ratings, verification status and account history help you judge each profile more easily.',
            },
          ],
          drawEyebrow: 'Cardora Campaigns',
          drawTitle: 'Official Cardora campaigns',
          drawDescription:
            'Official campaigns from Cardora with a clear prize, visible progress and a clean closing window on the homepage.',
          communityDraws: 'Collector raffles',
          managedByCardora: 'Managed by Cardora',
          officialBadge: 'Official campaign',
          drawHeadline: 'Cardora campaigns stay short, visible and easy to understand from the first glance.',
          drawText:
            'Each official campaign shows the prize, the target and the timing upfront, while the fulfillment and shipping flow remain handled directly by Cardora.',
          yourEntries: 'Your entries',
          activeVolume: 'Tracked campaign volume',
          activeCampaigns: 'Live campaigns',
          example:
            'The active official campaigns appear here automatically, with their photo, progress and key details already in place.',
          categoriesEyebrow: 'Featured Categories',
          categoriesTitle: 'Browse the marketplace by collecting focus',
          categoriesDescription:
            'Cards stay at the heart of Cardora, but every category is built to highlight valuable pieces with the same care.',
          trendingEyebrow: 'Trending Listings',
          trendingTitle: 'Listings collectors are watching right now',
          trendingDescription:
            'From graded cards and sealed boxes to statues, comics and hard-to-find memorabilia.',
          worksEyebrow: 'How Cardora Works',
          worksTitle: 'A safer way to complete collector-to-collector deals',
          worksDescription:
            'The process is simple for both sides and keeps the final payout tied to a successful delivery.',
          trustEyebrow: 'Trust & Protection',
          trustTitle: 'Protection for both buyers and sellers',
          trustDescription:
            'Cardora puts clarity before checkout so high-value trades can move forward with less risk and fewer surprises.',
          paymentTitle: 'Protected payment',
          paymentCards: [
            {
              title: 'Funds stay secured',
              text: 'The seller is not paid the moment the checkout is completed. The release happens after delivery is confirmed.',
              icon: WalletCards,
            },
            {
              title: 'Support when something is wrong',
              text: 'If the order does not match the listing, the Cardora team reviews the evidence before deciding the next step.',
              icon: Sparkles,
            },
          ],
          sellersEyebrow: 'Top Sellers',
          sellersTitle: 'Collectors with strong reputations',
          sellersDescription:
            'Profiles with consistent sales, clear specialties and a track record other buyers can check.',
          recentEyebrow: 'Recently Added',
          recentTitle: 'Fresh listings just added to the marketplace',
          recentDescription:
            'A quick look at the newest pieces before they disappear into someone elseâ€™s collection.',
          reviewsEyebrow: 'Collector Reviews',
          reviewsTitle: 'What members say about Cardora',
          reviewsDescription:
            'Experiences from collectors who value clear listings, protected payments and calmer transactions.',
          finalBadge: 'List your first collectible',
          finalTitle: 'Put your next piece in front of serious collectors.',
          finalDescription:
            'Whether you are selling a slab, a sealed box, a rare figure or a key issue, Cardora helps you present it clearly and sell it with more confidence.',
          finalCta: 'Create listing',
          finalSecondary: 'How it works',
        }
      : {
          heroBadge: 'Ασφαλής αγορά συλλεκτικών',
          heroTitle: 'Αγόρασε, πούλησε και αντάλλαξε συλλεκτικά με ασφάλεια.',
          heroDescription:
            'Η Cardora είναι το premium marketplace για κάρτες, φιγούρες, κόμικς, βιβλία και μοναδικά συλλεκτικά. Σχεδιασμένο για ασφαλείς συναλλαγές, αξιόπιστους πωλητές και 100% προστατευμένες πληρωμές.',
          ctaBuy: 'Ξεκίνα τις αγορές',
          ctaSell: 'Δημιούργησε αγγελία',
          trustStrip:
            'Τα χρήματα παραμένουν προστατευμένα μέχρι να ολοκληρωθεί σωστά η παραγγελία και να επιβεβαιωθεί η παραλαβή.',
          rightCards: [
            {
              title: 'Προστατευμένες πληρωμές',
              text: 'Η πληρωμή μένει ασφαλής μέχρι να παραληφθεί και να επιβεβαιωθεί η παραγγελία.',
            },
            {
              title: 'Καθαρές αγγελίες',
              text: 'Κατάσταση, φωτογραφίες και βασικά στοιχεία εμφανίζονται ξεκάθαρα πριν από κάθε αγορά.',
            },
            {
              title: 'Επαληθευμένα μέλη',
              text: 'Αξιολογήσεις, verification status και ιστορικό λογαριασμού σε βοηθούν να κρίνεις πιο εύκολα κάθε προφίλ.',
            },
          ],
          drawEyebrow: 'Επίσημα Campaigns',
          drawTitle: 'Επίσημες καμπάνιες της Cardora',
          drawDescription:
            'Εδώ θα βρεις τις επίσημες καμπάνιες της Cardora, με ξεκάθαρο δώρο, ζωντανή πρόοδο και όλες τις βασικές πληροφορίες με μια ματιά.',
          communityDraws: 'Κληρώσεις συλλεκτών',
          managedByCardora: 'Διαχείριση από Cardora',
          officialBadge: 'Επίσημη καμπάνια',
          drawHeadline: 'Επίσημες κληρώσεις της Cardora.',
          drawText: 'Συμμετείχε σε οργανωμένες κληρώσεις για σπάνιες κάρτες και μοναδικά συλλεκτικά αντικείμενα.',
          yourEntries: 'Οι συμμετοχές σου',
          activeVolume: 'Ενεργός όγκος καμπανιών',
          activeCampaigns: 'Ενεργές καμπάνιες',
          example:
            'Οι επίσημες καμπάνιες εμφανίζονται άμεσα, με απλό setup και καθαρή εικόνα από το πρώτο δευτερόλεπτο.',
          categoriesEyebrow: 'Featured Categories',
          categoriesTitle: 'Περιηγήσου ανά κατηγορία',
          categoriesDescription:
            'Οι κάρτες είναι ο πυρήνας μας — αλλά κάθε κατηγορία αναδεικνύεται με την ίδια προσοχή.',
          trendingEyebrow: 'Trending Αγγελίες',
          trendingTitle: 'Αγγελίες που τραβούν το ενδιαφέρον των συλλεκτών τώρα',
          trendingDescription:
            'Από graded κάρτες και sealed boxes μέχρι statues, κόμικς και πιο δύσκολα συλλεκτικά κομμάτια.',
          worksEyebrow: 'Πώς λειτουργεί',
          worksTitle: 'Ένας πιο ασφαλής τρόπος για συναλλαγές μεταξύ συλλεκτών',
          worksDescription:
            'Η διαδικασία είναι καθαρή και για τις δύο πλευρές και κρατά την αποδέσμευση συνδεδεμένη με την επιτυχημένη ολοκλήρωση της παραγγελίας.',
          trustEyebrow: 'Προστασία & εμπιστοσύνη',
          trustTitle: 'Προστασία τόσο για αγοραστή όσο και για πωλητή',
          trustDescription:
            'Η Cardora βάζει τη διαφάνεια πριν από την πληρωμή, ώστε ακόμα και πιο ακριβά συλλεκτικά να αλλάζουν χέρια με λιγότερο άγχος και περισσότερη σιγουριά.',
          paymentTitle: 'Προστατευμένη πληρωμή',
          paymentCards: [
            {
              title: 'Τα χρήματα μένουν προστατευμένα',
              text: 'Ο πωλητής δεν πληρώνεται αμέσως με το checkout. Η αποδέσμευση γίνεται μετά την παραλαβή και την επιβεβαίωση.',
              icon: WalletCards,
            },
            {
              title: 'Υποστήριξη όταν κάτι δεν πάει σωστά',
              text: 'Αν το αντικείμενο δεν ταιριάζει με την αγγελία, η ομάδα της Cardora εξετάζει τα στοιχεία πριν προχωρήσει στο επόμενο βήμα.',
              icon: Sparkles,
            },
          ],
          sellersEyebrow: 'Top Sellers',
          sellersTitle: 'Συλλέκτες με δυνατή φήμη και σταθερή παρουσία',
          sellersDescription:
            'Προφίλ με συνεπείς πωλήσεις, καθαρές ειδικότητες και ιστορικό που μπορούν να δουν εύκολα οι αγοραστές.',
          recentEyebrow: 'Νέες αγγελίες',
          recentTitle: 'Οι πιο πρόσφατες προσθήκες στο marketplace',
          recentDescription:
            'Μια γρήγορη ματιά στα νέα κομμάτια πριν φύγουν για τη συλλογή κάποιου άλλου.',
          reviewsEyebrow: 'Απόψεις μελών',
          reviewsTitle: 'Τι λένε οι συλλέκτες για την Cardora',
          reviewsDescription:
            'Εμπειρίες από μέλη που εκτιμούν τις καθαρές αγγελίες, τις προστατευμένες πληρωμές και τις πιο ήρεμες συναλλαγές.',
          finalBadge: 'Βγάλε τη δική σου αγγελία',
          finalTitle: 'Φέρε το επόμενο συλλεκτικό σου μπροστά στο σωστό κοινό.',
          finalDescription:
            'Είτε πουλάς slab, sealed box, σπάνια φιγούρα ή key issue, η Cardora σε βοηθά να το παρουσιάσεις καθαρά και να το διαθέσεις με περισσότερη σιγουριά.',
          finalCta: 'Δημιούργησε αγγελία',
          finalSecondary: 'Δες πώς λειτουργεί',
        }

  const quickBrowseCopy =
    locale === 'en'
      ? {
          eyebrow: 'Quick browse',
          title: 'Products first',
          description: 'Jump straight to active listings on mobile.',
        }
      : {
          eyebrow: 'Γρήγορη περιήγηση',
          title: 'Πρώτα τα προϊόντα',
          description: 'Στο κινητό βλέπεις άμεσα ενεργές αγγελίες.',
        }

  const normalizedHowItWorksSteps = howItWorksSteps.map((step, index) => {
    const isHold = step.id === 'hold' || index === 2
    const isRelease = step.id === 'release' || index === 3

    if (locale === 'en') {
      if (isHold) {
        return {
          ...step,
          title: 'The amount remains on hold',
          text: 'The payment remains protected while the shipment is on the way and while the buyer checks the item.',
        }
      }

      if (isRelease) {
        return {
          ...step,
          title: 'Payment is released after confirmation',
          text: 'Seller payout is completed after buyer confirmation or the end of the review period.',
        }
      }

      return step
    }

    if (isHold) {
      return {
        ...step,
        title: 'Το ποσό παραμένει δεσμευμένο',
        text: 'Η πληρωμή παραμένει προστατευμένη όσο η αποστολή είναι καθοδόν και όσο ο αγοραστής ελέγχει το αντικείμενο.',
      }
    }

    if (isRelease) {
      return {
        ...step,
        title: 'Η πληρωμή αποδεσμεύεται μετά την επιβεβαίωση',
        text: 'Η απόδοση στον πωλητή γίνεται μετά την επιβεβαίωση του αγοραστή ή τη λήξη της περιόδου ελέγχου.',
      }
    }

    return step
  })

  return (
    <div className="pb-10 sm:pb-14">
      <section className="container">
        <div className="grid gap-4 lg:gap-6 xl:grid-cols-[1.2fr,0.8fr]">
          <CardSurface className="relative overflow-hidden p-4 sm:p-6 md:p-7">
            <div className="relative z-10 max-w-2xl">
              <Badge tone="gold">{copy.heroBadge}</Badge>
              <h1 className="mt-4 max-w-3xl font-display text-3xl leading-[1.06] text-ink sm:text-4xl md:text-5xl xl:text-6xl">
                {copy.heroTitle}
              </h1>
              <p className="mt-3 max-w-2xl text-sm leading-6 text-mist sm:mt-5 sm:text-base sm:leading-7">
                {copy.heroDescription}
              </p>

              <div className="mt-5 flex flex-wrap gap-2.5 sm:mt-7">
                <Button as={Link} to="/kartes" size="lg">
                  {copy.ctaBuy}
                  <ArrowRight className="h-4 w-4" />
                </Button>
                <Button as={Link} to="/dimiourgia-aggelias" variant="secondary" size="lg">
                  {copy.ctaSell}
                </Button>
              </div>

              <div className="mt-5 hidden gap-2.5 sm:grid sm:grid-cols-3">
                {platformStats.slice(0, 3).map((stat) => (
                  <div key={stat.label} className="rounded-[20px] border border-[#eadab7] bg-white p-3.5">
                    <p className="text-[10px] uppercase tracking-[0.26em] text-[#968565]">
                      {stat.label}
                    </p>
                    <p className="mt-1.5 text-xl font-semibold text-ink">
                      {stat.format === 'currency' ? formatCurrency(stat.value ?? 0) : typeof stat.value === 'number' ? formatNumber(stat.value) : stat.value}
                    </p>
                  </div>
                ))}
              </div>

              <div className="mt-5 hidden items-center gap-3 rounded-[20px] border border-gold-300/20 bg-gold-300/10 px-3.5 py-3.5 text-[13px] text-[#7a6440] sm:flex">
                <ShieldCheck className="h-5 w-5 shrink-0" />
                {copy.trustStrip}
              </div>

              <StripeTransparencyCard compact className="mt-4 hidden sm:block" />
            </div>
          </CardSurface>

          <CardSurface className="hidden flex-col justify-between overflow-hidden p-0 xl:flex">
            <div className="relative overflow-hidden rounded-[24px] p-5">
              <img
                src="/asset.php?f=home-banner-20260716-960.jpg"
                alt="Cardora brand hero"
                width="960"
                height="535"
                className="banner-image-glow w-full rounded-[20px] border border-[#eadab7] object-cover"
                loading="eager"
                fetchPriority="high"
                decoding="async"
              />
            </div>

            <div className="grid gap-2.5 px-5 pb-5 sm:grid-cols-3 xl:grid-cols-1">
              {copy.rightCards.map((item) => (
                <div key={item.title} className="rounded-[20px] border border-[#eadab7] bg-white p-3.5">
                  <p className="text-xs uppercase tracking-[0.3em] text-gold-700">{item.title}</p>
                  <p className="mt-2 text-sm leading-7 text-mist">{item.text}</p>
                </div>
              ))}
            </div>
          </CardSurface>
        </div>
      </section>

      <section className="container mt-4 sm:mt-6 md:mt-8">
        <SearchBar />
      </section>

      {quickTrendingProducts.length ? (
        <section className="container mt-6 md:hidden">
          <SectionHeader
            eyebrow={quickBrowseCopy.eyebrow}
            title={quickBrowseCopy.title}
            description={quickBrowseCopy.description}
            className="!mb-3"
          />
          <div className="grid gap-3">
            {quickTrendingProducts.map((product) => (
              <ProductCard key={`mobile-quick-${product.id}`} product={product} />
            ))}
          </div>
        </section>
      ) : null}

      {featuredPlatformDraw ? (
        <section className="container mt-10 sm:mt-14">
          <SectionHeader
            eyebrow={copy.drawEyebrow}
            title={copy.drawTitle}
            description={copy.drawDescription}
          />

          <CardSurface className="h-full p-5 sm:p-6">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone="gold">{copy.managedByCardora}</Badge>
                <Badge tone="info">{copy.officialBadge}</Badge>
              </div>
              <h3 className="mt-4 font-display text-3xl text-ink">
                {copy.drawHeadline}
              </h3>
              <p className="mt-3 text-sm leading-6 text-mist">
                {copy.drawText}
              </p>

              <div className="mt-5 grid gap-3 md:grid-cols-3">
                <div className="rounded-[18px] border border-[#eadab7] bg-white p-3.5">
                  <div className="flex items-center gap-2 text-gold-700">
                    <Ticket className="h-4 w-4" />
                    <p className="text-[10px] uppercase tracking-[0.22em] text-[#968565]">{copy.yourEntries}</p>
                  </div>
                  <p className="mt-2 text-base font-semibold text-ink">
                    {formatNumber(officialEntries)}
                  </p>
                </div>
                <div className="rounded-[18px] border border-[#eadab7] bg-white p-3.5">
                  <div className="flex items-center gap-2 text-gold-700">
                    <Trophy className="h-4 w-4" />
                    <p className="text-[10px] uppercase tracking-[0.22em] text-[#968565]">{copy.activeVolume}</p>
                  </div>
                  <p className="mt-2 text-base font-semibold text-ink">
                    {formatCurrency(officialTrackedVolume)}
                  </p>
                </div>
                <div className="rounded-[18px] border border-[#eadab7] bg-white p-3.5">
                  <div className="flex items-center gap-2 text-gold-700">
                    <ShieldCheck className="h-4 w-4" />
                    <p className="text-[10px] uppercase tracking-[0.22em] text-[#968565]">{copy.activeCampaigns}</p>
                  </div>
                  <p className="mt-2 text-base font-semibold text-ink">
                    {formatNumber(liveOfficialCampaigns)}
                  </p>
                </div>
              </div>
            </div>

            <div className="mt-6 grid gap-4">
              <DrawCard draw={featuredPlatformDraw} showDetails={false} />
              {secondaryPlatformDraws.length ? (
                <div className="grid gap-4 md:grid-cols-2">
                  {secondaryPlatformDraws.slice(0, 2).map((draw) => (
                    <DrawCard key={draw.id} draw={draw} compact showDetails={false} />
                  ))}
                </div>
              ) : null}
            </div>
          </CardSurface>
        </section>
      ) : null}

      <section className="container mt-12 sm:mt-16">
        <SectionHeader
          eyebrow={copy.categoriesEyebrow}
          title={copy.categoriesTitle}
          description={copy.categoriesDescription}
        />
        <div className="section-grid">
          {categories.map((category) => (
            <CategoryCard key={category.id} category={category} />
          ))}
        </div>
      </section>

      <section className="hidden container mt-12 sm:mt-16 md:block">
        <SectionHeader
          eyebrow={copy.trendingEyebrow}
          title={copy.trendingTitle}
          description={copy.trendingDescription}
        />
        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {trendingProducts.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>

      <section className="container mt-16">
        <SectionHeader
          eyebrow={copy.worksEyebrow}
          title={copy.worksTitle}
          description={copy.worksDescription}
        />
        <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
          {normalizedHowItWorksSteps.map((step, index) => (
            <CardSurface key={step.id} className="h-full">
              <div className="mb-5 flex h-10 w-10 items-center justify-center rounded-xl bg-gold-300/15 text-base font-semibold text-gold-100">
                0{index + 1}
              </div>
              <h3 className="text-lg font-semibold text-ink">{step.title}</h3>
              <p className="mt-2.5 text-sm leading-6 text-mist">{step.text}</p>
            </CardSurface>
          ))}
        </div>
      </section>

      <section className="container mt-16">
        <div className="grid gap-5 xl:grid-cols-[1.1fr,0.9fr]">
          <CardSurface className="h-full">
            <SectionHeader
              eyebrow={copy.trustEyebrow}
              title={copy.trustTitle}
              description={copy.trustDescription}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              {trustHighlights.map((item) => (
                <div
                  key={item}
                  className="rounded-[20px] border border-[#eadab7] bg-white p-3.5"
                >
                  <div className="flex items-center gap-3">
                    <div className="rounded-xl bg-gold-300/12 p-2 text-gold-100">
                      <ShieldCheck className="h-4 w-4" />
                    </div>
                    <p className="font-semibold text-ink">{item}</p>
                  </div>
                </div>
              ))}
            </div>
          </CardSurface>

          <CardSurface className="h-full">
            <h3 className="font-display text-3xl text-ink">{copy.paymentTitle}</h3>
            <div className="mt-5 space-y-3">
              {copy.paymentCards.map((item) => {
                const Icon = item.icon
                return (
                  <div key={item.title} className="rounded-[20px] border border-[#eadab7] bg-white p-3.5">
                    <div className="flex items-center gap-3">
                      <Icon className="h-5 w-5 text-gold-700" />
                      <p className="font-semibold text-ink">{item.title}</p>
                    </div>
                    <p className="mt-2 text-sm leading-7 text-mist">{item.text}</p>
                  </div>
                )
              })}
            </div>
          </CardSurface>
        </div>
      </section>

      <section className="container mt-16">
        <SectionHeader
          eyebrow={copy.sellersEyebrow}
          title={copy.sellersTitle}
          description={copy.sellersDescription}
        />
        <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
          {topSellers.map((seller) => (
            <SellerCard key={seller.id} seller={seller} />
          ))}
        </div>
      </section>

      <section className="container mt-16">
        <SectionHeader
          eyebrow={copy.recentEyebrow}
          title={copy.recentTitle}
          description={copy.recentDescription}
        />
        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {recentProducts.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>

      <section className="container mt-16">
        <SectionHeader
          eyebrow={copy.reviewsEyebrow}
          title={copy.reviewsTitle}
          description={copy.reviewsDescription}
          align="center"
        />
        <div className="grid gap-5 lg:grid-cols-3">
          {reviews.map((review) => (
            <CardSurface key={review.id} className="h-full">
              <p className="font-display text-2xl text-gold-200">&ldquo;</p>
              <p className="mt-2.5 text-sm leading-7 text-[#5f6b7a]">{review.quote}</p>
              <div className="mt-5">
                <p className="font-semibold text-ink">{review.author}</p>
                <p className="text-sm text-mist">{review.role}</p>
              </div>
            </CardSurface>
          ))}
        </div>
      </section>

    </div>
  )
}

export default HomePage
