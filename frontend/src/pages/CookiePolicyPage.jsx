import LegalDocumentPage from '@/components/legal/LegalDocumentPage'
import DOCUMENT from '@/data/legal/cookies'

function CookiePolicyPage() {
  return <LegalDocumentPage document={DOCUMENT} pageKey="cookies" />
}

export default CookiePolicyPage
