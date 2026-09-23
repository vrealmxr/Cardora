import {
  ImagePlus,
  Loader2,
  Mail,
  PencilLine,  Plus,
  ShieldCheck,
  Star,
  Trash2,
} from 'lucide-react'
import ProfileAppearanceEditor from '@/components/profile/ProfileAppearanceEditor'
import UserAvatar from '@/components/people/UserAvatar'
import { useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Select, Textarea } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency } from '@/utils/formatters'
import { getCollectorProfileRoute, getUserDisplayName } from '@/utils/helpers'
import { normalizeTextTree } from '@/utils/textEncoding'

const createInitialCollectionForm = () => ({
  title: '',
  caption: '',
  visibility: 'public',
  sortOrder: 0,
  isFeatured: false,
  media: [],
  mediaFiles: [],
  productId: null,
})

const getMediaUrl = (mediaItem) => {
  if (!mediaItem) return null
  if (typeof mediaItem === 'string') return mediaItem

  if (typeof mediaItem === 'object') {
    return mediaItem.url ?? mediaItem.preview_url ?? mediaItem.path ?? null
  }

  return null
}

const getEntryMedia = (entry) => {
  const ownMedia = Array.isArray(entry?.media) ? entry.media.filter(Boolean) : []
  if (ownMedia.length) return ownMedia

  const linkedMedia = Array.isArray(entry?.product?.media) ? entry.product.media.filter(Boolean) : []
  return linkedMedia
}

const getEntrySubtitle = (entry) =>
  entry?.caption ||
  [entry?.product?.franchise, entry?.product?.condition].filter(Boolean).join(' • ')

