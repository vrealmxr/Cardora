import { companyRows } from './company.en.js'

export default {
  eyebrow: 'Cookie Policy', title: 'Cardora Cookie and Similar Technologies Policy',
  description: 'Cardora uses cookies, local storage and similar technologies where necessary to operate the marketplace or where the user has made the relevant choice for optional categories.',
  updated: 'Last updated: 23 October 2026',
  intro: 'This Cookie Policy explains what cookies are, why Cardora uses them, which technology categories operate in the marketplace, when a user choice is required and how users can withdraw or change their choice.',
  introSecondary: 'Read this policy alongside the Privacy Policy and Terms of Use. Where these technologies relate to accounts, checkout, verification, security or seller payouts, Cardora applies the principles of necessity and data minimisation.',
  companyTitle: 'Responsible operator', companyRows: companyRows.filter(([label]) => label !== 'Tax office'),
  principlesEyebrow: 'How they are used', principlesTitle: 'How Cardora uses cookies',
  principlesDescription: 'Cardora does not use cookies as an undefined marketing tool. The main aims are safe marketplace operation, a smooth user experience and, where permitted, product improvement.',
  principles: [
    { title: 'Necessary technologies first', text: 'Strictly necessary cookies or similar technologies are activated for purposes such as sign-in, session continuity, the cart, language, account security and protected checkout.' },
    { title: 'Optional categories are controlled', text: 'Optional categories, such as preferences or analytics, must follow a clear user choice and can be disabled again at any time through the relevant panel or browser settings.' },
    { title: 'Your choice is not bypassed', text: 'Cardora does not treat simply browsing the site as automatic consent to every non-essential technology. The choice is stored so that users are not interrupted on every visit.' },
    { title: 'The policy follows the actual product', text: 'If Cardora adds analytics, embedded content, affiliate tools or other third parties requiring an optional category, the policy and consent controls will be updated to reflect the change.' },
  ],
  sections: [
    { id: 'what', icon: 'privacy', title: '1. What counts as a cookie or similar technology', paragraphs: [
      'For this policy, “cookies” includes HTTP cookies, local storage, session storage, pixel-like technologies, device or browser identifiers and other technologies that store or retrieve information on a user’s device.',
      'Cardora may use both session technologies, which expire when the session closes, and persistent technologies, which remain for a specified period to retain useful choices or necessary security states.',
    ] },
    { id: 'categories', icon: 'general', title: '2. Categories of cookies and technologies used by Cardora', paragraphs: [
      'Cardora separates technologies into categories so that users can understand each group’s purpose and which groups can be controlled through consent settings.',
    ], bullets: [
      'Necessary: sign-in sessions, cart persistence, language state, abuse prevention and basic account security, checkout protection, unread states and functions required for the marketplace.',
      'Preferences: non-critical display or experience choices, such as remembered interface preferences where enabled by the user.',
      'Analytics: usage measurements, page performance or product-improvement metrics, only if and when enabled in accordance with the user’s choice.',
      'Third-party technologies: technologies associated with embedded content, payment providers or other integrations, only to the extent technically necessary or covered by the relevant choice or lawful basis.',
    ] },
    { id: 'necessary', icon: 'security', title: '3. When necessary cookies are enabled without a separate choice', paragraphs: [
      'Cardora activates necessary cookies and equivalent technologies only when technically required to provide a service requested by the user or to operate the marketplace safely. Examples include signing in, the cart, checkout state, in-platform notifications, fraud protection and continuation of an active secure session.',
      'These technologies are not used for undefined commercial tracking. A tool that is not actually necessary for the particular function is not classified as “necessary” merely because it is useful for reporting or marketing.',
    ], notice: 'Storing the consent choice itself is considered necessary so that Cardora can remember whether the user chose “accept all”, “necessary only” or a particular combination of categories.' },
    { id: 'optional', icon: 'retention', title: '4. Optional categories and consent', paragraphs: [
      'Where Cardora uses or later enables non-essential technologies, these belong to optional categories such as preferences or analytics. They are not enabled simply because someone visits the website. Cardora seeks to rely on a clear choice that can be withdrawn.',
      'Users can change their mind at any time through the cookie panel, a relevant link in the Cookie Policy or browser controls. Withdrawal does not affect lawful processing based on the previous choice, but it affects future activation of optional categories.',
    ] },
    { id: 'third-parties', icon: 'payments', title: '5. Third-party providers and specific services', paragraphs: [
      'Support chat is provided by Chatwoot and loads when you choose “Open chat” after the notice. Visiting the website or accepting cookies does not itself activate chat. Once opened, the service uses cookies to maintain the conversation and receives technical information about the visit. Closing the chat window does not delete the conversation history or the service’s cookies. You can alternatively contact support@cardora.gr.',
      'Identity verification redirects users to Didit’s secure website when they choose to continue after the specific notice and consent step. Didit may use cookies, session identifiers and device/IP information to maintain the verification session and prevent fraud. If the process starts on a computer, Didit displays a QR code requiring continuation of the same personal session on a phone. Afterwards, the user returns to Cardora. Biometric consent is separate from cookie choices. Further information appears in Cardora’s Privacy Policy and Didit’s privacy notice.',
      'Cardora uses third-party providers where necessary to provide the marketplace, including payment and connected-payout providers. In protected payments and seller payout setup, certain storage or identifiers may be needed for security, payment integrity, fraud prevention and the correct return to or continuation of the process.',
      'If a Cardora page later includes embedded media, maps, videos, widgets or external scripts that are not technically necessary, Cardora should place them in the corresponding optional category or activate them only after the relevant choice.',
    ] },
    { id: 'duration', icon: 'retention', title: '6. Storage duration', paragraphs: [
      'Session cookies or identifiers normally remain only as long as the session requires. Persistent cookies or local storage entries are retained only while they serve a reasonable purpose, such as saving consent choices, retaining non-essential preferences or keeping basic interface choices stable.',
      'Cardora seeks to review retention periods and remove entries that are no longer needed. Where information is required for compliance, fraud prevention, disputes or transaction evidence, retention may follow the broader rules described in the Privacy Policy.',
    ] },
    { id: 'controls', icon: 'rights', title: '7. How to control or disable cookies', paragraphs: [
      'Users can select “necessary only”, accept all categories or save specific choices through Cardora’s cookie panel. They can also delete cookies or local storage entries through their browser settings.',
      'Disabling certain technologies may affect sign-in, the cart, checkout or other services. Disabling necessary technologies at browser level may prevent access to essential platform functions.',
    ] },
    { id: 'updates', icon: 'legal', title: '8. Policy updates', paragraphs: [
      'Cardora may update this Cookie Policy when its tools, consent controls, providers, cookie categories or relevant legal obligations change.',
      'The latest version is always published on this page with its last-updated date. When a change materially affects non-essential categories, Cardora may ask users to make their choice again.',
    ] },
  ],
  relatedEyebrow: 'Related pages', relatedTitle: 'Cookies, privacy and marketplace compliance',
  relatedDescription: 'Cookies are one part of Cardora’s wider approach to transparency.',
  related: [
    { badge: 'Privacy', title: 'Privacy Policy', text: 'How Cardora processes personal data, for which purposes and with which providers.', to: '/politiki-aporritou', cta: 'Read the privacy policy' },
    { badge: 'Marketplace', title: 'Terms of Use', text: 'The framework for accounts, listings, orders, messaging, verification and seller payouts.', to: '/oroi-xrisis', cta: 'Read the terms' },
    { badge: 'Trust', title: 'Refunds and Disputes', text: 'How Cardora handles protected orders, disputes, holds and releases.', to: '/epistrofes-kai-diafores', cta: 'Read the policy' },
  ],
  ctaEyebrow: 'Your choices', ctaTitle: 'Want to change your cookie choices or ask a specific question?',
  ctaText: 'Use the cookie panel to change optional categories, or contact Cardora with questions about a particular provider, retention period or consent process.',
  ctaPrimary: 'Contact', ctaPrimaryTo: '/epikoinonia', ctaSecondary: 'Privacy Policy', ctaSecondaryTo: '/politiki-aporritou',
}
