import { SlidersHorizontal } from 'lucide-react'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { useI18n } from '@/hooks/useI18n'
import { cn } from '@/utils/helpers'

function FilterGroup({ group, value, onChange }) {
  if (!group?.options?.length) return null

  return (
    <div className="space-y-2.5">
      <p className="text-sm font-semibold text-white">{group.label}</p>
      <div className="flex flex-wrap gap-2">
        {group.options.map((option) => (
          <button
            key={`${group.key}-${option.value}`}
            type="button"
            disabled={option.disabled}
            onClick={() => onChange(group.key, option.value)}
            className={cn(
              'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition',
              value === option.value
                ? 'border-gold-300/40 bg-gold-300/18 text-gold-100'
                : 'border-white/10 bg-white/5 text-white/70 hover:border-white/20 hover:text-white',
              option.disabled && value !== option.value && 'cursor-not-allowed opacity-45',
            )}
          >
            <span>{option.label}</span>
            <span className="rounded-full bg-black/25 px-1.5 py-0.5 text-[10px] text-white/70">
              {option.count}
            </span>
          </button>
        ))}
      </div>
    </div>
  )
}

function FilterSidebar({ facetGroups = [], filters, onChange, onReset, className, spotlightFilters = [] }) {
  const { locale } = useI18n()

  const copy =
    locale === 'en'
      ? {
          title: 'Filters',
          subtitle: 'Smart facets based on currently matching listings',
          reset: 'Clear all',
        }
      : {
          title: '\u03a6\u03af\u03bb\u03c4\u03c1\u03b1',
          subtitle:
            '\u0394\u03c5\u03bd\u03b1\u03bc\u03b9\u03ba\u03ae \u03bf\u03bc\u03b1\u03b4\u03bf\u03c0\u03bf\u03af\u03b7\u03c3\u03b7 \u03bc\u03b5 \u03b2\u03ac\u03c3\u03b7 \u03c4\u03b1 \u03c4\u03c1\u03ad\u03c7\u03bf\u03bd\u03c4\u03b1 \u03b1\u03c0\u03bf\u03c4\u03b5\u03bb\u03ad\u03c3\u03bc\u03b1\u03c4\u03b1',
          reset: '\u039a\u03b1\u03b8\u03b1\u03c1\u03b9\u03c3\u03bc\u03cc\u03c2',
        }

  return (
    <CardSurface className={cn('space-y-5 p-[18px]', className)}>
      <div className="flex items-center justify-between gap-3">
        <div className="flex min-w-0 items-center gap-3">
          <div className="rounded-full border border-gold-300/20 bg-gold-300/10 p-2 text-gold-100">
            <SlidersHorizontal className="h-4 w-4" />
          </div>
          <div className="min-w-0">
            <h3 className="truncate font-display text-[1.7rem] text-white">{copy.title}</h3>
            <p className="text-sm text-mist">{copy.subtitle}</p>
          </div>
        </div>
        <Button variant="ghost" size="sm" onClick={onReset}>
          {copy.reset}
        </Button>
      </div>

      {spotlightFilters.length ? (
        <div className="flex flex-wrap gap-2">
          {spotlightFilters.map((item) => (
            <span
              key={item}
              className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] text-white/72"
            >
              {item}
            </span>
          ))}
        </div>
      ) : null}

      {facetGroups.map((group) => (
        <FilterGroup
          key={group.key}
          group={group}
          value={filters[group.key]}
          onChange={onChange}
        />
      ))}
    </CardSurface>
  )
}

export default FilterSidebar

