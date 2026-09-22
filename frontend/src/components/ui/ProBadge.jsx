import { Crown } from 'lucide-react'
import Badge from '@/components/ui/Badge'
import { cn } from '@/utils/helpers'

// Small reusable "PRO" marker for profiles, seller cards and listing
// bylines — anywhere a Cardora PRO seller should stand out.
function ProBadge({ className }) {
  return (
    <Badge tone="gold" className={cn('gap-1', className)}>
      <Crown className="h-3 w-3" />
      PRO
    </Badge>
  )
}

export default ProBadge
