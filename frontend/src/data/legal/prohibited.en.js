import { companyRows } from './company.en.js'

export default {
  eyebrow: 'Prohibited Items Policy', title: 'Prohibited, restricted and unsafe items',
  description: 'This policy explains which goods cannot be listed, sold or promoted on Cardora, which items may be restricted or require further review, and how the platform handles counterfeit, illegal, unsafe or materially misleading listings.',
  updated: 'Last updated: 6 April 2026',
  intro: 'Cardora is a curated marketplace for collectibles. Its focus on collecting does not remove general legal obligations: every item listed on Cardora must be lawful to sell, safe where relevant, honestly described and consistent with the platform’s rules.',
  introSecondary: 'Read this policy alongside the Terms of Use, the Refunds and Disputes Policy and any category-specific or verification instructions shown when creating a listing. Where required by law or platform safety, Cardora may act without prior notice.',
  companyTitle: 'Operator details', companyRows,
  principlesEyebrow: 'Listing safety', principlesTitle: 'What Cardora requires from every listing',
  principlesDescription: 'The platform serves collectors, so legality, authenticity, traceability and honest presentation take priority.',
  principles: [
    { title: 'Only lawful and authentic goods', text: 'Items must be lawful to possess and sell. Goods whose authenticity matters must be described honestly, including any repairs, restoration, resealing, alterations or uncertainty.' },
    { title: 'Collectible status does not remove safety risks', text: 'A collectible may be unsafe, recalled, restricted, counterfeit or legally prohibited. Cardora may therefore remove items even if they are traded informally elsewhere.' },
    { title: 'The correct category matters', text: 'Cardora is not a general classifieds platform. Listings without a meaningful connection to the category’s collecting focus may be removed even if the goods themselves are not illegal.' },
  ],
  sections: [
    { id: 'counterfeit', icon: 'prohibited', title: '1. Counterfeit, fake, altered or infringing goods', paragraphs: [
      'Counterfeit products, unauthorised reproductions, fake signatures, falsified certificates, altered grading holders, relabelled items, forged provenance documents and listings that materially mislead buyers about authenticity are prohibited. Cardora may also remove items when their authenticity cannot be adequately supported and the listing creates a serious risk of misleading buyers.',
      'In collectible categories, examples include fake trading cards, trimmed or materially altered cards presented dishonestly, resealed packs or boxes without clear disclosure, forged autographs, manipulated grading cases, unauthorised reproduction figures, pirated art books, fake certificates of authenticity and other misleading authenticity practices.',
    ] },
    { id: 'illegal', icon: 'legal', title: '2. Stolen property, unlawful provenance and illegal goods', paragraphs: [
      'Items that are stolen, unlawfully obtained, smuggled, looted, misappropriated or otherwise unlawful to possess or sell are prohibited. Users must not list goods without lawful title, authority to dispose of them or clear provenance where this is material to the nature of the item.',
      'Cardora may request supporting records, invoices or provenance evidence and remove a listing when the chain of origin or ownership is unclear or suspicious.',
    ] },
    { id: 'unsafe', icon: 'security', title: '3. Unsafe, recalled or dangerous products', paragraphs: [
      'Cardora does not allow products that are dangerous, subject to recall, unlawfully modified in a way that affects safety or otherwise inconsistent with product-safety obligations. Where applicable, this includes unsafe electronics, hazardous materials, altered chargers or batteries, recalled toys or collectibles, and goods requiring controlled distribution or specialist safety handling that Cardora does not support.',
      'An item’s collectible nature is not enough to justify its presence on the platform if the product remains dangerous, unsafe or legally problematic.',
    ] },
    { id: 'regulated', icon: 'prohibited', title: '4. Regulated, prohibited or abuse-prone goods', paragraphs: [
      'Cardora prohibits medicines, prescription products, controlled substances, tobacco and nicotine products, weapons, ammunition, explosives, active gift cards, top-up codes, cash equivalents, personal identity documents, banking credentials, active financial instruments and, generally, regulated or abuse-prone goods incompatible with the platform’s purpose and legal basis.',
    ] },
    { id: 'digital', icon: 'general', title: '5. Unsupported digital goods and non-collectible items', paragraphs: [
      'Unless expressly supported by Cardora, unsupported digital goods, gaming accounts, software licences, activation keys, downloadable files and other intangible transfers are prohibited. Cardora is designed primarily for physical collectibles and related supported marketplace formats.',
      'Cardora may also remove listings without a meaningful connection to the relevant collectible category, even if the item itself is not illegal. A collectors’ marketplace cannot remain clear and reliable if unrelated goods are placed in collectible categories merely to exploit traffic or visibility.',
    ], bullets: [
      'Unrelated household or general consumer goods placed in collectible categories.',
      'Low-information spam listings intended to attract views rather than facilitate a genuine collectible transaction.',
      'Listings with misleading titles, categories or imagery about what is actually for sale.',
    ] },
    { id: 'collector-specific', icon: 'reporting', title: '6. Collectible-specific manipulation, reports and Cardora action', paragraphs: [
      'Because Cardora serves collectors, certain misleading practices receive particular scrutiny. These include altered or repaired items without clear disclosure, incomplete lots presented as complete, box swaps, figure accessories exchanged between different product variants, replacement parts presented as originals, hidden restoration, loose grading labels, resealed wax packs, repacks described as sealed products, and mystery bundles that materially conceal their contents.',
      'Cardora may remove, suspend, hide, restrict, de-index, refuse or escalate any listing that appears prohibited, unsafe, illegal, counterfeit or materially misleading. It may request invoices, provenance, close-up images, serial details, certificates of authenticity, trader documentation or other supporting records. It may act before receiving a response where the risk to users, the platform or its legal obligations is sufficiently serious.',
    ], notice: 'In serious or repeated cases, Cardora may retain records, impose restrictions or suspensions and, where legally justified, cooperate with authorities or rights holders.' },
  ],
  relatedEyebrow: 'Related policies', relatedTitle: 'Policies that accompany listing restrictions',
  relatedDescription: 'Listing legality, user conduct and checkout protection are connected in practice.',
  related: [
    { badge: 'Terms', title: 'Terms of Use', text: 'General rules on accounts, listings, enforcement and suspensions across the marketplace.', to: '/oroi-xrisis', cta: 'Read the terms' },
    { badge: 'Privacy', title: 'Privacy Policy', text: 'How moderation, reporting, verification and safety records are processed.', to: '/politiki-aporritou', cta: 'Read the policy' },
    { badge: 'Disputes', title: 'Refunds and Disputes', text: 'What happens when an item arrives damaged, misdescribed or counterfeit, or does not arrive at all.', to: '/epistrofes-kai-diafores', cta: 'Read the policy' },
  ],
  ctaEyebrow: 'Listing review', ctaTitle: 'Unsure whether an item is allowed?',
  ctaText: 'Ask Cardora before publishing if the item’s legality, authenticity, safety or suitability for the category is unclear. This is especially important for sealed products, restored pieces, vintage electronics, repacks and items with unclear provenance.',
  ctaPrimary: 'Open support centre', ctaPrimaryTo: '/kentro-ypostiriksis', ctaSecondary: 'Contact', ctaSecondaryTo: '/epikoinonia',
}
