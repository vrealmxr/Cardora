import ProductCard from '@/components/catalog/ProductCard'
import EmptyState from '@/components/ui/EmptyState'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'

function FavoritesPage() {
  const { locale } = useI18n()
  const { favoriteProducts } = useMarketplace()

  const copy =
    locale === 'en'
      ? {
          eyebrow: 'Favorites',
          title: 'Saved collectibles',
          description:
            'Keep track of the items you may want to compare, revisit or buy later.',
          emptyTitle: 'You have no favorites yet',
          emptyDescription: 'Save listings so you can return to them quickly later.',
        }
      : {
          eyebrow: 'Αγαπημένα',
          title: 'Αποθηκευμένα συλλεκτικά',
          description:
            'Κράτησε εδώ τα αντικείμενα που θέλεις να συγκρίνεις, να ξαναδείς ή να αγοράσεις αργότερα.',
          emptyTitle: 'Δεν έχεις αγαπημένα ακόμη',
          emptyDescription: 'Αποθήκευσε αγγελίες για να επιστρέφεις πιο εύκολα σε αυτές.',
        }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />
      {favoriteProducts.length ? (
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {favoriteProducts.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      ) : (
        <EmptyState title={copy.emptyTitle} description={copy.emptyDescription} />
      )}
    </div>
  )
}

export default FavoritesPage
