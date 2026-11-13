import { companyRows } from './company.en.js'
import { diditLegal } from '../diditLegal.js'

export default {
  eyebrow: 'Privacy Policy', title: 'Cardora Marketplace Privacy Policy',
  description: 'This policy explains which personal data Cardora processes, for what purposes and on which legal bases, who receives it, how long it is retained and users’ rights under the GDPR and applicable law.',
  updated: 'Last updated: 21 October 2026',
  intro: 'This Privacy Policy applies to the website, buyer and seller services, verification tools, protected payments, order and support processes, messages, notifications and related Cardora services operated by VRealm I.K.E.',
  introSecondary: 'Where a particular checkout, verification, support or payout setup process contains a more specific privacy notice, that notice supplements this policy for the processing concerned. This policy does not limit any mandatory right under the GDPR or other applicable law.',
  companyTitle: 'Controller details', companyRows,
  principlesEyebrow: 'Key principles', principlesTitle: 'Cardora’s privacy principles',
  principlesDescription: 'Cardora processes data with regard to necessity, transparency, marketplace safety and the need to keep a clear record of transactions and disputes.',
  principles: [
    { title: 'Processing where there is a real reason', text: 'Cardora processes data reasonably necessary for accounts, listings, orders, verification, payouts, support, fraud prevention and legal compliance.' },
    { title: 'Protected payments through third-party providers', text: 'Checkout, connected payouts and related provider statuses operate through providers such as Stripe and Stripe Connect. Cardora does not replace a bank or payment institution.' },
    { title: 'Evidence stays within the platform', text: 'Messages, order history, shipping updates and moderation records may be used to protect users, manage disputes and prevent abuse.' },
    { title: 'Users retain their rights', text: 'Users may exercise rights of access, rectification, erasure, restriction, portability or objection where applicable, subject to lawful retention obligations.' },
  ],
  sections: [
    { id: 'cardora-scanner', icon: 'privacy', title: 'Cardora Scanner and card photographs', paragraphs: [
      'The card is detected and cropped automatically on your device. Photo metadata is removed before upload. If its boundaries cannot be identified clearly, the photo is used only for identification and the submission is excluded from training collection. Its images are deleted after analysis; an unprocessed submission expires within 24 hours. Photograph only one card, without faces or personal information.',
      'The image for identification is sent to Cardora’s server and the RunPod infrastructure used for identification. The submission, results and version of the terms are linked to your account so that you can view and delete your scans. Cropping does not make a submission anonymous.',
      'Only automatically cropped submissions are retained in the private review collection for up to 30 days, accessible only to authorised administrators. Using card images for future training requires a separate review of the image and usage rights, in accordance with the Cardora Scanner section of the Terms of Use.',
      'You can delete a submission from recent scans or contact support to exercise your rights and request a review of any exported copies. Accepting terms does not replace the lawful basis required to process personal data.',
    ] },
    diditLegal.en,
    { id: 'scope', icon: 'privacy', title: '1. Controller and scope', paragraphs: [
      'VRealm I.K.E., as Cardora’s operator, acts as controller for the personal data required to operate the marketplace, provide support, protect accounts, manage listings and orders, conduct verification and comply with legal obligations.',
      'This policy covers data supplied directly by users, data generated through platform use and data received from integrated providers or the other party to a transaction where necessary for the transaction, dispute handling, fraud review or service security.',
    ] },
    { id: 'categories', icon: 'general', title: '2. Categories of data processed by Cardora', paragraphs: [
      'Cardora may process identity and contact information such as full name, handle, email, city, biography, avatar, public profile image and profile preferences. It may also process account and access data such as a hashed password, authentication states, security logs and device or session indicators.',
      'Marketplace data also includes listings, favourites, follows, order history, notifications, reviews, support tickets, on-platform messages and moderation results. Checkout and payout processing includes order metadata, payment references, charge, transfer and payout references, connected-account statuses, refund indicators and related audit trails.',
      'Identity verification takes place through Didit as described in the dedicated section. Payment and bank-account information is processed through Stripe and Stripe Connect. No separate proof of address or IBAN document is requested for Cardora verification.',
    ], bullets: [
      'Public profile information: handle, biography, city, avatar, cover image and public metrics.',
      'Listing and order data: title, description, price, quantity, shipping states and dispute notes.',
      'Verification data: Didit session identifiers, status, timestamps and notice/consent evidence, as well as historical records from the previous process.',
      'Provider references: payment intents, charges, transfers, payouts and connected-account states.',
    ] },
    { id: 'sources-purposes', icon: 'legal', title: '3. Data sources, processing purposes and legal bases', paragraphs: [
      'Most data comes from users when they create an account, complete a profile, upload a listing, place an order, send a message, follow other users, submit verification information or contact support. Cardora also creates operational records from service use, such as order state changes, notification history, unread states, moderation flags, release states, shipping states and security logs.',
      'Cardora processes this data to provide accounts and the marketplace experience, complete protected orders, enable payouts, handle disputes and returns, moderate content, detect fraud and abuse, respond to support requests and comply with legal obligations.',
      'The legal basis may be performance of a contract or pre-contractual steps, compliance with a legal obligation, Cardora’s legitimate interests in security, fraud and abuse prevention and transaction documentation, or the user’s consent where specifically requested for optional choices.',
    ] },
    { id: 'payments', icon: 'payments', title: '4. Checkout, payouts, verification and transfers to providers', paragraphs: [
      'Cardora uses providers such as Stripe and Stripe Connect for protected checkout, connected accounts, releases, transfers, payouts and related payment states. When these services are used, Cardora may send the provider data necessary to process payment, check compliance, verify seller readiness and lawfully provide the service.',
      'Didit provides identity verification. Stripe may independently request identity, address, bank-account information or documents during its own onboarding. Cardora does not request proof of address or a bank document in its new verification process and does not encourage bypassing protected payments.',
    ] },
    { id: 'messaging-recipients', icon: 'moderation', title: '5. On-platform messages, data recipients and international transfers', paragraphs: [
      'On-platform messages may be processed to deliver conversations, show unread status, associate them with orders, detect abusive or off-platform conduct, apply automatic or manual flags and retain evidence for disputes, fraud investigations, support or enforcement. Cardora may use automated filters, masking or flagging when prohibited contact details, threats, insults or attempts to move a transaction off the platform are detected.',
      'Cardora transfers data to hosting, storage, authentication, notification, analytics, support, security, email, payment, payout and courier-related providers only to the extent necessary to provide the marketplace or comply with legal obligations. The other party to a transaction receives only the information necessary for the transaction, dispatch, receipt or dispute handling.',
      'Where providers or subprocessors process data outside the user’s country or the EEA, Cardora seeks to rely on a lawful transfer mechanism, such as an adequacy decision, Standard Contractual Clauses or another recognised safeguard under the GDPR.',
    ], notice: 'Where Cardora must retain records for tax, accounting, fraud prevention, compliance or dispute purposes, an erasure request may not result in the immediate or complete removal of every relevant record.' },
    { id: 'retention-rights', icon: 'rights', title: '6. Retention, rights and contact', paragraphs: [
      'Cardora retains data for as long as reasonably necessary to operate accounts, host listings, support orders, comply with tax and accounting obligations, keep evidence for disputes and fraud reviews and maintain service security. Different record types may have different retention periods depending on their nature and purpose.',
      'Subject to legal limitations, users may request access, rectification, erasure, restriction, portability or objection. They may also contact Cardora about data accuracy, compromised security, verification records or payout-related data. Cardora may request identification before responding.',
      'Users who believe their data is being processed unlawfully may first contact Cardora and may also lodge a complaint with the Hellenic Data Protection Authority or another competent supervisory authority where applicable. Cardora may update this policy to reflect legal or operational changes and will publish the latest version with its update date.',
    ] },
  ],
  relatedEyebrow: 'Related policies', relatedTitle: 'Other legal pages that accompany this policy',
  relatedDescription: 'Privacy, checkout, disputes and listing rules form a connected compliance framework on Cardora.',
  related: [
    { badge: 'Terms', title: 'Terms of Use', text: 'The basic rules for accounts, listings, orders, payouts and conduct on the platform.', to: '/oroi-xrisis', cta: 'Read the terms' },
    { badge: 'Disputes', title: 'Refunds and Disputes', text: 'How Cardora handles non-delivery claims, items not as described, holds and releases.', to: '/epistrofes-kai-diafores', cta: 'Read the policy' },
    { badge: 'Listings', title: 'Prohibited Items', text: 'Which items are not allowed and why authenticity, safety and legality matter.', to: '/apagorevmena-antikeimena', cta: 'Read the policy' },
  ],
  ctaEyebrow: 'Privacy requests', ctaTitle: 'Need help with access, correction or another privacy request?',
  ctaText: 'Use the support centre to create a clear request within the platform, or contact Cardora about account ownership, verification records, payout-readiness information or a potentially compromised account.',
  ctaPrimary: 'Open support centre', ctaPrimaryTo: '/kentro-ypostiriksis', ctaSecondary: 'Contact', ctaSecondaryTo: '/epikoinonia',
}
