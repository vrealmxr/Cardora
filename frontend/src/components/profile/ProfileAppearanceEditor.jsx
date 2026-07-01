import { Camera, Loader2, PaintBucket, Save, Trash2 } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import UserAvatar from '@/components/people/UserAvatar'
import Button from '@/components/ui/Button'
import { Input, Textarea } from '@/components/ui/Input'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { cardoraService } from '@/services/cardoraService'
import { getUserDisplayName } from '@/utils/helpers'

const PRESET_COLORS = ['#f2cb70', '#e08d5b', '#d95f75', '#9f67e5', '#537fe7', '#33a6c8', '#34b49a', '#7fb86e']
const DARK_NAVY = '#09111d'
const MID_NAVY = '#162238'

const clamp = (value, min, max) => Math.min(Math.max(value, min), max)

const normalizeHex = (value, fallback = '#f2cb70') => {
  const trimmed = String(value ?? '').trim()
  const normalized = trimmed.startsWith('#') ? trimmed : `#${trimmed}`
  return /^#[0-9a-fA-F]{6}$/.test(normalized) ? normalized.toUpperCase() : fallback
}

const hexToRgb = (hex) => {
  const normalized = normalizeHex(hex)
  const value = normalized.replace('#', '')

  return {
    r: Number.parseInt(value.slice(0, 2), 16),
    g: Number.parseInt(value.slice(2, 4), 16),
    b: Number.parseInt(value.slice(4, 6), 16),
  }
}

const rgbToHex = ({ r, g, b }) =>
  `#${[r, g, b]
    .map((channel) => clamp(Math.round(channel), 0, 255).toString(16).padStart(2, '0'))
    .join('')}`

const mixHex = (primary, secondary, ratio = 0.5) => {
  const start = hexToRgb(primary)
  const end = hexToRgb(secondary)
  const weight = clamp(ratio, 0, 1)

  return rgbToHex({
    r: start.r * (1 - weight) + end.r * weight,
    g: start.g * (1 - weight) + end.g * weight,
    b: start.b * (1 - weight) + end.b * weight,
  })
}

const createPalette = (baseColor) => {
  const from = normalizeHex(baseColor)

  return {
    from,
    via: mixHex(from, MID_NAVY, 0.62),
    to: DARK_NAVY,
  }
}

const buildInitialForm = (user) => {
  const baseColor = normalizeHex(user?.profile_cover?.palette?.from ?? '#f2cb70')
  const shippingOrigin = user?.shippingOrigin ?? user?.shipping_origin ?? {}

  return {
    displayName: user?.displayName ?? user?.display_name ?? user?.name ?? '',
    phone: user?.phone ?? '',
    city: user?.city ?? '',
    collectorTagline: user?.collector_tagline ?? user?.collectorTagline ?? '',
    bio: user?.bio ?? '',
    shipFromName: shippingOrigin?.full_name ?? user?.displayName ?? user?.display_name ?? user?.name ?? '',
    shipFromPhone: shippingOrigin?.phone ?? user?.phone ?? '',
    shipFromAddress1: shippingOrigin?.address_line_1 ?? '',
    shipFromAddress2: shippingOrigin?.address_line_2 ?? '',
    shipFromCity: shippingOrigin?.city ?? user?.city ?? '',
    shipFromPostalCode: shippingOrigin?.postal_code ?? '',
    shipFromCountry: shippingOrigin?.country ?? 'Greece',
    shipFromCountryCode: shippingOrigin?.country_code ?? 'GR',
    baseColor,
    avatarFile: null,
    removeAvatar: false,
  }
}

