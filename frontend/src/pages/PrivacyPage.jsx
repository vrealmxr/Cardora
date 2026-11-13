import LegalDocumentPage from '@/components/legal/LegalDocumentPage'
import DOCUMENT from '@/data/legal/privacy'

function PrivacyPage() {
  return <LegalDocumentPage document={DOCUMENT} pageKey="privacy" />
}

export default PrivacyPage
