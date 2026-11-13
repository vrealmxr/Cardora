import { safeJson } from '@/server/document'

// Declarative scripts are removed/replaced with their route during client navigation.
// Escape HTML delimiters so seller text cannot end the JSON-LD script element.
export default function StructuredData({ value }) {
  return value ? <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: safeJson(value) }} /> : null
}
