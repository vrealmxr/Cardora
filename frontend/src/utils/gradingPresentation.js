// Keep stored values stable. Packaging and seller condition descriptions are not grading certificates.
export const gradingOptionsForEdit = (options, current) => current && !options.includes(current) ? [...options, current] : options

export function gradingChoiceLabel(value, locale) {
  const en = locale === 'en'
  if (['Ungraded', 'Raw Copy'].includes(value)) return en ? 'Not professionally graded' : 'Χωρίς επαγγελματική βαθμολόγηση'
  if (value === 'Sealed') return en ? 'Sealed packaging (not a grading company)' : 'Σφραγισμένη συσκευασία (δεν είναι εταιρεία grading)'
  return value
}

export function gradingPresentation(company, locale) {
  const en = locale === 'en'
  const ungraded = !company || ['Ungraded', 'Raw Copy', 'Sealed'].includes(company)
  return {
    company: company === 'Sealed' ? null : gradingChoiceLabel(company, locale),
    packaging: company === 'Sealed' ? (en ? 'Sealed (seller’s description)' : 'Σφραγισμένο (δήλωση πωλητή)') : null,
    gradeLabel: ungraded ? (en ? 'Seller’s condition note' : 'Σχόλιο κατάστασης πωλητή') : (en ? 'Grade on the label' : 'Βαθμός στην ετικέτα'),
  }
}
