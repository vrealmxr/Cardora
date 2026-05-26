import { useEffect, useState } from 'react'
import { cn, getInitials, getUserDisplayName } from '@/utils/helpers'

function UserAvatar({
  user,
  size = 'md',
  className,
  ringClassName,
  textClassName = 'text-slate-950',
}) {
  const imageUrl = user?.avatar?.imageUrl ?? user?.avatarUrl ?? user?.avatar_url ?? null
  const from = user?.avatar?.from ?? '#f2cb70'
  const to = user?.avatar?.to ?? '#21456e'
  const [imageBroken, setImageBroken] = useState(false)

  useEffect(() => {
    setImageBroken(false)
  }, [imageUrl])

  const hasImage = Boolean(imageUrl && !imageBroken)
  const sizeClassName =
    size === 'xs'
      ? 'h-7 w-7 text-[11px]'
      : size === 'sm'
        ? 'h-9 w-9 text-xs'
        : size === 'lg'
          ? 'h-20 w-20 text-2xl'
          : 'h-12 w-12 text-base'

  const resolvedRingClassName =
    ringClassName ?? (hasImage ? 'border-white/10 shadow-[0_0_0_1px_rgba(255,255,255,0.02)]' : 'border-navy-950')

  return (
    <div
      className={cn(
        'relative flex shrink-0 items-center justify-center overflow-hidden rounded-full font-bold',
        hasImage ? 'border bg-[#0b1323]' : 'border-4',
        sizeClassName,
        resolvedRingClassName,
        hasImage ? 'text-white' : textClassName,
        className,
      )}
      style={
        hasImage
          ? undefined
          : {
              background: `linear-gradient(135deg, ${from}, ${to})`,
            }
      }
    >
      {hasImage ? (
        <img
          src={imageUrl}
          alt={getUserDisplayName(user) || 'User avatar'}
          className="h-full w-full object-cover"
          onError={() => setImageBroken(true)}
        />
      ) : (
        <span>{getInitials(getUserDisplayName(user))}</span>
      )}
    </div>
  )
}

export default UserAvatar
