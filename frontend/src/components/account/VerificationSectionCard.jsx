import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Select, Textarea } from '@/components/ui/Input'
import { useI18n } from '@/hooks/useI18n'
import { cn } from '@/utils/helpers'

const statusTone = {
  Εγκεκριμένο: 'success',
  Approved: 'success',
  'Σε έλεγχο': 'info',
  'Under review': 'info',
  'Απαιτείται διόρθωση': 'warning',
  'Απαιτούνται διορθώσεις': 'warning',
  'Changes requested': 'warning',
  'Changes required': 'warning',
  'Δεν έχει ξεκινήσει': 'muted',
  'Not started': 'muted',
}

function VerificationField({ field, value, onChange, locale }) {
  const copy =
    locale === 'en'
      ? { choose: 'Choose' }
      : { choose: 'Επίλεξε' }

  if (field.type === 'select') {
    return (
      <Select value={value ?? ''} onChange={(event) => onChange(field.name, event.target.value)}>
        <option value="">{copy.choose}</option>
        {field.options.map((option) => (
          <option key={option} value={option}>
            {option}
          </option>
        ))}
      </Select>
    )
  }

  if (field.type === 'textarea') {
    return (
      <Textarea
        value={value ?? ''}
        onChange={(event) => onChange(field.name, event.target.value)}
        placeholder={field.placeholder}
      />
    )
  }

  if (field.type === 'checkbox') {
    return (
      <label className="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 px-3.5 py-3 text-sm text-white/80">
        <input
          type="checkbox"
          checked={Boolean(value)}
          onChange={(event) => onChange(field.name, event.target.checked)}
          className="mt-1 h-4 w-4 rounded border-white/20 bg-transparent text-gold-300 focus:ring-gold-300/30"
        />
        <span>{field.label}</span>
      </label>
    )
  }

  if (field.type === 'file') {
    const files = Array.isArray(value) ? value : value ? [value] : []

    return (
      <div className="space-y-2">
        <input
          type="file"
          multiple={field.multiple}
          accept={field.accept}
          onChange={(event) => {
            const nextValue = Array.from(event.target.files ?? [])
            onChange(field.name, field.multiple ? nextValue : nextValue[0] ?? null)
          }}
          className="block w-full text-[13px] text-mist file:mr-3 file:rounded-lg file:border-0 file:bg-gold-300/15 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-gold-100 hover:file:bg-gold-300/20"
        />
        {files.length ? (
          <div className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] text-white/75">
            {locale === 'en'
              ? `${files.length} file${files.length === 1 ? '' : 's'} selected`
              : `${files.length} αρχείο${files.length === 1 ? '' : 'α'} επιλεγμένο`}
          </div>
        ) : null}
      </div>
    )
  }

  return (
    <Input
      type={field.type ?? 'text'}
      value={value ?? ''}
      onChange={(event) => onChange(field.name, event.target.value)}
      placeholder={field.placeholder}
    />
  )
}

function VerificationSectionCard({ section, draft, error, onChange, onSubmit }) {
  const { locale } = useI18n()

  const copy =
    locale === 'en'
      ? {
          submit: 'Submit for review',
          acceptedDocs: 'Accepted documents',
          checklist: 'Checklist',
          currentFiles: 'Uploaded files',
          noFiles: 'No files have been uploaded for this section yet.',
          reviewNote: 'Review note',
        }
      : {
          submit: 'Υποβολή για έλεγχο',
          acceptedDocs: 'Αποδεκτά έγγραφα',
          checklist: 'Checklist',
          currentFiles: 'Αρχεία που υπάρχουν',
          noFiles: 'Δεν υπάρχει ακόμη υποβολή για αυτή την ενότητα.',
          reviewNote: 'Σημείωση review',
        }

  return (
    <CardSurface className="overflow-hidden">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="max-w-3xl">
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone={statusTone[section.status] ?? 'gold'}>{section.status}</Badge>
            <span className="text-[11px] uppercase tracking-[0.28em] text-white/40">
              {section.shortTitle}
            </span>
          </div>
          <h3 className="mt-3 font-display text-[2rem] text-white">{section.title}</h3>
          <p className="mt-2 text-sm leading-7 text-mist">{section.description}</p>
          <p className="mt-3 text-sm leading-7 text-white/75">{section.helperText}</p>
        </div>
      </div>

      <div className="mt-6 grid gap-4 xl:grid-cols-[0.9fr,1.1fr]">
        <div className="space-y-4">
          <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-gold-100">
              {copy.acceptedDocs}
            </p>
            <div className="mt-3 space-y-2">
              {section.acceptedDocuments.map((item) => (
                <div key={item} className="rounded-xl border border-white/8 bg-black/10 px-3 py-2.5 text-sm text-white/80">
                  {item}
                </div>
              ))}
            </div>
          </div>

          <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-gold-100">
              {copy.checklist}
            </p>
            <div className="mt-3 space-y-2">
              {section.checklist.map((item) => (
                <div key={item} className="rounded-xl border border-white/8 bg-black/10 px-3 py-2.5 text-sm text-white/75">
                  {item}
                </div>
              ))}
            </div>
          </div>

          <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-gold-100">
              {copy.currentFiles}
            </p>
            {section.uploads.length ? (
              <div className="mt-3 space-y-2">
                {section.uploads.map((upload, index) => (
                  <div
                    key={`${section.id}-${upload.fileName}-${index}`}
                    className="rounded-xl border border-white/8 bg-black/10 px-3 py-2.5"
                  >
                    <p className="text-sm font-medium text-white">{upload.label}</p>
                    {upload.fileName ? <p className="mt-1 text-xs text-white/50">{upload.fileName}</p> : null}
                  </div>
                ))}
              </div>
            ) : (
              <p className="mt-3 text-sm text-mist">{copy.noFiles}</p>
            )}
          </div>
        </div>

        <div className="rounded-[22px] border border-white/8 bg-black/10 p-4">
          <div className="grid gap-4 md:grid-cols-2">
            {section.fields.map((field) => (
              <div
                key={`${section.id}-${field.name}`}
                className={cn(
                  field.type === 'textarea' && 'md:col-span-2',
                  field.type === 'file' && 'md:col-span-2',
                  field.type === 'checkbox' && 'md:col-span-2',
                )}
              >
                {field.type !== 'checkbox' ? (
                  <label className="mb-2 block text-sm text-mist">
                    {field.label}
                    {field.required ? <span className="ml-1 text-gold-100">*</span> : null}
                  </label>
                ) : null}
                <VerificationField field={field} value={draft[field.name]} onChange={onChange} locale={locale} />
                {field.helperText ? <p className="mt-2 text-xs leading-6 text-white/45">{field.helperText}</p> : null}
              </div>
            ))}
          </div>

          {error ? (
            <div className="mt-4 rounded-xl border border-amber-400/20 bg-amber-500/10 px-3.5 py-3 text-sm text-amber-100">
              {error}
            </div>
          ) : null}

          <div className="mt-5 rounded-xl border border-white/8 bg-white/5 px-4 py-3">
            <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-gold-100">
              {copy.reviewNote}
            </p>
            <p className="mt-2 text-sm leading-7 text-white/75">{section.reviewNotes}</p>
          </div>
        </div>
      </div>

      <div className="mt-6 flex justify-end">
        <Button variant="primary" size="sm" onClick={() => onSubmit(section.id)}>
          {copy.submit}
        </Button>
      </div>
    </CardSurface>
  )
}

export default VerificationSectionCard