function ProfileAppearanceEditor() {
  const { locale } = useI18n()
  const { currentUser, refreshCurrentUser } = useAuth()
  const { refreshBootstrap } = useMarketplace()
  const [form, setForm] = useState(() => buildInitialForm(currentUser))
  const [busy, setBusy] = useState(false)
  const [feedback, setFeedback] = useState(null)

  useEffect(() => {
    setForm(buildInitialForm(currentUser))
  }, [currentUser])

  const copy =
    locale === 'en'
      ? {
          title: 'Profile appearance',
          description: 'Choose your collector color and profile photo, then keep your public card clean and recognisable.',
          photoLabel: 'Profile photo',
          photoHint: 'Square photos look best in your profile circle.',
          removePhoto: 'Remove photo',
          colorLabel: 'Header color',
          colorHint: 'This color appears on the public collector header.',
          customColor: 'Custom color',
          preview: 'Live preview',
          displayName: 'Nickname',
          phone: 'Phone',
          city: 'City',
          tagline: 'Collector line',
          bio: 'Bio',
          shippingTitle: 'Private shipping origin',
          shippingHint: 'These fields stay private and are used only for DHL label creation and courier paperwork.',
          shipFromName: 'Sender full name',
          shipFromPhone: 'Sender phone',
          shipFromAddress1: 'Address line 1',
          shipFromAddress2: 'Address line 2',
          shipFromPostalCode: 'Postal code',
          shipFromCountry: 'Country',
          shipFromCountryCode: 'Country code',
          save: 'Save profile',
          saving: 'Saving...',
          success: 'Your profile look was updated.',
          failed: 'We could not save your profile right now.',
        }
      : {
          title: 'Εμφάνιση προφίλ',
          description: 'Διάλεξε χρώμα συλλέκτη και φωτογραφία προφίλ, ώστε η δημόσια κάρτα σου να δείχνει καθαρή και αναγνωρίσιμη.',
          photoLabel: 'Φωτογραφία προφίλ',
          photoHint: 'Οι τετράγωνες φωτογραφίες ταιριάζουν καλύτερα στον κύκλο του προφίλ.',
          removePhoto: 'Αφαίρεση φωτογραφίας',
          colorLabel: 'Χρώμα header',
          colorHint: 'Αυτό το χρώμα εμφανίζεται στο επάνω μέρος του δημόσιου προφίλ σου.',
          customColor: 'Custom χρώμα',
          preview: 'Ζωντανή προεπισκόπηση',
          displayName: 'Nickname',
          city: 'Πόλη',
          tagline: 'Σύντομη γραμμή συλλέκτη',
          bio: 'Bio',
          save: 'Αποθήκευση προφίλ',
          saving: 'Αποθήκευση...',
          success: 'Η εμφάνιση του προφίλ σου ενημερώθηκε.',
          failed: 'Δεν μπορέσαμε να αποθηκεύσουμε το προφίλ αυτή τη στιγμή.',
        }

  const palette = useMemo(() => createPalette(form.baseColor), [form.baseColor])

  const avatarPreviewUrl = useMemo(() => {
    if (form.avatarFile) {
      return URL.createObjectURL(form.avatarFile)
    }

    if (form.removeAvatar) {
      return null
    }

    return currentUser?.avatar_url ?? null
  }, [currentUser?.avatar_url, form.avatarFile, form.removeAvatar])

  useEffect(
    () => () => {
      if (form.avatarFile) {
        URL.revokeObjectURL(avatarPreviewUrl)
      }
    },
    [avatarPreviewUrl, form.avatarFile],
  )

  const previewUser = useMemo(
    () => ({
      ...currentUser,
      displayName: form.displayName,
      display_name: form.displayName,
      city: form.city,
      bio: form.bio,
      collectorTagline: form.collectorTagline,
      collector_tagline: form.collectorTagline,
      avatar_url: avatarPreviewUrl,
      avatar: {
        from: palette.from,
        to: mixHex(palette.from, '#21456e', 0.55),
        imageUrl: avatarPreviewUrl,
      },
    }),
    [avatarPreviewUrl, currentUser, form.bio, form.city, form.collectorTagline, form.displayName, palette.from],
  )

  if (!currentUser) return null

  const handleSave = async (event) => {
    event.preventDefault()
    setBusy(true)
    setFeedback(null)

    try {
      let avatarUrl = form.removeAvatar ? null : currentUser.avatar_url ?? null

      if (form.avatarFile) {
        const uploads = await cardoraService.uploadFiles([form.avatarFile], 'avatars')
        avatarUrl = uploads?.[0]?.url ?? avatarUrl
      }

      const currentCover = currentUser.profile_cover && typeof currentUser.profile_cover === 'object'
        ? currentUser.profile_cover
        : {}

      await cardoraService.updateProfile({
        display_name: form.displayName.trim() || null,
        phone: form.phone.trim() || null,
        city: form.city.trim() || null,
        collector_tagline: form.collectorTagline.trim() || null,
        bio: form.bio.trim() || null,
        avatar_url: avatarUrl,
        shipping_origin: {
          full_name: form.shipFromName.trim() || null,
          phone: form.shipFromPhone.trim() || null,
          address_line_1: form.shipFromAddress1.trim() || null,
          address_line_2: form.shipFromAddress2.trim() || null,
          city: form.shipFromCity.trim() || null,
          postal_code: form.shipFromPostalCode.trim() || null,
          country: form.shipFromCountry.trim() || null,
          country_code: form.shipFromCountryCode.trim().toUpperCase() || null,
        },
        profile_cover: {
          ...currentCover,
          palette,
        },
      })

      await Promise.all([refreshCurrentUser(), refreshBootstrap()])
      setForm((previous) => ({
        ...previous,
        avatarFile: null,
        removeAvatar: false,
      }))
      setFeedback({ tone: 'success', text: copy.success })
    } catch (error) {
      setFeedback({
        tone: 'danger',
        text: error?.message || copy.failed,
      })
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-5">
      <div>
        <h3 className="font-display text-3xl text-white">{copy.title}</h3>
        <p className="mt-2 text-sm leading-7 text-mist">{copy.description}</p>
      </div>

      <div className="overflow-hidden rounded-[28px] border border-white/10 bg-[#081324]">
        <div
          className="h-28 w-full"
          style={{
            background: `linear-gradient(135deg, ${palette.from}, ${palette.via}, ${palette.to})`,
          }}
        />

        <div className="px-5 pb-5 pt-0 sm:px-6">
          <div className="-mt-10 flex items-end gap-4">
            <UserAvatar user={previewUser} size="lg" className="h-20 w-20 text-2xl" />
            <div className="pb-1">
              <p className="text-2xl font-semibold text-white">{form.displayName.trim() || getUserDisplayName(currentUser)}</p>
              <p className="mt-1 text-sm text-mist">@{currentUser.handle}</p>
              <p className="mt-2 text-sm text-white/80">{form.collectorTagline.trim() || form.bio.trim() || ' '}</p>
            </div>
          </div>

          <p className="mt-4 text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.preview}</p>
        </div>
      </div>

      {feedback ? (
        <div
          className={`rounded-xl border px-4 py-3 text-sm ${
            feedback.tone === 'success'
              ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100'
              : 'border-rose-400/25 bg-rose-500/10 text-rose-100'
          }`}
        >
          {feedback.text}
        </div>
      ) : null}

      <form className="space-y-5" onSubmit={handleSave}>
        <div className="grid gap-5 lg:grid-cols-[0.92fr,1.08fr]">
          <div className="rounded-[24px] border border-white/10 bg-white/5 p-4">
            <div className="flex items-center gap-2 text-sm font-semibold text-white">
              <Camera className="h-4 w-4 text-gold-100" />
              {copy.photoLabel}
            </div>
            <p className="mt-2 text-xs leading-6 text-mist">{copy.photoHint}</p>

            <div className="mt-4 flex items-center gap-4">
              <UserAvatar user={previewUser} size="lg" className="h-20 w-20 text-2xl" />
              <div className="flex flex-wrap gap-2">
                <label className="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-white/12 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-gold-300/40 hover:bg-white/10">
                  <Camera className="h-4 w-4" />
                  <span>{copy.photoLabel}</span>
                  <input
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={(event) => {
                      const file = event.target.files?.[0] ?? null
                      if (!file) return

                      setForm((previous) => ({
                        ...previous,
                        avatarFile: file,
                        removeAvatar: false,
                      }))
                    }}
                  />
                </label>

                {(currentUser.avatar_url || form.avatarFile) ? (
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={() =>
                      setForm((previous) => ({
                        ...previous,
                        avatarFile: null,
                        removeAvatar: true,
                      }))
                    }
                  >
                    <Trash2 className="h-4 w-4" />
                    {copy.removePhoto}
                  </Button>
                ) : null}
              </div>
            </div>
          </div>

          <div className="rounded-[24px] border border-white/10 bg-white/5 p-4">
            <div className="flex items-center gap-2 text-sm font-semibold text-white">
              <PaintBucket className="h-4 w-4 text-gold-100" />
              {copy.colorLabel}
            </div>
            <p className="mt-2 text-xs leading-6 text-mist">{copy.colorHint}</p>

            <div className="mt-4 flex flex-wrap gap-3">
              {PRESET_COLORS.map((color) => {
                const active = normalizeHex(form.baseColor) === normalizeHex(color)

                return (
                  <button
                    key={color}
                    type="button"
                    aria-label={`color-${color}`}
                    onClick={() => setForm((previous) => ({ ...previous, baseColor: color }))}
                    className={`h-10 w-10 rounded-full border-2 transition ${
                      active ? 'border-white shadow-gold-soft' : 'border-white/15'
                    }`}
                    style={{ backgroundColor: color }}
                  />
                )
              })}

              <label className="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                <span className="text-xs uppercase tracking-[0.18em] text-white/60">{copy.customColor}</span>
                <input
                  type="color"
                  value={normalizeHex(form.baseColor)}
                  className="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0"
                  onChange={(event) =>
                    setForm((previous) => ({
                      ...previous,
                      baseColor: normalizeHex(event.target.value),
                    }))
                  }
                />
              </label>
            </div>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <div>
            <label className="mb-2 block text-sm text-mist">{copy.displayName}</label>
            <Input
              value={form.displayName}
              onChange={(event) => setForm((previous) => ({ ...previous, displayName: event.target.value }))}
              maxLength={255}
            />
          </div>
          <div>
            <label className="mb-2 block text-sm text-mist">{copy.phone ?? (locale === 'en' ? 'Phone' : 'Τηλέφωνο')}</label>
            <Input
              value={form.phone}
              onChange={(event) => setForm((previous) => ({ ...previous, phone: event.target.value }))}
              maxLength={50}
            />
          </div>
          <div>
            <label className="mb-2 block text-sm text-mist">{copy.city}</label>
            <Input
              value={form.city}
              onChange={(event) => setForm((previous) => ({ ...previous, city: event.target.value }))}
              maxLength={255}
            />
          </div>
          <div className="md:col-span-2">
            <label className="mb-2 block text-sm text-mist">{copy.tagline}</label>
            <Input
              value={form.collectorTagline}
              onChange={(event) => setForm((previous) => ({ ...previous, collectorTagline: event.target.value }))}
              maxLength={255}
            />
          </div>
          <div className="md:col-span-2">
            <label className="mb-2 block text-sm text-mist">{copy.bio}</label>
            <Textarea
              className="min-h-[130px]"
              value={form.bio}
              onChange={(event) => setForm((previous) => ({ ...previous, bio: event.target.value }))}
            />
          </div>
        </div>

        <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
          <div className="flex flex-col gap-2">
            <h4 className="text-sm font-semibold text-white">
              {copy.shippingTitle ?? (locale === 'en' ? 'Private shipping origin' : 'Ιδιωτική διεύθυνση αποστολέα')}
            </h4>
            <p className="text-xs leading-6 text-mist">
              {copy.shippingHint ??
                (locale === 'en'
                  ? 'These fields stay private and are used only for DHL label creation and courier paperwork.'
                  : 'Τα στοιχεία αυτά μένουν ιδιωτικά και χρησιμοποιούνται μόνο για DHL labels και στοιχεία αποστολής.')}
            </p>
          </div>

          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <div>
              <label className="mb-2 block text-sm text-mist">
                {copy.shipFromName ?? (locale === 'en' ? 'Sender full name' : 'Ονοματεπώνυμο αποστολέα')}
              </label>
              <Input
                value={form.shipFromName}
                onChange={(event) => setForm((previous) => ({ ...previous, shipFromName: event.target.value }))}
                maxLength={255}
              />
            </div>
            <div>
              <label className="mb-2 block text-sm text-mist">
                {copy.shipFromPhone ?? (locale === 'en' ? 'Sender phone' : 'Τηλέφωνο αποστολέα')}
              </label>
              <Input
                value={form.shipFromPhone}
                onChange={(event) => setForm((previous) => ({ ...previous, shipFromPhone: event.target.value }))}
                maxLength={50}
              />
            </div>
            <div className="md:col-span-2">
              <label className="mb-2 block text-sm text-mist">
                {copy.shipFromAddress1 ?? (locale === 'en' ? 'Address line 1' : 'Διεύθυνση 1')}
              </label>
              <Input
                value={form.shipFromAddress1}
                onChange={(event) => setForm((previous) => ({ ...previous, shipFromAddress1: event.target.value }))}
                maxLength={255}
              />
            </div>
            <div className="md:col-span-2">
              <label className="mb-2 block text-sm text-mist">
                {copy.shipFromAddress2 ?? (locale === 'en' ? 'Address line 2' : 'Διεύθυνση 2')}
              </label>
              <Input
                value={form.shipFromAddress2}
                onChange={(event) => setForm((previous) => ({ ...previous, shipFromAddress2: event.target.value }))}
                maxLength={255}
              />
            </div>
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.city}</label>
              <Input
                value={form.shipFromCity}
                onChange={(event) => setForm((previous) => ({ ...previous, shipFromCity: event.target.value }))}
                maxLength={100}
              />
            </div>
            <div>
              <label className="mb-2 block text-sm text-mist">
                {copy.shipFromPostalCode ?? (locale === 'en' ? 'Postal code' : 'Τ.Κ.')}
              </label>
              <Input
                value={form.shipFromPostalCode}
                onChange={(event) => setForm((previous) => ({ ...previous, shipFromPostalCode: event.target.value }))}
                maxLength={30}
              />
            </div>
            <div>
              <label className="mb-2 block text-sm text-mist">
                {copy.shipFromCountry ?? (locale === 'en' ? 'Country' : 'Χώρα')}
              </label>
              <Input
                value={form.shipFromCountry}
                onChange={(event) => setForm((previous) => ({ ...previous, shipFromCountry: event.target.value }))}
                maxLength={120}
              />
            </div>
            <div>
              <label className="mb-2 block text-sm text-mist">
                {copy.shipFromCountryCode ?? (locale === 'en' ? 'Country code' : 'Κωδικός χώρας')}
              </label>
              <Input
                value={form.shipFromCountryCode}
                onChange={(event) =>
                  setForm((previous) => ({
                    ...previous,
                    shipFromCountryCode: event.target.value.slice(0, 2).toUpperCase(),
                  }))
                }
                maxLength={2}
              />
            </div>
          </div>
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={busy}>
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            {busy ? copy.saving : copy.save}
          </Button>
        </div>
      </form>
    </div>
  )
}

export default ProfileAppearanceEditor
