import { companyRows } from './company.en.js'

// Translation of the existing Greek policy; this does not introduce new policy terms.
export default {
  eyebrow: 'Refunds and Disputes Policy',
  title: 'Refunds, disputes and protected orders',
  description: 'This policy explains how Cardora handles order problems, shipping evidence, held funds, buyer confirmation, seller release, manual review, refunds and abusive reports in a collectibles marketplace.',
  updated: 'Last updated: 6 April 2026',
  intro: 'This policy applies to orders placed through Cardora checkout. Read it alongside the Terms of Use and any specific instructions shown at checkout, in the order area or in a particular protected delivery process.',
  introSecondary: 'Cardora operates a marketplace and is not automatically the merchant of record for every third-party item. How a dispute is handled therefore depends on the seller’s status, the problem, the order’s status, the available evidence and whether funds are still held or have already been released.',
  companyTitle: 'Operator details', companyRows,
  principlesEyebrow: 'How protection works',
  principlesTitle: 'Key principles of order protection on Cardora',
  principlesDescription: 'The aim is to keep evidence, payments and review within the platform so that each dispute can be assessed against an actual record.',
  principles: [
    { title: 'Holds are conditional', text: 'Where Cardora offers protected checkout, the buyer’s funds may be held before release to the seller. The exact release process depends on the transaction, the evidence and any dispute or compliance issue preventing release.' },
    { title: 'Evidence matters more than general claims', text: 'Photographs, order records, tracking, parcel labels, timestamps, packaging evidence and message history carry more weight than vague or unsupported reports.' },
    { title: 'Traders and private sellers are treated differently', text: 'Mandatory consumer rights may differ depending on whether the seller acts as a trader or a private individual. Cardora’s process operates alongside the law and does not replace it.' },
  ],
  sections: [
    { id: 'scope', icon: 'disputes', title: '1. Scope and Cardora’s role', paragraphs: [
      'This policy covers purchases completed through Cardora checkout, including non-delivery, material differences from the description, damage in transit, authenticity concerns, shipping delays, release timing and refund requests.',
      'In ordinary transactions between users, the sale contract is generally between the buyer and seller. Cardora operates the marketplace, moderation, support and protected-payment infrastructure. It may investigate disputes, delay release, request evidence or support a refund where justified.',
    ] },
    { id: 'shipping', icon: 'shipping', title: '2. Shipping and receiving obligations', paragraphs: [
      'The seller must dispatch within the timeframe stated in the listing, order process or seller dashboard and provide proof of dispatch, tracking data or dispatch confirmation where required. The buyer must provide an accurate delivery address, follow shipping updates and inspect the parcel immediately after delivery.',
      'If the parcel appears damaged, incomplete, tampered with or inconsistent with the expected shipment, the buyer must retain the packaging, take clear photographs, keep the labels and contact Cardora without undue delay.',
    ] },
    { id: 'issues', icon: 'reporting', title: '3. Issues Cardora may review and supporting evidence', paragraphs: [
      'Cardora may review non-delivery, substantial delay, the wrong item, a materially inaccurate description, significant undisclosed defects, missing essential parts or accessories, suspected counterfeiting or authenticity concerns, and damage in transit.',
      'To assess a dispute, Cardora may request photographs, video, parcel labels, shipment scans, timestamps, message history, listing screenshots, serial numbers, authenticity references, courier documents and other evidence reasonably relevant to the issue.',
    ], bullets: [
      'Non-delivery: no parcel has been delivered and there is insufficient evidence of delivery to the correct buyer address.',
      'Not as described: the item differs materially from the listing in its identity, condition, completeness or value.',
      'Authenticity concern: there are reasonable indications that the item may be counterfeit, altered or materially misrepresented.',
    ] },
    { id: 'release', icon: 'payments', title: '4. Held funds, buyer review and seller release', paragraphs: [
      'Where the checkout or order process specifies protected release, the buyer’s funds may remain pending before release to the seller. Release depends on the order status, provider status, available evidence, the seller’s payout readiness, the review window shown in the process and whether a dispute or compliance issue prevents release.',
      'The buyer may be asked to confirm receipt or satisfactory completion of the order. If the applicable review window expires without a valid dispute, Cardora may proceed with release, subject to the conditions of that process. Once funds have been released through the payment provider, the technical options for reversing the transfer are often more limited than before release.',
    ], notice: 'Cardora may delay release while a dispute, fraud review, verification issue, shipping inconsistency or other compliance issue remains open, even if the expected review window would otherwise have expired.' },
    { id: 'refunds', icon: 'payments', title: '5. Refunds before or after release', paragraphs: [
      'If a problem is reported before seller release and Cardora concludes, on the evidence and the applicable process rules, that the buyer should be reimbursed, a refund may be supported through the payment provider and the pending seller amount cancelled to the extent technically possible.',
      'If funds have already been released to the seller through the connected payment system, Cardora may continue investigating and taking platform measures, but immediate technical recovery of the funds is not always possible. Cardora may request cooperation, impose restrictions, retain records and support further lawful steps.',
    ] },
    { id: 'abuse', icon: 'moderation', title: '6. Abusive reports and bad faith', paragraphs: [
      'Cardora may reject claims and take enforcement action when a user acts in bad faith, supplies fabricated evidence, repeatedly makes clearly unsupported claims, abuses chargebacks, threatens the other party, attempts to move settlement off the platform or tries to manipulate the protection system.',
      'Cardora’s dispute process does not remove mandatory legal rights against a trader seller or rights involving other competent bodies. Cardora may nevertheless make its own platform decision on release, refund support, listing enforcement or account restrictions to the extent required for the platform’s operation and safety.',
    ] },
  ],
  relatedEyebrow: 'Related policies', relatedTitle: 'Policies affecting refunds and order protection',
  relatedDescription: 'Disputes involve checkout, listings, legality and user conduct.',
  related: [
    { badge: 'Terms', title: 'Terms of Use', text: 'The marketplace rules for accounts, orders, seller obligations, protected releases and enforcement.', to: '/oroi-xrisis', cta: 'Read the terms' },
    { badge: 'Privacy', title: 'Privacy Policy', text: 'How messages, order evidence, Didit verification results, historical records and provider identifiers are processed and retained.', to: '/politiki-aporritou', cta: 'Read the policy' },
    { badge: 'Listings', title: 'Prohibited Items', text: 'Goods that cannot be listed or sold because they are unsafe, illegal, counterfeit or incompatible with Cardora.', to: '/apagorevmena-antikeimena', cta: 'Read the policy' },
  ],
  ctaEyebrow: 'Order support', ctaTitle: 'Need to report a problem with an order?',
  ctaText: 'Use Cardora’s support centre and keep evidence, order statuses, photographs, shipping updates and message history within the platform so that the case can be reviewed with a complete record.',
  ctaPrimary: 'Open support centre', ctaPrimaryTo: '/kentro-ypostiriksis', ctaSecondary: 'Contact', ctaSecondaryTo: '/epikoinonia',
}
