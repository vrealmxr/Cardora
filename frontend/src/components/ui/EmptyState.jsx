import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'

function EmptyState({ title, description, actionLabel, onAction }) {
  return (
    <CardSurface className="text-center">
      <div className="mx-auto flex max-w-xl flex-col items-center gap-4 py-8">
        <div className="rounded-full border border-gold-300/20 bg-gold-300/10 px-4 py-1 text-xs font-semibold uppercase tracking-[0.3em] text-gold-100">
          Cardora
        </div>
        <h3 className="font-display text-3xl text-white">{title}</h3>
        <p className="text-mist">{description}</p>
        {actionLabel ? (
          <Button variant="secondary" onClick={onAction}>
            {actionLabel}
          </Button>
        ) : null}
      </div>
    </CardSurface>
  )
}

export default EmptyState