function ProfilePage() {
  const { locale } = useI18n()
  const { currentUser } = useAuth()
  const {
    accountVerification,
    favoriteProducts,
    marketplaceAccess,
    orders,
    personalizedCategorySummary,
    productsWithSellers,
    recentProducts,
    getCollectorProfileByUserId,
    myCollectionEntries,
    myListings,
    createCollectionEntry,
    updateCollectionEntry,
    deleteCollectionEntry,
  } = useMarketplace()

  const [editingCollectionId, setEditingCollectionId] = useState(null)
  const [collectionForm, setCollectionForm] = useState(createInitialCollectionForm)
  const [collectionBusy, setCollectionBusy] = useState(false)
  const [collectionDeletingId, setCollectionDeletingId] = useState(null)
  const [collectionFeedback, setCollectionFeedback] = useState(null)
  const [fileInputKey, setFileInputKey] = useState(0)
  const [searchParams] = useSearchParams()
  const recentActivity = useMemo(() => {
    if (!currentUser) return []

    const items = []
    const pushUnique = (value) => {
      if (!value) return
      if (items.includes(value)) return
      items.push(value)
    }

    ;(Array.isArray(recentProducts) ? recentProducts : []).slice(0, 2).forEach((product) => {
      pushUnique(
        locale === 'en'
          ? `You recently viewed "${product.title}".`
          : `Είδες πρόσφατα την αγγελία "${product.title}".`,
      )
    })

    ;(Array.isArray(favoriteProducts) ? favoriteProducts : []).slice(0, 2).forEach((product) => {
      pushUnique(
        locale === 'en'
          ? `You saved "${product.title}" to your favorites.`
          : `Έβαλες στα αγαπημένα σου το "${product.title}".`,
      )
    })

    ;(Array.isArray(myListings) ? myListings : [])
      .slice()
      .sort((left, right) => new Date(right.listedAt ?? 0) - new Date(left.listedAt ?? 0))
      .slice(0, 2)
      .forEach((listing) => {
        pushUnique(
          locale === 'en'
            ? `Your listing "${listing.title}" is active on Cardora.`
            : `Η αγγελία σου "${listing.title}" είναι ενεργή στην Cardora.`,
        )
      })

    ;(Array.isArray(orders) ? orders : [])
      .slice()
      .sort((left, right) => new Date(right.updatedAt ?? right.createdAt ?? 0) - new Date(left.updatedAt ?? left.createdAt ?? 0))
      .slice(0, 3)
      .forEach((order) => {
        const product =
          (Array.isArray(productsWithSellers) ? productsWithSellers : []).find(
            (item) =>
              Number(item.id) === Number(order.productId) ||
              Number(item.databaseId ?? 0) === Number(order.productId ?? 0),
          ) ?? null
        const title = product?.title

        if (Number(order.buyerId ?? 0) === Number(currentUser.id) && title) {
          pushUnique(
            locale === 'en'
              ? `You placed an order for "${title}".`
              : `Ολοκλήρωσες αγορά για το "${title}".`,
          )
        }

        if (Number(order.sellerId ?? 0) === Number(currentUser.id) && title) {
          pushUnique(
            locale === 'en'
              ? `You have a sale for "${title}".`
              : `Έχεις πώληση για το "${title}".`,
          )
        }
      })

    if (!items.length && Array.isArray(currentUser.recentActivity)) {
      currentUser.recentActivity.forEach((item) => pushUnique(item))
    }

    return items.slice(0, 6)
  }, [currentUser, favoriteProducts, locale, myListings, orders, productsWithSellers, recentProducts])

  if (!currentUser) return null

  const publicProfile = getCollectorProfileByUserId(currentUser.id)
  const collectionEntries = Array.isArray(myCollectionEntries) ? myCollectionEntries : []

  const favoriteCategories = Array.isArray(currentUser.favoriteCategories)
    ? currentUser.favoriteCategories
    : []
  const listedCount = Number(currentUser.listedItems ?? currentUser.listingsCount ?? 0)
  const soldCount = Number(currentUser.soldItems ?? currentUser.salesCount ?? 0)
  const purchasedCount = Number(currentUser.purchasedItems ?? currentUser.purchaseCount ?? 0)

  const displayFavoriteCategories =
    favoriteCategories.length > 0
      ? favoriteCategories
      : personalizedCategorySummary
          .map((entry) => String(entry.value ?? '').trim())
          .filter(Boolean)
          .map((entry) => entry.charAt(0).toUpperCase() + entry.slice(1))
  const trustStatus =
    currentUser.trustStatus ?? (locale === 'en' ? 'Basic account' : 'Βασικός λογαριασμός')
  const responseTime =
    currentUser.responseTime ??
    (locale === 'en'
      ? 'Response time will appear after your first conversations.'
      : 'Ο χρόνος απάντησης θα εμφανιστεί μετά τις πρώτες συνομιλίες σου.')
  const rating = Number(currentUser.rating ?? 0).toFixed(1)
  const isEditingCollection = editingCollectionId != null
  const showActivationNotice = searchParams.get('activation') === '1'
    const accessCopy = normalizeTextTree(
    locale === 'en'
      ? {
          eyebrow: 'Marketplace access',
          readyTitle: 'Your account is ready for protected buying and selling',
          blockedTitle: 'Before you buy or sell, complete the full activation checklist',
          description:
            'Cardora requires identity verification, address verification, IBAN verification, a Stripe connected account and complete private shipping details before all marketplace actions unlock.',
          warning:
            'Until everything is completed, buying, selling, bidding and raffle entries stay locked, and listings cannot remain visible.',
          welcome:
            'Your account was created successfully. The next step is to complete verification and create your Stripe connected account.',
          verificationButton: 'Open verification center',
          dashboardButton: 'Open seller dashboard / Stripe setup',
          ordersButton: 'Open orders',
        }
      : {
          eyebrow: 'Πρόσβαση στο marketplace',
          readyTitle: 'Ο λογαριασμός σου είναι έτοιμος για protected αγορές και πωλήσεις',
          blockedTitle: 'Πριν αγοράσεις ή πουλήσεις, ολοκλήρωσε όλο το activation checklist',
          description:
            'Η Cardora απαιτεί επαλήθευση ταυτότητας, διεύθυνσης, IBAN, Stripe Connected Account και πλήρη ιδιωτικά στοιχεία αποστολής πριν ξεκλειδώσουν όλες οι marketplace ενέργειες.',
          warning:
            'Μέχρι να ολοκληρωθούν όλα, αγορές, πωλήσεις, bids και συμμετοχές σε κληρώσεις παραμένουν κλειδωμένα, ενώ οι αγγελίες δεν μπορούν να παραμένουν ορατές.',
          welcome:
            'Ο λογαριασμός σου δημιουργήθηκε κανονικά. Επόμενο βήμα είναι να ολοκληρώσεις verification και να δημιουργήσεις Stripe connected account.',
          verificationButton: 'Άνοιγμα verification center',
          dashboardButton: 'Άνοιγμα seller dashboard / Stripe setup',
          ordersButton: 'Άνοιγμα παραγγελιών',
        }
  )

    const copy = normalizeTextTree(
    locale === 'en'
      ? {
          eyebrow: 'Profile',
          title: 'Your collector profile',
          description:
            'Manage your public collector identity, keep your verification on track and stay close to the activity tied to your account.',
          likes: 'Profile likes',
          followers: 'Followers',
          following: 'Following',
          trustLine: 'Email, public profile and verification center available',
          publicProfile: 'Open public collection',
          verificationTitle: 'Verification',
          verificationText:
            'Review what has already been approved and what is still needed before you unlock full selling and payout access.',
          manageVerification: 'Verification center',
          status: 'Status',
          progress: 'Progress',
          payout: 'Payout status',
          raffleTitle: 'Private raffle studio',
          raffleText:
            'Organize your own raffles from your account and send them for review before they go public.',
          openStudio: 'Open studio',
          privateSetup: 'Private setup',
          privateSetupText:
            'Prize, entry price, available slots and limits per member stay under your control.',
          reviewFlow: 'Review',
          reviewFlowText: 'Cardora reviews every raffle before it goes live.',
          fairDraw: 'System draw',
          fairDrawText: 'The final draw is executed by the Cardora system.',
          editTitle: 'Profile details',
          displayName: 'Nickname',
          city: 'City',
          bio: 'Bio',
          favoriteCategories: 'Favorite categories',
          listed: 'Listings',
          sold: 'Sales',
          purchased: 'Purchases',
          activity: 'Recent activity',
          noFavoriteCategories: 'No favorite categories have been added yet.',
          noActivity:
            'Your recent activity will appear here once you start browsing, buying or listing items.',
          collectionEyebrow: 'Collection management',
          collectionTitle: 'Personal collection gallery',
          collectionDescription:
            'Only pieces you keep in your collection belong here. They are not displayed as listings for sale.',
          addCollectionEntry: 'Add collection piece',
          editCollectionEntry: 'Edit',
          removeCollectionEntry: 'Delete',
          createFormTitle: 'New collection piece',
          editFormTitle: 'Edit piece',
          titleField: 'Title',
          captionField: 'Caption / note',
          visibilityField: 'Visibility',
          sortOrderField: 'Display order',
          featuredField: 'Featured item',
          mediaField: 'Photos',
          mediaHint: 'Upload 1-6 clear photos for your personal gallery.',
          saveNew: 'Create',
          saveChanges: 'Save',
          cancelEdit: 'Cancel',
          visibilityPublic: 'Public',
          visibilityPrivate: 'Private',
          noCollectionItems: 'There are no collection items yet. Add your first piece.',
          noImage: 'No image',
          featuredBadge: 'Featured',
          linkedProduct: 'Linked listing snapshot',
          selectedFiles: 'Selected files',
          existingMedia: 'Current photos',
          removePhoto: 'Remove',
          titleRequired: 'Add a title for your collection piece.',
          saveFailed: 'Saving failed. Try again.',
          deleteFailed: 'Deletion failed. Try again.',
          createdNotice: 'The collection piece was added.',
          updatedNotice: 'Changes were saved.',
          deletedNotice: 'The collection piece was deleted.',
          deleteConfirm: 'Delete this piece from your collection?',
        }
      : {
          eyebrow: 'Προφίλ',
          title: 'Το συλλεκτικό σου προφίλ',
          description:
            'Διαχειρίσου τη δημόσια συλλεκτική σου εικόνα, παρακολούθησε την επαλήθευση και μείνε κοντά στη δραστηριότητα του λογαριασμού σου.',
          likes: 'Likes προφίλ',
          followers: 'Ακόλουθοι',
          following: 'Ακολουθεί',
          trustLine: 'Email, δημόσιο προφίλ και κέντρο επαλήθευσης διαθέσιμα',
          publicProfile: 'Άνοιγμα δημόσιας συλλογής',
          verificationTitle: 'Επαλήθευση',
          verificationText:
            'Δες τι έχει ήδη εγκριθεί και τι χρειάζεται ακόμη πριν αποκτήσεις πλήρη πρόσβαση σε πωλήσεις και αναλήψεις.',
          manageVerification: 'Κέντρο επαλήθευσης',
          status: 'Κατάσταση',
          progress: 'Πρόοδος',
          payout: 'Κατάσταση payout',
          raffleTitle: 'Ιδιωτική δημιουργία κλήρωσης',
          raffleText:
            'Οργάνωσε τις δικές σου κληρώσεις μέσα από τον λογαριασμό σου και στείλε τες για έλεγχο πριν δημοσιευτούν.',
          openStudio: 'Άνοιγμα δημιουργίας',
          privateSetup: 'Ιδιωτικό setup',
          privateSetupText:
            'Έπαθλο, τιμή συμμετοχής, διαθέσιμες θέσεις και όρια ανά μέλος.',
          reviewFlow: 'Review',
          reviewFlowText: 'Η Cardora ελέγχει κάθε κλήρωση πριν γίνει δημόσια.',
          fairDraw: 'System draw',
          fairDrawText: 'Η τελική κλήρωση γίνεται από το σύστημα της Cardora.',
          editTitle: 'Στοιχεία προφίλ',
          displayName: 'Nickname',
          city: 'Πόλη',
          bio: 'Bio',
          favoriteCategories: 'Αγαπημένες κατηγορίες',
          listed: 'Αγγελίες',
          sold: 'Πωλήσεις',
          purchased: 'Αγορές',
          activity: 'Πρόσφατη δραστηριότητα',
          noFavoriteCategories: 'Δεν έχουν προστεθεί ακόμη αγαπημένες κατηγορίες.',
          noActivity:
            'Η πρόσφατη δραστηριότητά σου θα εμφανιστεί εδώ μόλις αρχίσεις να χαζεύεις, να αγοράζεις ή να καταχωρίζεις αντικείμενα.',
          collectionEyebrow: 'Διαχείριση συλλογής',
          collectionTitle: 'Gallery προσωπικής συλλογής',
          collectionDescription:
            'Εδώ μπαίνουν μόνο κομμάτια που κρατάς στη συλλογή σου. Δεν εμφανίζονται ως αγγελίες προς πώληση.',
          addCollectionEntry: 'Προσθήκη κομματιού',
          editCollectionEntry: 'Επεξεργασία',
          removeCollectionEntry: 'Διαγραφή',
          createFormTitle: 'Νέο κομμάτι συλλογής',
          editFormTitle: 'Επεξεργασία κομματιού',
          titleField: 'Τίτλος',
          captionField: 'Λεζάντα / σημείωση',
          visibilityField: 'Ορατότητα',
          sortOrderField: 'Σειρά εμφάνισης',
          featuredField: 'Featured κομμάτι',
          mediaField: 'Φωτογραφίες',
          mediaHint: 'Ανέβασε 1-6 καθαρές φωτογραφίες για το προσωπικό σου gallery.',
          saveNew: 'Δημιουργία',
          saveChanges: 'Αποθήκευση',
          cancelEdit: 'Ακύρωση',
          visibilityPublic: 'Δημόσιο',
          visibilityPrivate: 'Ιδιωτικό',
          noCollectionItems: 'Δεν υπάρχουν ακόμη κομμάτια συλλογής. Πρόσθεσε το πρώτο σου.',
          noImage: 'Χωρίς εικόνα',
          featuredBadge: 'Featured',
          linkedProduct: 'Σύνδεση με listing snapshot',
          selectedFiles: 'Επιλεγμένα αρχεία',
          existingMedia: 'Τωρινές φωτογραφίες',
          removePhoto: 'Αφαίρεση',
          titleRequired: 'Συμπλήρωσε τίτλο για το κομμάτι της συλλογής σου.',
          saveFailed: 'Δεν έγινε αποθήκευση. Δοκίμασε ξανά.',
          deleteFailed: 'Δεν έγινε διαγραφή. Δοκίμασε ξανά.',
          createdNotice: 'Το κομμάτι συλλογής προστέθηκε.',
          updatedNotice: 'Οι αλλαγές αποθηκεύτηκαν.',
          deletedNotice: 'Το κομμάτι συλλογής διαγράφηκε.',
          deleteConfirm: 'Να διαγραφεί αυτό το κομμάτι από τη συλλογή;',
        }
  )

  const resetCollectionForm = ({ clearFeedback = false } = {}) => {
    setEditingCollectionId(null)
    setCollectionForm(createInitialCollectionForm())
    setFileInputKey((previous) => previous + 1)

    if (clearFeedback) {
      setCollectionFeedback(null)
    }
  }

  const startCollectionEdit = (entry) => {
    setEditingCollectionId(entry.id)
    setCollectionFeedback(null)
    setCollectionForm({
      title: entry.title ?? entry.product?.title ?? '',
      caption: entry.caption ?? '',
      visibility: entry.visibility === 'private' ? 'private' : 'public',
      sortOrder: Number(entry.sortOrder ?? 0),
      isFeatured: Boolean(entry.isFeatured),
      media: Array.isArray(entry.media) ? entry.media.filter(Boolean) : [],
      mediaFiles: [],
      productId: entry.productId ?? null,
    })
    setFileInputKey((previous) => previous + 1)
  }

  const handleCollectionSubmit = async (event) => {
    event.preventDefault()

    const title = String(collectionForm.title ?? '').trim()
    if (!title) {
      setCollectionFeedback({
        tone: 'danger',
        text: copy.titleRequired,
      })
      return
    }

    setCollectionBusy(true)
    setCollectionFeedback(null)

    try {
      const payload = {
        title,
        caption: String(collectionForm.caption ?? '').trim(),
        visibility: collectionForm.visibility === 'private' ? 'private' : 'public',
        sortOrder: Number(collectionForm.sortOrder ?? 0),
        isFeatured: Boolean(collectionForm.isFeatured),
        media: Array.isArray(collectionForm.media) ? collectionForm.media.filter(Boolean) : [],
        mediaFiles: Array.isArray(collectionForm.mediaFiles)
          ? collectionForm.mediaFiles.filter(Boolean)
          : [],
        productId: collectionForm.productId ?? null,
      }

      if (isEditingCollection) {
        await updateCollectionEntry(editingCollectionId, payload)
      } else {
        await createCollectionEntry(payload)
      }

      resetCollectionForm()
      setCollectionFeedback({
        tone: 'success',
        text: isEditingCollection ? copy.updatedNotice : copy.createdNotice,
      })
    } catch (error) {
      setCollectionFeedback({
        tone: 'danger',
        text: error?.message || copy.saveFailed,
      })
    } finally {
      setCollectionBusy(false)
    }
  }

  const handleCollectionDelete = async (entryId) => {
    if (!entryId || collectionDeletingId || collectionBusy) return
    if (!window.confirm(copy.deleteConfirm)) return

    setCollectionDeletingId(entryId)
    setCollectionFeedback(null)

    try {
      await deleteCollectionEntry(entryId)

      if (editingCollectionId === entryId) {
        resetCollectionForm()
      }

      setCollectionFeedback({
        tone: 'success',
        text: copy.deletedNotice,
      })
    } catch (error) {
      setCollectionFeedback({
        tone: 'danger',
        text: error?.message || copy.deleteFailed,
      })
    } finally {
      setCollectionDeletingId(null)
    }
  }

  const handleCollectionMediaRemove = (indexToRemove) => {
    setCollectionForm((previous) => ({
      ...previous,
      media: previous.media.filter((_, index) => index !== indexToRemove),
    }))
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <CardSurface className="mb-8 border-gold-300/18 bg-gold-300/10">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="max-w-3xl">
            <p className="text-[11px] uppercase tracking-[0.3em] text-gold-100">
              {accessCopy.eyebrow}
            </p>
            <h2 className="mt-3 text-3xl font-semibold text-white">
              {marketplaceAccess?.is_marketplace_ready ? accessCopy.readyTitle : accessCopy.blockedTitle}
            </h2>
            <p className="mt-3 text-sm leading-7 text-gold-50">{accessCopy.description}</p>
            {showActivationNotice ? (
              <div className="mt-4 rounded-2xl border border-gold-300/20 bg-[#0d1523] px-4 py-3 text-sm leading-7 text-gold-50">
                {accessCopy.welcome}
              </div>
            ) : null}
            <p className="mt-4 text-sm leading-7 text-white/78">{accessCopy.warning}</p>
          </div>

          <Badge tone={marketplaceAccess?.is_marketplace_ready ? 'success' : 'warning'}>
            {(marketplaceAccess?.completion_percentage ?? 0)}%
          </Badge>
        </div>

        <div className="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          {(marketplaceAccess?.requirements ?? []).map((requirement) => (
            <div key={requirement.key} className="rounded-2xl border border-white/10 bg-[#0d1523] p-4">
              <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{requirement.label}</p>
              <p className="mt-2 text-sm font-semibold text-white">{requirement.status_label}</p>
              <p className="mt-2 text-xs leading-6 text-mist">{requirement.description}</p>
            </div>
          ))}
        </div>

        <div className="mt-6 flex flex-wrap gap-3">
          <Button as={Link} to="/epalithefsi-logariasmou">
            {accessCopy.verificationButton}
          </Button>
          <Button as={Link} to="/dashboard-politi" variant="secondary">
            {accessCopy.dashboardButton}
          </Button>
          <Button as={Link} to="/paraggelies" variant="ghost">
            {accessCopy.ordersButton}
          </Button>
        </div>
      </CardSurface>

      <div className="grid gap-8 xl:grid-cols-[0.85fr,1.15fr]">
        <CardSurface>
          <div className="flex items-center gap-4">
            <UserAvatar
              user={currentUser}
              size="lg"
              className="h-20 w-20 text-2xl"
              ringClassName="border-transparent"
            />
            <div>
              <h2 className="text-2xl font-semibold text-ink">{getUserDisplayName(currentUser)}</h2>
              <p className="text-mist">{currentUser.city || '-'}</p>
            </div>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <Badge tone="success">{trustStatus}</Badge>
            <Badge tone="gold">{rating} rating</Badge>
            {accountVerification ? <Badge tone="info">{accountVerification.overallStatus}</Badge> : null}
          </div>

          <p className="mt-4 text-sm leading-7 text-mist">{currentUser.bio || currentUser.collectorTagline || '-'}</p>

          {publicProfile ? (
            <div className="mt-5 grid gap-3 sm:grid-cols-3">
              <div className="rounded-[20px] border border-gold-200 bg-white p-4 shadow-[0_18px_40px_rgba(148,114,44,0.08)]">
                <p className="text-[11px] uppercase tracking-[0.28em] text-[#8d7a58]">{copy.likes}</p>
                <p className="mt-2 text-lg font-semibold text-ink">{publicProfile.likeCount}</p>
              </div>
              <div className="rounded-[20px] border border-gold-200 bg-white p-4 shadow-[0_18px_40px_rgba(148,114,44,0.08)]">
                <p className="text-[11px] uppercase tracking-[0.28em] text-[#8d7a58]">{copy.followers}</p>
                <p className="mt-2 text-lg font-semibold text-ink">{publicProfile.followerCount ?? 0}</p>
              </div>
              <div className="rounded-[20px] border border-gold-200 bg-white p-4 shadow-[0_18px_40px_rgba(148,114,44,0.08)]">
                <p className="text-[11px] uppercase tracking-[0.28em] text-[#8d7a58]">{copy.following}</p>
                <p className="mt-2 text-lg font-semibold text-ink">{publicProfile.followingCount ?? 0}</p>
              </div>
            </div>
          ) : null}

          <div className="mt-6 space-y-3">
            <div className="flex items-center gap-3 text-sm text-ink/80">
              <Mail className="h-4 w-4 text-gold-100" />
              {currentUser.email}
            </div>
            <div className="flex items-center gap-3 text-sm text-ink/80">
              <ShieldCheck className="h-4 w-4 text-gold-100" />
              {copy.trustLine}
            </div>
            <div className="flex items-center gap-3 text-sm text-ink/80">
              <Star className="h-4 w-4 text-gold-100" />
              {responseTime}
            </div>
          </div>

          {publicProfile ? (
            <div className="mt-5 flex flex-wrap gap-2">
              <Button
                as={Link}
                to={getCollectorProfileRoute(publicProfile.handle)}
                variant="secondary"
              >
                {copy.publicProfile}
              </Button>
              <Button
                as={Link}
                to={`${getCollectorProfileRoute(publicProfile.handle)}?connections=followers`}
                variant="secondary"
              >
                {locale === 'en' ? 'Followers & following' : 'Ακόλουθοι & ακολουθεί'}
              </Button>
            </div>
          ) : null}
        </CardSurface>

        <div className="space-y-8">
          <CardSurface>
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 className="font-display text-3xl text-white">{copy.verificationTitle}</h3>
                <p className="mt-2 text-sm leading-7 text-mist">{copy.verificationText}</p>
              </div>
              <Button as={Link} to="/epalithefsi-logariasmou" variant="secondary" size="sm">
                {copy.manageVerification}
              </Button>
            </div>

            {accountVerification ? (
              <div className="mt-5 grid gap-4 md:grid-cols-3">
                <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
                  <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{copy.status}</p>
                  <p className="mt-2 text-lg font-semibold text-white">{accountVerification.overallStatus}</p>
                </div>
                <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
                  <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{copy.progress}</p>
                  <p className="mt-2 text-lg font-semibold text-white">
                    {accountVerification.progressPercentage}%
                  </p>
                </div>
                <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
                  <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{copy.payout}</p>
                  <p className="mt-2 text-sm font-semibold text-white">{accountVerification.payoutStatus}</p>
                </div>
              </div>
            ) : null}
          </CardSurface>

          <CardSurface>
            <ProfileAppearanceEditor />
          </CardSurface>

          <CardSurface>
            <h3 className="font-display text-3xl text-white">{copy.favoriteCategories}</h3>
            <div className="mt-4 flex flex-wrap gap-2">
              {displayFavoriteCategories.length ? (
                displayFavoriteCategories.map((item) => (
                  <Badge key={item} tone="gold">
                    {item}
                  </Badge>
                ))
              ) : (
                <p className="text-sm text-mist">{copy.noFavoriteCategories}</p>
              )}
            </div>
            <div className="mt-6 grid gap-3 md:grid-cols-3">
              <div className="rounded-[24px] border border-white/8 bg-white/5 p-4">
                <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.listed}</p>
                <p className="mt-2 text-2xl font-semibold text-white">{listedCount}</p>
              </div>
              <div className="rounded-[24px] border border-white/8 bg-white/5 p-4">
                <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.sold}</p>
                <p className="mt-2 text-2xl font-semibold text-white">{soldCount}</p>
              </div>
              <div className="rounded-[24px] border border-white/8 bg-white/5 p-4">
                <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.purchased}</p>
                <p className="mt-2 text-2xl font-semibold text-white">{purchasedCount}</p>
              </div>
            </div>
          </CardSurface>

          <CardSurface>
            <h3 className="font-display text-3xl text-white">{copy.activity}</h3>
            <div className="mt-4 space-y-3">
              {recentActivity.length ? (
                recentActivity.map((item) => (
                  <div
                    key={item}
                    className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm text-white/80"
                  >
                    {item}
                  </div>
                ))
              ) : (
                <div className="rounded-2xl border border-dashed border-white/10 bg-white/5 px-4 py-4 text-sm text-mist">
                  {copy.noActivity}
                </div>
              )}
            </div>
          </CardSurface>
        </div>
      </div>

      <section className="mt-20 grid gap-8 xl:grid-cols-[1.1fr,0.9fr]">
        <div>
          <SectionHeader
            eyebrow={copy.collectionEyebrow}
            title={copy.collectionTitle}
            description={copy.collectionDescription}
          />

          <div className="mb-4 flex justify-end">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => resetCollectionForm({ clearFeedback: true })}
            >
              <Plus className="h-4 w-4" />
              {copy.addCollectionEntry}
            </Button>
          </div>

          {collectionFeedback ? (
            <div
              className={`mb-4 rounded-xl border px-4 py-3 text-sm ${
                collectionFeedback.tone === 'success'
                  ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-800'
                  : 'border-rose-400/25 bg-rose-500/10 text-rose-800'
              }`}
            >
              {collectionFeedback.text}
            </div>
          ) : null}

          {collectionEntries.length ? (
            <div className="grid gap-4 sm:grid-cols-2">
              {collectionEntries.map((entry) => {
                const media = getEntryMedia(entry)
                const primaryMediaUrl = getMediaUrl(media[0])
                const subtitle = getEntrySubtitle(entry)

                return (
                  <CardSurface key={entry.id} className="overflow-hidden p-3">
                    <div className="relative overflow-hidden rounded-[18px] border border-white/10 bg-[#081324] p-2">
                      {primaryMediaUrl ? (
                        <img
                          src={primaryMediaUrl}
                          alt={entry.title || copy.noImage}
                          className="h-48 w-full rounded-xl bg-[#050d1a] object-contain"
                          loading="lazy"
                        />
                      ) : (
                        <div className="flex h-48 w-full items-center justify-center bg-white/5 text-sm text-mist">
                          {copy.noImage}
                        </div>
                      )}
                    </div>

                    <div className="mt-3 flex flex-wrap gap-2">
                      <Badge tone={entry.visibility === 'private' ? 'muted' : 'info'}>
                        {entry.visibility === 'private' ? copy.visibilityPrivate : copy.visibilityPublic}
                      </Badge>
                      {entry.isFeatured ? <Badge tone="gold">{copy.featuredBadge}</Badge> : null}
                      {entry.productId ? <Badge tone="warning">{copy.linkedProduct}</Badge> : null}
                    </div>

                    <p className="mt-3 text-sm font-semibold text-white">{entry.title || '-'}</p>
                    {subtitle ? <p className="mt-1 line-clamp-2 text-xs text-mist">{subtitle}</p> : null}

                    {entry.product?.price != null ? (
                      <p className="mt-2 text-sm font-semibold text-gold-100">
                        {formatCurrency(Number(entry.product.price))}
                      </p>
                    ) : null}

                    <div className="mt-4 flex gap-2">
                      <Button
                        variant="secondary"
                        size="sm"
                        className="flex-1"
                        onClick={() => startCollectionEdit(entry)}
                        disabled={collectionBusy}
                      >
                        <PencilLine className="h-4 w-4" />
                        {copy.editCollectionEntry}
                      </Button>
                      <Button
                        variant="danger"
                        size="sm"
                        className="flex-1"
                        onClick={() => handleCollectionDelete(entry.id)}
                        disabled={collectionBusy || collectionDeletingId === entry.id}
                      >
                        {collectionDeletingId === entry.id ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <Trash2 className="h-4 w-4" />
                        )}
                        {copy.removeCollectionEntry}
                      </Button>
                    </div>
                  </CardSurface>
                )
              })}
            </div>
          ) : (
            <CardSurface hover={false}>
              <p className="text-sm text-mist">{copy.noCollectionItems}</p>
            </CardSurface>
          )}
        </div>

        <CardSurface>
          <h3 className="font-display text-3xl text-white">
            {isEditingCollection ? copy.editFormTitle : copy.createFormTitle}
          </h3>

          <form className="mt-5 space-y-4" onSubmit={handleCollectionSubmit}>
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.titleField}</label>
              <Input
                value={collectionForm.title}
                onChange={(event) =>
                  setCollectionForm((previous) => ({ ...previous, title: event.target.value }))
                }
                placeholder={
                  locale === 'en' ? 'e.g. Personal grail binder page' : 'π.χ. Σελίδα binder με προσωπικά grails'
                }
                required
              />
            </div>

            <div>
              <label className="mb-2 block text-sm text-mist">{copy.captionField}</label>
              <Textarea
                className="min-h-[110px]"
                value={collectionForm.caption}
                onChange={(event) =>
                  setCollectionForm((previous) => ({ ...previous, caption: event.target.value }))
                }
                placeholder={
                  locale === 'en' ? 'Short context about this piece in your collection.' : 'Σύντομο context για το κομμάτι της συλλογής σου.'
                }
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="mb-2 block text-sm text-mist">{copy.visibilityField}</label>
                <Select
                  value={collectionForm.visibility}
                  onChange={(event) =>
                    setCollectionForm((previous) => ({
                      ...previous,
                      visibility: event.target.value === 'private' ? 'private' : 'public',
                    }))
                  }
                >
                  <option value="public">{copy.visibilityPublic}</option>
                  <option value="private">{copy.visibilityPrivate}</option>
                </Select>
              </div>
              <div>
                <label className="mb-2 block text-sm text-mist">{copy.sortOrderField}</label>
                <Input
                  type="number"
                  min={0}
                  step={1}
                  value={collectionForm.sortOrder}
                  onChange={(event) =>
                    setCollectionForm((previous) => ({
                      ...previous,
                      sortOrder: Number(event.target.value || 0),
                    }))
                  }
                />
              </div>
            </div>

            <label className="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm text-white/85">
              <input
                type="checkbox"
                checked={Boolean(collectionForm.isFeatured)}
                onChange={(event) =>
                  setCollectionForm((previous) => ({
                    ...previous,
                    isFeatured: event.target.checked,
                  }))
                }
                className="h-4 w-4 rounded border-white/30 bg-transparent text-gold-300 focus:ring-gold-300/30"
              />
              {copy.featuredField}
            </label>

            <div>
              <div className="mb-2 flex items-center gap-2 text-sm text-mist">
                <ImagePlus className="h-4 w-4 text-gold-100" />
                {copy.mediaField}
              </div>
              <p className="mb-3 text-xs text-mist">{copy.mediaHint}</p>
              <Input
                key={fileInputKey}
                type="file"
                accept="image/*"
                multiple
                onChange={(event) =>
                  setCollectionForm((previous) => ({
                    ...previous,
                    mediaFiles: Array.from(event.target.files ?? []),
                  }))
                }
              />
            </div>

            {collectionForm.media.length ? (
              <div>
                <p className="mb-2 text-xs uppercase tracking-[0.24em] text-white/45">{copy.existingMedia}</p>
                <div className="grid gap-2 sm:grid-cols-2">
                  {collectionForm.media.map((mediaItem, index) => {
                    const mediaUrl = getMediaUrl(mediaItem)

                    return (
                      <div
                        key={`${mediaUrl ?? index}-${index}`}
                        className="rounded-xl border border-white/10 bg-white/5 p-2"
                      >
                        {mediaUrl ? (
                          <img
                            src={mediaUrl}
                            alt={`collection-media-${index + 1}`}
                            className="h-24 w-full rounded-lg object-cover"
                          />
                        ) : (
                          <div className="flex h-24 w-full items-center justify-center rounded-lg bg-white/5 text-xs text-mist">
                            {copy.noImage}
                          </div>
                        )}
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="mt-2 w-full"
                          onClick={() => handleCollectionMediaRemove(index)}
                          disabled={collectionBusy}
                        >
                          {copy.removePhoto}
                        </Button>
                      </div>
                    )
                  })}
                </div>
              </div>
            ) : null}

            {collectionForm.mediaFiles.length ? (
              <div>
                <p className="mb-2 text-xs uppercase tracking-[0.24em] text-white/45">{copy.selectedFiles}</p>
                <div className="space-y-1">
                  {collectionForm.mediaFiles.map((file) => (
                    <p key={`${file.name}-${file.size}`} className="text-xs text-white/80">
                      {file.name}
                    </p>
                  ))}
                </div>
              </div>
            ) : null}

            <div className="flex flex-wrap gap-2 pt-2">
              <Button type="submit" disabled={collectionBusy}>
                {collectionBusy ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Plus className="h-4 w-4" />
                )}
                {isEditingCollection ? copy.saveChanges : copy.saveNew}
              </Button>

              {isEditingCollection ? (
                <>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => resetCollectionForm({ clearFeedback: true })}
                    disabled={collectionBusy}
                  >
                    {copy.cancelEdit}
                  </Button>
                  <Button
                    type="button"
                    variant="danger"
                    onClick={() => handleCollectionDelete(editingCollectionId)}
                    disabled={collectionBusy || collectionDeletingId === editingCollectionId}
                  >
                    {collectionDeletingId === editingCollectionId ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Trash2 className="h-4 w-4" />
                    )}
                    {copy.removeCollectionEntry}
                  </Button>
                </>
              ) : null}
            </div>
          </form>
        </CardSurface>
      </section>
    </div>
  )
}

export default ProfilePage

