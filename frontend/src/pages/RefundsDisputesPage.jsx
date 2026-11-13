import LegalDocumentPage from '@/components/legal/LegalDocumentPage'
import DOCUMENT from '@/data/legal/refunds'

function RefundsDisputesPage() {
  return <LegalDocumentPage document={DOCUMENT} pageKey="refunds-disputes" />
}

export default RefundsDisputesPage
