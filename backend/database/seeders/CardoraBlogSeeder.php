<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CardoraBlogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();
        $authors = $this->resolveAuthors();

        BlogPost::query()
            ->whereIn('slug', [
                'how-cardora-protected-payments-work',
                'what-makes-a-card-listing-look-premium',
                'selling-figures-with-better-protection',
            ])
            ->update(['status' => 'draft']);

        foreach ($this->posts() as $post) {
            $category = $categories[$post['category_key']] ?? $categories['collector_news'];
            $author = $authors[$post['author_key']] ?? $authors['fallback'];

            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'blog_category_id' => $category->getKey(),
                    'author_id' => $author->getKey(),
                    'title' => $post['seo']['translations']['el']['title'],
                    'excerpt' => $post['seo']['translations']['el']['excerpt'],
                    'content' => $post['content'],
                    'cover_media' => $post['cover_media'],
                    'status' => 'published',
                    'published_at' => Carbon::parse($post['published_at']),
                    'tags' => $post['tags'],
                    'seo' => $post['seo'],
                    'is_featured' => $post['is_featured'],
                ]
            );
        }
    }

    protected function seedCategories(): array
    {
        return [
            'cardora_guides' => BlogCategory::updateOrCreate(
                ['slug' => 'cardora-guides'],
                [
                    'name' => 'Cardora Guides',
                    'description' => 'In-depth marketplace guides for pricing, listing quality, trust and safer selling.',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            ),
            'card_market' => BlogCategory::updateOrCreate(
                ['slug' => 'card-market'],
                [
                    'name' => 'Card Market',
                    'description' => 'Official card-market developments, release notes and collector pricing context.',
                    'sort_order' => 2,
                    'is_active' => true,
                ]
            ),
            'comics_books' => BlogCategory::updateOrCreate(
                ['slug' => 'comics-books'],
                [
                    'name' => 'Comics & Books',
                    'description' => 'Collector reading for comics, books, anniversary issues and key release context.',
                    'sort_order' => 3,
                    'is_active' => true,
                ]
            ),
            'figures_collectibles' => BlogCategory::updateOrCreate(
                ['slug' => 'figures-collectibles'],
                [
                    'name' => 'Figures & Collectibles',
                    'description' => 'Figures, premium collectibles, display pieces and collector presentation news.',
                    'sort_order' => 4,
                    'is_active' => true,
                ]
            ),
            'collector_news' => BlogCategory::updateOrCreate(
                ['slug' => 'collector-news'],
                [
                    'name' => 'Collector News',
                    'description' => 'Official current-month updates that matter to serious collectors and sellers.',
                    'sort_order' => 5,
                    'is_active' => true,
                ]
            ),
        ];
    }

    protected function resolveAuthors(): array
    {
        $fallback = User::query()->orderBy('id')->firstOrFail();

        $resolve = function (string $handle) use ($fallback): User {
            return User::query()->where('handle', $handle)->first() ?: $fallback;
        };

        return [
            'fallback' => $fallback,
            'andreas' => $resolve('andreas-vault'),
            'nikos' => $resolve('nikos-slabs'),
            'eleni' => $resolve('eleni-figures'),
            'maria' => $resolve('maria-keys'),
            'panos' => $resolve('panos-pops'),
        ];
    }

    protected function posts(): array
    {
        return [
            $this->article(
                categoryKey: 'cardora_guides',
                authorKey: 'nikos',
                slug: 'pos-na-apotimiseis-sosta-mia-karta-prin-ti-valeis-pros-polisi-stin-cardora',
                titleEl: 'Πώς να αποτιμήσεις σωστά μια κάρτα πριν τη βάλεις προς πώληση στην Cardora',
                titleEn: 'How to price a card correctly before listing it on Cardora',
                excerptEl: 'Ένας πλήρης πρακτικός οδηγός για να μη βασίζεσαι μόνο στο ένστικτο: condition, sold comps, ρευστότητα, grading context και σωστή τιμή εισόδου.',
                excerptEn: 'A complete practical guide to card pricing using condition, sold comps, liquidity and grading context instead of guesswork.',
                publishedAt: '2026-04-05 11:30:00',
                introEl: <<<'TEXT'
Η σωστή αποτίμηση μιας κάρτας δεν είναι απλώς θέμα “τι ζητάνε οι άλλοι”. Είναι θέμα ταυτότητας του κομματιού, πραγματικής κατάστασης, βάθους αγοράς και σωστής στρατηγικής για το αν θέλεις γρήγορη πώληση ή premium positioning. Στην Cardora αυτό έχει ακόμη μεγαλύτερη σημασία, γιατί ένα listing με σωστή τιμή, καθαρές πληροφορίες και σωστό trust setup τραβά πιο σοβαρούς αγοραστές και λιγότερα άσκοπα παζάρια.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Ξεκίνα από την ακριβή ταυτότητα της κάρτας',
                        <<<'TEXT'
Το πρώτο λάθος που κάνουν πολλοί sellers είναι ότι αποτιμούν “τον παίκτη” ή “το set” και όχι το συγκεκριμένο item. Για να τιμολογήσεις σωστά πρέπει πρώτα να ξέρεις ακριβώς τι έχεις: set, year, parallel, grading company, serial numbering, card number και φυσικά αν μιλάμε για raw ή graded κομμάτι.

Αν το αντικείμενο δεν ταυτοποιηθεί σωστά, όλα τα comps που θα δεις μετά είναι θολά. Ένα /25 gold, ένα mojo, ένα silver, ένα base rookie και ένα case hit μπορεί να μοιάζουν οπτικά “κοντά”, αλλά η αγορά τα διαβάζει τελείως διαφορετικά. Η σωστή περιγραφή ήδη από το πρώτο βήμα σε προστατεύει από λάθος τιμολόγηση και σε βοηθά να βρεις πιο σχετικές συγκρίσεις.
TEXT,
                        [
                            'Έλεγξε πρώτα set, χρονιά, parallel, serial και card number.',
                            'Σε graded κάρτα κατέγραψε ακριβώς grade, slab brand και τυχόν ειδικά στοιχεία label.',
                            'Σε raw κάρτα ξεχώρισε αν υπάρχουν surface, corner ή centering θέματα πριν αρχίσεις τα comps.',
                        ]
                    ),
                    $this->section(
                        'Μη διαβάζεις μόνο asking prices· ψάξε πραγματικές πωλήσεις',
                        <<<'TEXT'
Το ότι μια κάρτα είναι ανεβασμένη κάπου στα 400 ευρώ δεν σημαίνει ότι αξίζει 400 ευρώ. Οι αγγελίες δείχνουν προσδοκίες, όχι πραγματική αγορά. Αυτό που σε βοηθά είναι τα recent sold comps, ειδικά όταν είναι κοντά σε ημερομηνία, grade και parallel με το δικό σου κομμάτι.

Όταν η αγορά είναι λεπτή και υπάρχουν λίγες καταγεγραμμένες πωλήσεις, δεν κοιτάς μόνο την τελευταία τιμή. Κοιτάς το εύρος: ποια είναι η χαμηλή ζώνη όπου πουλά γρήγορα, ποια είναι η premium ζώνη όπου πουλά πιο αργά και τι συμβαίνει όταν υπάρχει hype, release, injury, championship ή grading pop pressure. Η Cardora λειτουργεί καλύτερα όταν το listing σου έχει λογική που ο σοβαρός buyer μπορεί να καταλάβει.
TEXT,
                        [
                            'Τα recent sold comps αξίζουν περισσότερο από τα active asks.',
                            'Όταν υπάρχουν λίγες πωλήσεις, χρησιμοποίησε εύρος τιμών και όχι ένα νούμερο σαν απόλυτη αλήθεια.',
                            'Κατέγραψε αν η αγορά κινείται από hype, event, injury risk ή off-season σιωπή.',
                        ]
                    ),
                    $this->section(
                        'Η κατάσταση της κάρτας αλλάζει όλο το αποτέλεσμα',
                        <<<'TEXT'
Ακόμη και στο ίδιο card ID, η αξία αλλάζει δραστικά ανάλογα με την κατάσταση. Μια raw κάρτα που “δείχνει καθαρή” δεν σημαίνει αυτόματα ότι αξίζει σαν PSA 10 candidate. Αν έχει print lines, soft corner, surface haze ή εμφανές centering issue, η αγορά το προεξοφλεί πολύ γρήγορα και συνήθως σκληρά.

Σε graded κάρτες η εικόνα είναι ακόμη πιο ξεκάθαρη: η διαφορά ανάμεσα σε 9, 9.5 και 10 δεν είναι γραμμική. Σε premium rookies ή χαμηλά numbered parallels, το top grade συχνά λειτουργεί σαν διαφορετικό asset class. Γι’ αυτό ένας seller που θέλει να αποτιμήσει σοβαρά πρέπει να ξέρει όχι μόνο “τι card είναι”, αλλά και σε ποιο quality bucket τον βάζει ο αγοραστής.
TEXT
                    ),
                    $this->section(
                        'Διάλεξε στρατηγική Cardora και όχι απλώς ένα νούμερο',
                        <<<'TEXT'
Στην πράξη δεν υπάρχει μόνο “η σωστή τιμή”. Υπάρχει η σωστή στρατηγική. Αν θέλεις ταχύτητα, μπαίνεις πιο κοντά στο χαμηλό εύρος των comps. Αν θέλεις premium positioning, χρειάζεσαι καλύτερο visual setup, ακριβέστερο description και ξεκάθαρη αιτιολόγηση της αξίας. Αν θέλεις offers, πρέπει να αφήσεις χώρο χωρίς να δίνεις την εντύπωση ότι η αρχική τιμή είναι τυχαία.

Η Cardora ευνοεί listings που είναι καθαρά, τεκμηριωμένα και λογικά τιμολογημένα. Ο σοβαρός buyer δεν πληρώνει μόνο για το card. Πληρώνει για τη βεβαιότητα ότι κατάλαβε τι αγοράζει, πώς θα σταλεί και γιατί η τιμή στέκει. Εκεί ακριβώς κερδίζει ο seller που αποτίμησε σωστά πριν δημοσιεύσει.
TEXT,
                        [
                            'Χαμηλότερη τιμή σημαίνει συνήθως ταχύτερη ρευστότητα.',
                            'Premium τιμή απαιτεί premium παρουσίαση και καθαρή αιτιολόγηση.',
                            'Η σωστή τιμή είναι μέρος του trust, όχι απλώς μέρος του margin.',
                        ]
                    ),
                ],
                tags: ['Pricing', 'Cards', 'Condition', 'Seller Guide', 'Cardora'],
                isFeatured: true,
                gradient: 'from-[#7f5b18] via-[#24324f] to-[#0a1120]',
                label: 'Cardora Guide',
                authorRoleEl: 'Ομάδα αγοράς και αποτίμησης Cardora',
                authorRoleEn: 'Cardora market and pricing team',
            ),
            $this->article(
                categoryKey: 'cardora_guides',
                authorKey: 'andreas',
                slug: 'pos-na-stiseis-premium-listing-kai-asfali-apostoli-choris-na-chaseis-trust-i-axia',
                titleEl: 'Πώς να στήσεις premium listing και ασφαλή αποστολή χωρίς να χάσεις trust ή αξία',
                titleEn: 'How to build a premium listing and safe shipment without losing trust or value',
                excerptEl: 'Από το hero photo μέχρι τη συσκευασία και το protected checkout, όλα όσα ξεχωρίζουν ένα σοβαρό listing από μια πρόχειρη αγγελία.',
                excerptEn: 'From the hero photo to packing and protected checkout, a full guide to building a premium listing.',
                publishedAt: '2026-04-05 09:30:00',
                introEl: <<<'TEXT'
Ένα premium listing δεν είναι μόνο “όμορφο”. Είναι λειτουργικό. Βοηθά τον buyer να καταλάβει γρήγορα τι βλέπει, μειώνει αβεβαιότητα, περιορίζει τα άσκοπα μηνύματα και προστατεύει την αξία του αντικειμένου μέχρι το checkout. Όταν αυτό συνδυάζεται με σωστή αποστολή και το protected flow της Cardora, το αποτέλεσμα είναι πιο καθαρό trust και καλύτερο conversion.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Η πρώτη εικόνα πρέπει να λύνει απορίες, όχι να δημιουργεί',
                        <<<'TEXT'
Το hero image είναι η στιγμή που ο buyer αποφασίζει αν θα μείνει ή θα φύγει. Σε κάρτες θέλει καθαρό front, σωστό crop, ουδέτερο φόντο και καθαρή ορατότητα slab ή surface. Σε φιγούρες θέλει να φαίνεται box condition, σημαντικά accessories και το scale του αντικειμένου. Σε books ή comics θέλει καθαρή μπροστινή όψη, spine όπου χρειάζεται και βασικά στοιχεία έκδοσης.

Το λάθος είναι να αντιμετωπίζεις την πρώτη εικόνα σαν “κάτι που απλώς ανεβάζω”. Η πρώτη εικόνα κουβαλάει ήδη μέρος της αποτίμησης. Αν δείχνει μπερδεμένη, σκοτεινή ή πρόχειρη, ο buyer προεξοφλεί και χαμηλότερο επίπεδο φροντίδας στο υπόλοιπο listing και στην αποστολή.
TEXT
                    ),
                    $this->section(
                        'Το description πρέπει να είναι σύντομο, αλλά πλήρες',
                        <<<'TEXT'
Premium listing δεν σημαίνει μεγάλο wall of text. Σημαίνει σωστή ιεράρχηση πληροφορίας. Πρώτα τι είναι το κομμάτι, μετά ποια είναι η κατάστασή του, μετά τι ακριβώς παραλαμβάνει ο αγοραστής και τέλος οτιδήποτε ιδιαίτερο: flaws, signatures, inserts, missing accessories, edition specifics, COA ή storage notes.

Η σαφήνεια εδώ δεν είναι αισθητικό θέμα. Είναι προστασία. Όσο πιο καθαρά έχεις περιγράψει το αντικείμενο, τόσο πιο εύκολο είναι να σταθεί σωστά η παραγγελία, το review και τυχόν dispute handling. Στην Cardora ένα καλό listing βοηθά και το moderation να εγκρίνει γρηγορότερα και τον buyer να αγοράσει με περισσότερη σιγουριά.
TEXT,
                        [
                            'Δήλωσε πάντα defects, ελλείψεις ή αντικαταστάσεις.',
                            'Μη θάβεις το σημαντικό στοιχείο στο τέλος του κειμένου.',
                            'Ό,τι δεν φαίνεται στις φωτογραφίες, πρέπει να αποσαφηνίζεται στο description.',
                        ]
                    ),
                    $this->section(
                        'Η αποστολή είναι μέρος του προϊόντος',
                        <<<'TEXT'
Για premium collectible, η αποστολή δεν είναι μεταφορικό “πακέτο έξω από την αγγελία”. Είναι κομμάτι της εμπειρίας και μέρος της ευθύνης του seller. Card loaders, team bags, semi-rigids, bubble wrap, corner protection, outer box, moisture barrier και σωστό fill material δεν είναι υπερβολή. Είναι η γραμμή ανάμεσα σε ασφαλή παράδοση και χαμένη αξία.

Αυτό ισχύει ακόμη περισσότερο στα κομμάτια με κουτί, graded slabs, signed items ή fragile displays. Αν η συσκευασία δεν είναι αντάξια της αξίας, ο buyer αισθάνεται ότι πληρώνει premium για πρόχειρη διαχείριση. Αντίθετα, όταν η αποστολή είναι καθαρά δομημένη, η αξία του listing υποστηρίζεται μέχρι το τέλος της παραγγελίας.
TEXT,
                        [
                            'Cards: sleeve, semi-rigid ή toploader, team bag και extra board support.',
                            'Figures: inner protection, corner reinforcement και σταθερό outer box.',
                            'Books & comics: flat support, moisture barrier και καθαρό edge protection.',
                        ]
                    ),
                    $this->section(
                        'Το trust ολοκληρώνεται στο checkout και στο payout flow',
                        <<<'TEXT'
Ο buyer δεν βλέπει μόνο το listing. Βλέπει και αν η πλατφόρμα λειτουργεί με κανόνες. Στην Cardora η παραγγελία περνά από protected checkout, ελεγχόμενη επικοινωνία και release logic πριν ολοκληρωθεί το payout. Αυτό σημαίνει ότι το premium listing δεν στέκεται μόνο του. Δένει με premium post-purchase εμπειρία.

Γι’ αυτό ο seller που θέλει υψηλότερη ποιότητα πωλήσεων πρέπει να σκέφτεται ενιαία: listing, επικοινωνία, packing, tracking, confirmation και μετά release. Όταν όλα αυτά είναι καθαρά, η αξία του αντικειμένου δεν στηρίζεται μόνο στην αγορά. Στηρίζεται και στην εμπιστοσύνη που χτίζεται γύρω του.
TEXT
                    ),
                ],
                tags: ['Listings', 'Shipping', 'Trust', 'Cardora', 'Seller Guide'],
                isFeatured: false,
                gradient: 'from-[#5c4d1a] via-[#1d2840] to-[#09111d]',
                label: 'Seller Playbook',
                authorRoleEl: 'Συντακτική ομάδα εμπειρίας πωλητών Cardora',
                authorRoleEn: 'Cardora seller experience editorial team',
            ),
            $this->article(
                categoryKey: 'card_market',
                authorKey: 'maria',
                slug: 'rory-mcilroy-masters-2025-ti-simainei-gia-tin-agora-ton-golf-cards',
                titleEl: 'Rory McIlroy και Masters 2025: τι σημαίνει για την αγορά των golf cards',
                titleEn: 'Rory McIlroy and the 2025 Masters: what it means for the golf card market',
                excerptEl: 'Η PSA δείχνει γιατί μια μεγάλη αθλητική στιγμή δεν αρκεί από μόνη της. Η πραγματική ιστορία είναι η σπανιότητα, το μικρό catalog και το πώς ωριμάζει η αγορά του golf collecting.',
                excerptEn: 'PSA’s new piece on Rory McIlroy shows how legacy, scarcity and a tiny card catalog shape the golf card market.',
                publishedAt: '2026-04-04 18:15:00',
                introEl: <<<'TEXT'
Η επίσημη ανάλυση της PSA για τον Rory McIlroy μετά την κατάκτηση του Masters λειτουργεί σαν πολύ καλό μάθημα για κάθε collector που προσπαθεί να διαβάσει σωστά τη σχέση ανάμεσα σε αθλητικό επίτευγμα και συλλεκτική αξία. Το ενδιαφέρον δεν είναι μόνο ότι ο McIlroy έκλεισε τον κύκλο του career Grand Slam. Είναι ότι το hobby ανακάλυψε ξανά πόσο μικρό και άνισο παραμένει το card catalog του golf.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Η μεγάλη στιγμή ανέβασε τη ζήτηση, αλλά η σπανιότητα ήταν ήδη εκεί',
                        <<<'TEXT'
Η PSA περιγράφει τον θρίαμβο του McIlroy ως στιγμή που τράβηξε ξανά τα βλέμματα όχι απλώς πάνω στον αθλητή, αλλά πάνω στην ίδια την προσφορά των καρτών του. Αυτό είναι βασικό: η ζήτηση μπορεί να εκτοξευθεί μέσα σε μία νύχτα, αλλά η αγορά αποτιμά με ένταση μόνο όταν συναντά περιορισμένη και ανομοιόμορφη προσφορά.

Στην περίπτωση του Rory, η ιστορική ασυνέχεια των licensed golf releases δημιούργησε ένα περιβάλλον όπου η αγορά δεν έχει το εύρος που βρίσκεις σε basketball, baseball ή football. Άρα όταν έρχεται ένα event με legacy βάρος, η αξία δεν απλώνεται ομαλά· συγκεντρώνεται πιο βίαια σε λίγα key cards και κυρίως σε συγκεκριμένες premium εκδόσεις.
TEXT
                    ),
                    $this->section(
                        'Το μικρό catalog αλλάζει τον τρόπο που διαβάζεις comps',
                        <<<'TEXT'
Η PSA σημειώνει ότι σύμφωνα με το Trading Card Database ο συνολικός όγκος των διαφορετικών McIlroy cards, μαζί με variations και parallels, είναι εντυπωσιακά μικρός σε σχέση με άλλα μεγάλα ονόματα του σύγχρονου αθλητισμού. Αυτό για έναν seller ή buyer σημαίνει κάτι πολύ πρακτικό: δεν μπορείς να τιμολογείς σαν να υπάρχει βαθιά, σταθερή και καθημερινή αγορά.

Σε στενά catalogs, κάθε comp χρειάζεται περισσότερο context. Δεν αρκεί να δεις μία υψηλή πώληση και να την κάνεις benchmark για όλα. Πρέπει να καταλάβεις αν μιλάμε για key autograph, για low-numbered premium issue, για early SI Kids panel piece ή για card με περιορισμένο PSA population. Η αγορά τιμωρεί γρήγορα τις γενικεύσεις.
TEXT,
                        [
                            'Μικρό catalog σημαίνει πιο αραιά comps και μεγαλύτερη μεταβλητότητα.',
                            'Population, numbering και release context μετράνε περισσότερο από το όνομα μόνο του.',
                            'Ένα μεγάλο career event δεν εξισώνει αυτόματα όλα τα cards του αθλητή.',
                        ]
                    ),
                    $this->section(
                        'Γιατί οι premium autos τραβούν δυσανάλογα την προσοχή',
                        <<<'TEXT'
Στο ίδιο άρθρο η PSA δείχνει ότι ο πραγματικός premium πυρήνας του McIlroy market βρίσκεται στις χαμηλά αριθμημένες autograph και dual-auto κάρτες, όχι απλώς στις πιο “γνωστές” εκδόσεις. Αυτό είναι αρκετά κοινό μοτίβο σε μικρότερες αθλητικές κατηγορίες: όταν δεν υπάρχει τεράστιο μαζικό catalog, το hobby πηγαίνει πιο γρήγορα στα scarcity anchors.

Για τους collectors αυτό σημαίνει ότι η αγορά ωριμάζει επιλεκτικά. Δεν ανεβαίνει όλο το φάσμα το ίδιο. Ανεβαίνουν πρώτα τα κομμάτια που συνδυάζουν ιστορικό story, χαμηλή διαθεσιμότητα, ισχυρό visual identity και σαφή θέση μέσα στο collecting hierarchy του παίκτη.
TEXT
                    ),
                    $this->section(
                        'Τι κρατάμε ως lesson για Cardora sellers',
                        <<<'TEXT'
Αν πουλάς sport cards στην Cardora, η υπόθεση McIlroy είναι χρήσιμη γιατί θυμίζει ότι η σωστή αποτίμηση ξεκινά από το market structure και όχι από τον ενθουσιασμό. Όταν ένα κομμάτι ανήκει σε λεπτή αγορά, χρειάζεσαι πιο προσεκτική τιμή, πιο καθαρό explanation και περισσότερο υπομονετικό positioning.

Αντίστοιχα, αν αγοράζεις, πρέπει να ξεχωρίζεις το genuine long-term scarcity από το απλό momentary hype. Οι πιο ώριμοι collectors δεν πληρώνουν μόνο τη μεγάλη νίκη. Πληρώνουν τη θέση που έχει ένα συγκεκριμένο card μέσα σε ολόκληρο το οικοσύστημα προσφοράς του αθλητή.
TEXT
                    ),
                ],
                tags: ['PSA', 'Rory McIlroy', 'Golf Cards', 'Card Value', 'Collector News'],
                isFeatured: true,
                gradient: 'from-[#6f5316] via-[#243650] to-[#09131f]',
                label: 'Official Source',
                authorRoleEl: 'Συντακτική ομάδα Cardora',
                authorRoleEn: 'Cardora editorial team',
                source: [
                    'publisher' => 'PSA',
                    'title' => 'Rory McIlroy’s 2025 Masters Title Put Eyeballs on His Shockingly Small Card Catalog',
                    'url' => 'https://www.psacard.com/articles/articleview/15699/rory-mcilroy-masters-champion-grand-slam-cards',
                    'published_at' => '2026-04-01',
                ],
                sourceNoteEl: 'Το άρθρο βασίζεται στην επίσημη ανάλυση της PSA για το πώς η κατάκτηση του Masters από τον Rory McIlroy επηρέασε το ενδιαφέρον γύρω από ένα ασυνήθιστα μικρό golf card catalog.',
            ),
            $this->article(
                categoryKey: 'collector_news',
                authorKey: 'nikos',
                slug: 'ti-simainei-to-where-to-find-psa-in-april-2026-gia-sovarous-sellers-kai-graders',
                titleEl: 'Τι σημαίνει το “Where to Find PSA in April 2026” για σοβαρούς sellers και graders',
                titleEn: 'What PSA’s April 2026 schedule means for serious sellers and graders',
                excerptEl: 'Η επίσημη PSA λίστα του Απριλίου δεν είναι απλώς ημερολόγιο events. Είναι εργαλείο για σωστό timing σε submissions, grading pipelines και market planning.',
                excerptEn: 'PSA’s April 2026 schedule is more than an event list. It is a timing tool for submissions, grading pipelines and market planning.',
                publishedAt: '2026-04-04 15:10:00',
                introEl: <<<'TEXT'
Η επίσημη σελίδα “Where to Find PSA in April 2026” μπορεί να μοιάζει σαν απλό calendar post, αλλά για collectors και resellers είναι ουσιαστικά εργαλείο planning. Όταν μια grading εταιρεία δημοσιεύει αναλυτικά πού θα δέχεται in-person submissions μέσα στον μήνα, δίνει έμμεσα σήματα και για το πώς κινείται το service pipeline, ποιες αγορές εξυπηρετεί πιο έντονα και πότε ένας seller έχει νόημα να οργανώσει συγκεκριμένο submission wave.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Το in-person submission δεν είναι απλώς ευκολία',
                        <<<'TEXT'
Η PSA ανακοινώνει για τον Απρίλιο Walk-In Wednesdays σε Jersey City και Santa Ana, αλλά και ειδικά submission events σε Texas, San Jose, London, Paris και New York. Για έναν collector αυτό δεν είναι μόνο θέμα ευκολίας μετακίνησης. Είναι θέμα ελέγχου του ρίσκου και του χρόνου. Όσο πιο οργανωμένα στήνεις το submission σου, τόσο πιο καθαρά δουλεύεις με cashflow, release timing και resale expectations.

Σε μια αγορά όπου αρκετοί sellers βασίζονται σε graded inventory για την επόμενη κίνηση του καταστήματός τους ή της προσωπικής συλλογής τους, το πότε θα στείλεις έχει σημασία σχεδόν όσο και το τι θα στείλεις. Η επίσημη PSA λίστα επιτρέπει να δουλέψεις πιο επαγγελματικά: να κάνεις pre-sort, να κλείσεις ποιοτικά lots και να αποφασίσεις ποια κομμάτια αξίζει να φύγουν άμεσα.
TEXT
                    ),
                    $this->section(
                        'Η γεωγραφία του schedule δείχνει πού υπάρχει ζήτηση και υποδομή',
                        <<<'TEXT'
Το γεγονός ότι το πρόγραμμα του μήνα απλώνεται από New Jersey και California μέχρι London Card Show, Paris και New York event points δείχνει ξεκάθαρα ότι η PSA δεν λειτουργεί μόνο με fixed-office λογική. Λειτουργεί και με οικοσύστημα φυσικής παρουσίας, συνεργατών και collector hubs. Αυτό είναι χρήσιμο για όποιον προσπαθεί να διαβάσει πού κατευθύνεται η επίσημη grading δραστηριότητα.

Για ευρωπαϊκούς sellers και αγοραστές, η ύπαρξη stops όπως London και Paris βοηθά να καταλάβουν ότι η περιοχή δεν είναι απλώς secondary audience. Είναι μέρος του πραγματικού flow. Αυτό δεν σημαίνει ότι κάθε submission γίνεται ξαφνικά φθηνό ή γρήγορο, αλλά σημαίνει ότι το planning μπορεί να γίνει πιο ρεαλιστικό και λιγότερο αποσπασματικό.
TEXT
                    ),
                    $this->section(
                        'Πότε αξίζει να στείλεις και πότε όχι',
                        <<<'TEXT'
Το πιο ώριμο grading decision δεν είναι “στέλνω ό,τι φαίνεται ωραίο”. Είναι “στέλνω ό,τι έχει νόημα να δεσμεύσει χρόνο και κεφάλαιο”. Αν έχεις cards με αμφίβολη grade outcome, λεπτό spread ανάμεσα σε raw και graded τιμή ή ασταθή hype, το official schedule από μόνο του δεν αρκεί για να δικαιολογήσει submission. Αντίθετα, όταν έχεις κομμάτια με ισχυρή διαφορά αξίας ανά grade tier ή cards που χρειάζονται certification για να σταθούν σε premium marketplace, τότε το timing του schedule αποκτά πραγματικό βάρος.

Αυτό είναι ιδιαίτερα σημαντικό για Cardora sellers που θέλουν να παρουσιάσουν σοβαρό inventory. Δεν χρειάζεται να γίνουν όλα graded. Χρειάζεται να grading-άρεις μόνο εκεί που το certification βελτιώνει καθαρά την κατανόηση, το trust και την πιθανότητα premium sale.
TEXT,
                        [
                            'Στείλε όταν η grade ladder δικαιολογεί το κόστος και τον χρόνο.',
                            'Μη στέλνεις επειδή απλώς “άνοιξε event κοντά σου”.',
                            'Οργάνωσε το submission wave μαζί με το listing plan και όχι ξεχωριστά από αυτό.',
                        ]
                    ),
                    $this->section(
                        'Τι κρατάμε για τον μήνα',
                        <<<'TEXT'
Η ουσία του επίσημου PSA schedule δεν είναι το marketing. Είναι η προβλεψιμότητα. Για collectors που σκέφτονται επαγγελματικά, το να ξέρεις τον ρυθμό του μήνα σου επιτρέπει να δουλέψεις καλύτερα με grading queue, listing calendar και αγοραστικό πλάνο.

Σε αγορές που ωριμάζουν, η οργάνωση κερδίζει συνήθως περισσότερο από τον ενθουσιασμό. Και αυτό ακριβώς είναι το μήνυμα πίσω από μια “απλή” λίστα events: όποιος λειτουργεί με δομή, κερδίζει χρόνο, clarity και συχνά καλύτερη απόδοση.
TEXT
                    ),
                ],
                tags: ['PSA', 'Grading', 'Submission Events', 'Collector News', 'April 2026'],
                isFeatured: false,
                gradient: 'from-[#4f3d14] via-[#20324b] to-[#09121d]',
                label: 'Official Source',
                authorRoleEl: 'Συντακτική ομάδα Cardora',
                authorRoleEn: 'Cardora editorial team',
                source: [
                    'publisher' => 'PSA',
                    'title' => 'Where to Find PSA in April 2026',
                    'url' => 'https://www.psacard.com/articles/articleview/15641/where-to-find-psa-in-april-2026',
                    'published_at' => '2026-04-01',
                ],
                sourceNoteEl: 'Το άρθρο βασίζεται στο επίσημο πρόγραμμα φυσικής παρουσίας και submission events που δημοσίευσε η PSA για τον Απρίλιο του 2026.',
            ),
            $this->article(
                categoryKey: 'collector_news',
                authorKey: 'andreas',
                slug: 'psa-comes-to-new-york-giati-ena-sygkekrimeno-live-event-metraei-gia-submissions-kai-market-timing',
                titleEl: 'PSA comes to New York: γιατί ένα συγκεκριμένο live event μετράει για submissions και market timing',
                titleEn: 'PSA comes to New York: why one live event matters for submissions and market timing',
                excerptEl: 'Η επίσημη ανακοίνωση της PSA για το New York stop της 25ης Απριλίου δείχνει πώς τα targeted live events λειτουργούν σαν πρακτικοί κόμβοι για graders, dealers και serious collectors.',
                excerptEn: 'PSA’s official New York event announcement shows how targeted live stops become practical hubs for graders, dealers and serious collectors.',
                publishedAt: '2026-04-04 09:20:00',
                introEl: <<<'TEXT'
Δεν έχουν όλα τα grading events το ίδιο βάρος. Κάποια είναι απλές παρουσίες. Κάποια όμως λειτουργούν σαν σημείο συνάντησης για submissions, networking, dealer activity και market intelligence. Η επίσημη PSA ανακοίνωση για το event της 25ης Απριλίου στη Νέα Υόρκη ανήκει περισσότερο στη δεύτερη κατηγορία, γιατί δένει το grading service με μια από τις πιο ενεργές collecting μητροπόλεις.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Η Νέα Υόρκη είναι αγορά, όχι απλώς τοποθεσία',
                        <<<'TEXT'
Όταν η PSA πηγαίνει στη Νέα Υόρκη, δεν επιλέγει απλώς ακόμη ένα stop. Επιλέγει έναν χώρο όπου συνυπάρχουν μεγάλοι collectors, dealers, breakers, pop-culture buyers και cross-category buyers με υψηλή αγοραστική διάθεση. Αυτό αλλάζει την ποιότητα του event, γιατί το submission περιβάλλον συναντά άμεσα το resale environment.

Για έναν serious collector, τέτοιες πόλεις λειτουργούν σαν επιταχυντές πληροφορίας. Δεν μαθαίνεις μόνο πού στέλνεις. Μαθαίνεις τι κινείται, τι ζητιέται, ποια categories ανεβαίνουν και πώς μιλάνε οι έμπειροι συμμετέχοντες για τον επόμενο κύκλο της αγοράς.
TEXT
                    ),
                    $this->section(
                        'Τα live events βελτιώνουν και την πειθαρχία του submission',
                        <<<'TEXT'
Υπάρχει και μια πιο πρακτική πλευρά: όταν ένα συγκεκριμένο event έχει ημερομηνία, το submission plan αποκτά πειθαρχία. Ο collector προετοιμάζει τις κάρτες του, ξανακοιτά condition, αποφασίζει ποια κομμάτια θα σταλούν, ποια θα μείνουν raw και ποια δεν αξίζει να δεσμεύσουν χρόνο ή κεφάλαιο.

Αυτός ο εξαναγκασμός σε προετοιμασία είναι θετικός. Στις πιο ώριμες συλλεκτικές αγορές, οι καλύτερες αποφάσεις δεν βγαίνουν όταν κάποιος “στείλει αυθόρμητα”. Βγαίνουν όταν έχει προηγηθεί καθαρή ταξινόμηση του inventory, ρεαλιστική προσδοκία για grades και σαφής στόχος για το πού θα πουληθεί το αποτέλεσμα.
TEXT
                    ),
                    $this->section(
                        'Γιατί αυτό ενδιαφέρει και sellers που δεν θα πάνε στο event',
                        <<<'TEXT'
Ακόμη κι αν κάποιος δεν βρίσκεται στη Νέα Υόρκη, τέτοια επίσημα events έχουν έμμεση αξία. Δημιουργούν στιγμές όπου συγκεντρώνονται submissions και αυξάνεται το attention γύρω από grading, premium inventory και high-trust selling. Αυτό μπορεί να επηρεάσει τον τρόπο που δουλεύουν οι watchlists, οι online αναζητήσεις και τα timing windows για serious listings.

Με άλλα λόγια, ένα official PSA event δεν λειτουργεί μόνο τοπικά. Στέλνει σήμα σε ολόκληρο το hobby ότι η αγορά βρίσκεται ξανά σε φάση κινητικότητας. Και αυτό είναι χρήσιμη πληροφορία ακόμη και για sellers που δραστηριοποιούνται αποκλειστικά online.
TEXT,
                        [
                            'Κάθε μεγάλο grading stop στέλνει indirect signal και στο online marketplace.',
                            'Τα submission waves συχνά συμπίπτουν με αυξημένο ενδιαφέρον για premium inventory.',
                            'Η σωστή προετοιμασία inventory αξίζει ακόμη και αν τελικά δεν γίνει submission στο συγκεκριμένο event.',
                        ]
                    ),
                    $this->section(
                        'Το πρακτικό lesson για την Cardora',
                        <<<'TEXT'
Για την Cardora, τέτοιες ανακοινώσεις είναι χρήσιμες γιατί βοηθούν τους sellers να σκέφτονται με πιο επαγγελματικό ρυθμό. Αν έχεις material που ίσως γίνει graded, αν σχεδιάζεις premium listings ή αν θέλεις να παρακολουθήσεις πού κατευθύνεται η αγορά υψηλότερης εμπιστοσύνης, ένα επίσημο PSA stop σαν της Νέας Υόρκης λειτουργεί σαν καλό χρονικό ορόσημο.

Η ουσία είναι απλή: οι σοβαρές αγορές ευνοούν όσους κινούνται με calendar awareness. Και τα official event posts είναι ακριβώς ένα από τα εργαλεία που βοηθούν να δημιουργηθεί αυτή η πειθαρχία.
TEXT
                    ),
                ],
                tags: ['PSA', 'New York', 'Submission Events', 'Grading', 'Collector News'],
                isFeatured: false,
                gradient: 'from-[#815a1d] via-[#24324e] to-[#0a1320]',
                label: 'Official Source',
                authorRoleEl: 'Συντακτική ομάδα Cardora',
                authorRoleEn: 'Cardora editorial team',
                source: [
                    'publisher' => 'PSA',
                    'title' => 'PSA Comes to New York on April 25',
                    'url' => 'https://www.psacard.com/articles/articleview/15738/psa-comes-to-new-york-april-25',
                    'published_at' => '2026-04-03',
                ],
                sourceNoteEl: 'Το άρθρο βασίζεται στην επίσημη PSA ανακοίνωση για το event της 25ης Απριλίου 2026 στη Νέα Υόρκη.',
            ),
            $this->article(
                categoryKey: 'comics_books',
                authorKey: 'maria',
                slug: 'cgc-comics-hot-list-april-2026-ti-mas-leei-gia-to-pou-paei-to-interest',
                titleEl: 'CGC Comics Hot List – April 2026: τι μας λέει για το πού πηγαίνει το collector interest',
                titleEn: 'CGC Comics Hot List – April 2026: what it says about collector interest',
                excerptEl: 'Η μηνιαία hot list της CGC είναι χρήσιμη όχι μόνο για spec picks, αλλά για να δεις πώς ενώνονται media momentum, first appearances και ιστορικά keys.',
                excerptEn: 'CGC’s April 2026 hot list is useful not just for speculation, but for reading the mix of media momentum, first appearances and historic keys.',
                publishedAt: '2026-04-04 12:20:00',
                introEl: <<<'TEXT'
Η επίσημη Hot List της CGC για τον Απρίλιο του 2026 είναι από τα πιο χρήσιμα monthly snapshots για collectors, γιατί δεν κοιτάζει μόνο “τι αρέσει στον κόσμο”, αλλά ποιες κατηγορίες τίτλων τραβούν πραγματικά την τρέχουσα submission και buying προσοχή. Αυτό έχει σημασία γιατί οι αγορές κόμικς δεν κινούνται από έναν μόνο λόγο. Συνήθως κινούνται από την ένωση ιστορικού significance, media catalyst και αφηγηματικής αναγνώρισης από το fandom.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Τα ιστορικά first appearances μένουν στην κορυφή όταν βρίσκουν νέο catalyst',
                        <<<'TEXT'
Η CGC βάζει στο προσκήνιο το Green Lantern #87 για τον John Stewart και το Batman #181 για την Poison Ivy. Και στις δύο περιπτώσεις βλέπουμε το ίδιο μοτίβο: τα βιβλία δεν είναι “καινούργια discoveries”. Είναι παλιά, θεμελιωμένα keys που αποκτούν νέο κύμα ενδιαφέροντος επειδή η σημερινή pop culture τους δίνει ξανά spotlight.

Αυτό είναι βασικό lesson για κάθε comic seller. Τα μεγαλύτερα swings δεν έρχονται μόνο από νέο issue hype. Έρχονται συχνά όταν ένα ήδη γνωστό key ξαναμπαίνει στη συζήτηση με πιο καθαρό media angle. Τότε ο collector δεν αγοράζει απλώς νέο θόρυβο. Αγοράζει βιβλίο που ήδη έχει ιστορικό βάρος.
TEXT
                    ),
                    $this->section(
                        'Η media ορατότητα δεν αρκεί μόνη της, αλλά επιταχύνει το ήδη δυνατό material',
                        <<<'TEXT'
Στην περίπτωση του John Stewart, η CGC συνδέει το renewed interest με την προσμονή για τη σειρά “Lanterns”. Στην Poison Ivy, το βιβλίο ενισχύεται τόσο από κινηματογραφικό casting discussion όσο και από τη συνεχή δημοφιλία της χαρακτήρα στο animation. Αυτές οι αφορμές δεν κατασκευάζουν αξία από το μηδέν. Λειτουργούν σαν επιταχυντές σε τίτλους που η αγορά ήδη αναγνωρίζει ως σοβαρούς.

Γι’ αυτό και ένας ώριμος collector δεν βλέπει απλώς headline news. Εξετάζει αν η δημοσιότητα πέφτει πάνω σε βιβλίο με καθαρό πρώτο κλειδί, ιστορική θέση και μακροχρόνια βάση συλλεκτών. Όταν όλα αυτά συμπίπτουν, το ενδιαφέρον συνήθως δεν είναι επιφανειακό.
TEXT
                    ),
                    $this->section(
                        'Star Wars #1 θυμίζει ότι η πολιτισμική βαρύτητα μετρά όσο και ο χαρακτήρας',
                        <<<'TEXT'
Η παρουσία του Star Wars #1 στη λίστα είναι εξαιρετικά διδακτική, γιατί το βιβλίο δεν στηρίζεται σε ένα μόνο “first appearance” με τον κλασικό superhero τρόπο. Η βαρύτητά του είναι πιο πολιτισμική και πιο franchise-wide. Πρόκειται για κομμάτι που συνδέεται με μια τεράστια κινηματογραφική μυθολογία και με δεκαετίες comic expansion.

Όταν η CGC επισημαίνει την επιστροφή του Star Wars στις αίθουσες μέσω του “The Mandalorian & Grogu”, ουσιαστικά δείχνει πώς ένα franchise catalyst μπορεί να φέρει στην επιφάνεια παλαιότερα material που λειτουργούν σαν ιστορικά σημεία εισόδου για τον collector. Αυτό είναι άλλο είδος δύναμης από ένα απλό “έσκασε νέο villain”.
TEXT,
                        [
                            'First appearances, franchise milestones και media relaunches δεν έχουν ίδια λογική.',
                            'Η CGC hot list βοηθά να ξεχωρίζεις ποιο είδος ζήτησης κινείται κάθε μήνα.',
                            'Για sellers, το σωστό copy πρέπει να τονίζει το σωστό story του βιβλίου και όχι γενικά “είναι hot”.',
                        ]
                    ),
                    $this->section(
                        'Πώς χρησιμοποιείται σωστά μια hot list στην Cardora',
                        <<<'TEXT'
Μια hot list δεν είναι αυτόματη εντολή αγοράς. Είναι φίλτρο προσοχής. Σου δείχνει πού στρέφεται το βλέμμα της αγοράς, αλλά εσύ πρέπει να ελέγξεις grade, restoration concerns, page quality, recent sold range και αν το συγκεκριμένο copy που έχεις πραγματικά αξίζει να ανεβεί premium.

Στην Cardora αυτό μεταφράζεται σε πιο καθαρά listings: σωστή ταυτότητα issue, σαφής αναφορά αν πρόκειται για key appearance, καλές φωτογραφίες μπροστινού/πίσω μέρους ή slab, και αποφυγή υπερβολικής τιμής μόνο και μόνο επειδή “είναι στη hot list”. Ο σοβαρός collector καταλαβαίνει γρήγορα πότε ένα seller έχει διαβάσει το signal και πότε απλώς κυνηγά τον θόρυβο.
TEXT
                    ),
                ],
                tags: ['CGC', 'Comics', 'Hot List', 'Collector News', 'Keys'],
                isFeatured: false,
                gradient: 'from-[#6c4e18] via-[#24314b] to-[#0a1321]',
                label: 'Official Source',
                authorRoleEl: 'Συντακτική ομάδα Cardora',
                authorRoleEn: 'Cardora editorial team',
                source: [
                    'publisher' => 'CGC',
                    'title' => 'CGC Comics Hot List – April 2026',
                    'url' => 'https://www.cgccomics.com/news/article/15085/hottest-comics-green-lantern-batman-star-wars/',
                    'published_at' => '2026-04-02',
                ],
                sourceNoteEl: 'Το άρθρο βασίζεται στη μηνιαία επίσημη CGC Hot List του Απριλίου 2026, η οποία αναδεικνύει βιβλία που κερδίζουν συλλεκτική δυναμική από submissions, media momentum και ιστορικό βάρος.',
            ),
            $this->article(
                categoryKey: 'collector_news',
                authorKey: 'panos',
                slug: 'upcoming-releases-april-2026-apo-tin-cgc-pos-na-diavazeis-to-miniaio-release-calendar',
                titleEl: 'Upcoming Releases: April 2026 από την CGC και πώς να διαβάζεις σωστά το μηνιαίο release calendar',
                titleEn: 'CGC Upcoming Releases April 2026 and how to read a monthly release calendar',
                excerptEl: 'Από TCG και sports cards μέχρι comics, films και games, η επίσημη λίστα της CGC βοηθά να δεις τι μπορεί να επηρεάσει τη συλλεκτική συζήτηση μέσα στον μήνα.',
                excerptEn: 'From TCG and sports cards to comics, films and games, CGC’s official release list helps collectors spot what may shape the month.',
                publishedAt: '2026-04-03 18:10:00',
                introEl: <<<'TEXT'
Η μηνιαία λίστα “Upcoming Releases” της CGC δεν είναι απλώς περιεχόμενο ενημέρωσης. Για collectors είναι εργαλείο προετοιμασίας. Δείχνει τι μπαίνει στην αγορά μέσα στον μήνα και ποια σημεία μπορεί να τραβήξουν ξαφνικά το ενδιαφέρον σε cards, comics, TCGs, games και γενικότερα pop culture collectibles.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Release calendar σημαίνει προετοιμασία, όχι παρορμητικό FOMO',
                        <<<'TEXT'
Η CGC για τον Απρίλιο του 2026 στέκεται σε κυκλοφορίες όπως το Bizarro: Year None, το Secrets of Strixhaven, νέα TCG προϊόντα, το 2025-26 SkyBox Metal Universe Hockey και μεγάλα media releases όπως ο νέος Super Mario τίτλος και σχετικές κινηματογραφικές αφίξεις. Αυτό που έχει σημασία για έναν collector δεν είναι να κυνηγήσει τα πάντα. Είναι να δει πού μπορεί να δημιουργηθεί συγκέντρωση προσοχής.

Όσο πιο νωρίς ξέρεις τι έρχεται, τόσο καλύτερα ξεχωρίζεις τι αξίζει budget, τι αξίζει απλή παρακολούθηση και τι ίσως δημιουργήσει secondary demand σε παλαιότερο material. Έτσι δουλεύει σωστά ένα monthly calendar: δεν σε κάνει να αγοράζεις περισσότερο, σε κάνει να αγοράζεις πιο επιλεκτικά.
TEXT
                    ),
                    $this->section(
                        'Τα νέα προϊόντα επηρεάζουν και τα παλιά',
                        <<<'TEXT'
Όταν η CGC σημειώνει νέο One Piece, Yu-Gi-Oh!, Gundam ή Union Arena προϊόν, δεν μιλά μόνο για sealed hype. Μιλά και για το πώς ένα νέο product wave μπορεί να γυρίσει collectors προς παλαιότερα key cards, early appearances, first waves ή σπανιότερα sealed formats. Το ίδιο ισχύει και σε sports cards, όπου ένα νέο branded release μπορεί να επαναφέρει το ενδιαφέρον σε παλαιότερα inserts, autos και parallel trees.

Αντίστοιχα, σε comics και films, ένα νέο project συχνά ξανανοίγει τη συζήτηση γύρω από first appearances, anniversary issues, adaptation books και fan-favorite characters. Το ημερολόγιο κυκλοφοριών είναι λοιπόν και εργαλείο backward-looking research, όχι μόνο forward-looking excitement.
TEXT
                    ),
                    $this->section(
                        'Τι κάνει έναν collector calendar πραγματικά χρήσιμο',
                        <<<'TEXT'
Ένα καλό release calendar δεν το χρησιμοποιείς μόνο για να θυμηθείς ημερομηνίες. Το χρησιμοποιείς για να συνδέσεις γεγονότα. Αν ένα TCG set φέρνει reimagined old favorites, αν ένα sports release μπαίνει πρώτη φορά κάτω από ισχυρό brand umbrella ή αν ένα film sequel αυξάνει ξανά το visibility ενός legacy franchise, αυτό σημαίνει ότι πρέπει να ξαναδείς την υπάρχουσα συλλογή σου πριν την αγορά.

Εκεί οι ώριμοι collectors κερδίζουν συνήθως περισσότερο. Αντί να κυνηγούν μόνο το “καινούργιο”, διαβάζουν τι θα σημάνει το καινούργιο για ό,τι ήδη υπάρχει στην αγορά. Και πολλές φορές εκεί κρύβεται η καλύτερη ευκαιρία.
TEXT,
                        [
                            'Νέα κυκλοφορία δεν σημαίνει πάντα καλύτερη αγορά από το παλαιότερο σχετικό material.',
                            'Το σωστό calendar reading σε βοηθά να προετοιμάσεις listing, submission ή watchlist πριν ξεκινήσει ο μεγάλος θόρυβος.',
                            'Στην Cardora, ένα timely listing μπορεί να ωφεληθεί όταν δημοσιεύεται λίγο πριν κορυφωθεί η προσοχή σε μια υποκατηγορία.',
                        ]
                    ),
                    $this->section(
                        'Η σωστή χρήση στο Cardora ecosystem',
                        <<<'TEXT'
Για sellers της Cardora, ο μηνιαίος release χάρτης βοηθά σε δύο επίπεδα: πρώτον, στο πότε θα ανεβάσεις σχετικό inventory και δεύτερον, στο πώς θα το περιγράψεις. Αν ξέρεις ότι ο μήνας κουβαλά συγκεκριμένο collector interest, μπορείς να στηρίξεις την παρουσίαση του κομματιού σου με πιο σαφές context.

Για buyers, το κέρδος είναι διαφορετικό: ξέρεις πότε να περιμένεις, πότε να συγκρίνεις και πότε να αποφύγεις αγορά πάνω στο peak του hype. Στην πράξη, η αγορά ευνοεί εκείνους που είναι έτοιμοι λίγο πριν την έκρηξη και όχι εκείνους που τρέχουν όταν η συζήτηση έχει ήδη κοκκινίσει.
TEXT
                    ),
                ],
                tags: ['CGC', 'Release Calendar', 'Collector News', 'TCG', 'Comics'],
                isFeatured: false,
                gradient: 'from-[#7d5b1d] via-[#24324f] to-[#0b1320]',
                label: 'Official Source',
                authorRoleEl: 'Συντακτική ομάδα Cardora',
                authorRoleEn: 'Cardora editorial team',
                source: [
                    'publisher' => 'CGC',
                    'title' => 'Upcoming Releases: April 2026',
                    'url' => 'https://www.cgccomics.com/news/article/15093/',
                    'published_at' => '2026-04-02',
                ],
                sourceNoteEl: 'Το άρθρο βασίζεται στη μηνιαία επίσημη λίστα Upcoming Releases της CGC για τον Απρίλιο του 2026, η οποία συγκεντρώνει comics, TCG, sports card, game και film catalysts του μήνα.',
            ),
            $this->article(
                categoryKey: 'figures_collectibles',
                authorKey: 'eleni',
                slug: 'lego-editions-kai-football-collectibles-ti-allazei-ston-tropo-pou-diabazoume-display-sets',
                titleEl: 'LEGO Editions και football collectibles: τι αλλάζει στον τρόπο που διαβάζουμε τα display sets',
                titleEn: 'LEGO Editions and football collectibles: how they change the display-set conversation',
                excerptEl: 'Η επίσημη ανακοίνωση της LEGO για τα νέα football Editions δείχνει πώς τα display collectibles γίνονται ολοένα πιο ξεκάθαρα collector products και όχι απλά toys.',
                excerptEn: 'LEGO’s official football Editions announcement shows how display collectibles are becoming clearer collector products, not just toys.',
                publishedAt: '2026-04-03 15:10:00',
                introEl: <<<'TEXT'
Η επίσημη ανακοίνωση της LEGO για τη νέα πλατφόρμα LEGO Editions και τη football σειρά του Απριλίου 2026 έχει ιδιαίτερο ενδιαφέρον για collectors, ακόμη κι αν κάποιος δεν συλλέγει παραδοσιακά bricks. Ο λόγος είναι απλός: το product language μοιάζει όλο και περισσότερο με collector display strategy και όλο και λιγότερο με κλασικό toy-first positioning.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Η LEGO μιλά πλέον ανοιχτά για display, icons και easter eggs',
                        <<<'TEXT'
Στην ανακοίνωση της 2ας Απριλίου η LEGO δεν παρουσιάζει απλώς νέους ποδοσφαιρικούς κωδικούς. Παρουσιάζει μια νέα “Editions” λογική, όπου ο fan καλείται να build, display και celebrate την ταυτότητα συγκεκριμένων icons. Η έμφαση σε easter eggs, collectible plaques, signature poses και tribute στοιχεία δεν αφήνει αμφιβολία ότι η στόχευση είναι ο collector που θέλει αντικείμενο για προβολή και όχι μόνο για παιχνίδι.

Αυτό είναι σημαντικό γιατί μετακινεί το κέντρο βάρους από το “πολλά κομμάτια = καλό παιχνίδι” στο “σωστή αφήγηση, σωστό visual payoff και καθαρή σύνδεση με το fandom = ισχυρό collectible”. Για marketplaces όπως η Cardora αυτό είναι κλασικό σημάδι ότι μια κατηγορία ωριμάζει συλλεκτικά.
TEXT
                    ),
                    $this->section(
                        'Η football θεματολογία ανοίγει νέα είδη collector audience',
                        <<<'TEXT'
Η LEGO συνδέει τη σειρά με Ronaldo, Mbappé, Messi και Vini Jr., δίνει minifigure appearances, διαφορετικά piece counts, display highlights και ακριβή pricing tiers. Αυτό πρακτικά σημαίνει ότι το προϊόν πατάει σε πολλές ταυτόχρονα κοινότητες: football fans, LEGO collectors, athlete collectors και display buyers που δεν είναι απαραίτητα παραδοσιακοί builders.

Όταν ένα product πετυχαίνει τέτοια διασταύρωση κοινού, αποκτά μεγαλύτερο secondary ενδιαφέρον. Δεν μπαίνει απλώς στο ράφι των LEGO fans. Μπορεί να βρεθεί και στο ραντάρ συλλεκτών αθλητικού memorabilia, crossover display collectors ή gift buyers που λειτουργούν με premium taste.
TEXT,
                        [
                            'Minifigure first appearances και icon storytelling ενισχύουν το collector appeal.',
                            'Το crossover ανάμεσα σε football fandom και buildable display είναι πολύ πιο ισχυρό από ένα απλό sports tie-in.',
                            'Τα premium μεγαλύτερα sets μπορούν να διαβαστούν σαν hero display pieces και όχι σαν απλά expansion items.',
                        ]
                    ),
                    $this->section(
                        'Γιατί αυτά τα products θέλουν διαφορετικό marketplace listing',
                        <<<'TEXT'
Αν τέτοιου τύπου sets βγουν σε resale environment, το listing πρέπει να αντιμετωπίζει το αντικείμενο ως display collectible. Ο buyer ενδιαφέρεται για seal condition, box sharpness, shelf presence, completeness, edition identity και αν υπάρχει ιδιαίτερη σύνδεση με συγκεκριμένο icon ή release wave. Δεν φτάνει να πεις “LEGO football set”.

Σε marketplace όρους, αυτό σημαίνει καλύτερο τίτλο, σωστά franchise fields, φωτογραφίες που δείχνουν box quality και όχι μόνο το μπροστινό art, και σαφή θέση για το αν μιλάμε για sealed, open box, displayed ή complete with original packaging. Όσο περισσότερο collector product γίνεται μια κατηγορία, τόσο περισσότερο collector language χρειάζεται και η αγγελία.
TEXT
                    ),
                    $this->section(
                        'Τι κρατάμε για την ευρύτερη αγορά collectibles',
                        <<<'TEXT'
Η μεγάλη εικόνα είναι ότι όλο και περισσότερα brands μαθαίνουν να πουλάνε ιστορία και identity μαζί με το αντικείμενο. Αυτό για τους collectors σημαίνει ότι τα “όρια” ανάμεσα σε figure, premium toy, display model και fandom collectible γίνονται πιο ρευστά.

Για την Cardora, τέτοιες κυκλοφορίες είναι ακριβώς ο λόγος που οι κατηγορίες figures και collectibles πρέπει να έχουν σωστή collector λογική. Όχι γενικό toy wording, αλλά περιγραφή που καταλαβαίνει display value, edition story και crossover fandom appeal.
TEXT
                    ),
                ],
                tags: ['LEGO', 'Figures', 'Display Collectibles', 'Football', 'Collector News'],
                isFeatured: false,
                gradient: 'from-[#8a631f] via-[#264266] to-[#0a1320]',
                label: 'Official Source',
                authorRoleEl: 'Συντακτική ομάδα Cardora',
                authorRoleEn: 'Cardora editorial team',
                source: [
                    'publisher' => 'LEGO',
                    'title' => 'Building History: the LEGO Group Teams Up with Cristiano Ronaldo, Kylian Mbappé, Lionel Messi, and Vini Jr. to Celebrate the Magic of Football',
                    'url' => 'https://www.lego.com/en-us/aboutus/news/2026/april/lego-editions-wants-a-piece',
                    'published_at' => '2026-04-02',
                ],
                sourceNoteEl: 'Το άρθρο βασίζεται στην επίσημη ανακοίνωση της LEGO για τη νέα πλατφόρμα LEGO Editions και τα football collector sets του Απριλίου 2026.',
            ),
            $this->article(
                categoryKey: 'card_market',
                authorKey: 'andreas',
                slug: 'panini-prizm-usa-stars-and-stripes-baseball-giati-metraei-i-proti-fora-kato-apo-to-prizm-brand',
                titleEl: 'Panini Prizm USA Stars & Stripes Baseball: γιατί μετράει η πρώτη φορά κάτω από το Prizm brand',
                titleEn: 'Panini Prizm USA Stars & Stripes Baseball: why the first Prizm-branded release matters',
                excerptEl: 'Η επίσημη ανακοίνωση της Panini δείχνει γιατί η μετάβαση του Stars & Stripes στο Prizm brand είναι κάτι περισσότερο από ένα απλό product refresh.',
                excerptEn: 'Panini’s official announcement shows why moving Stars & Stripes under the Prizm brand is more than a simple product refresh.',
                publishedAt: '2026-04-03 12:25:00',
                introEl: <<<'TEXT'
Όταν η Panini λέει ότι το 2026 USA Stars & Stripes Baseball εμφανίζεται για πρώτη φορά μέσα στο Prizm brand, δεν περιγράφει απλώς ένα νέο κουτί. Περιγράφει repositioning. Και για collectors αυτό μετράει, γιατί το Prizm όνομα μεταφέρει μαζί του συγκεκριμένες προσδοκίες: παράλληλα, χρωματική αναγνωρισιμότητα, chase structure και ισχυρότερη οπτική ταυτότητα.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Το brand umbrella επηρεάζει αμέσως τη συλλεκτική ανάγνωση',
                        <<<'TEXT'
Η Panini αναφέρει ρητά ότι είναι η πρώτη φορά που το Stars & Stripes μπαίνει στο Prizm family. Αυτό από μόνο του αλλάζει τον τρόπο που θα διαβάσουν το προϊόν πολλοί collectors. Το Prizm δεν είναι απλώς design line. Είναι brand shorthand για parallels, chromed look, recognizable chase patterns και πιο ξεκάθαρη retail-to-hobby γλώσσα.

Σε πολλές αγορές, το brand umbrella βοηθά ένα προϊόν να κερδίσει attention πριν ακόμη δούμε αναλυτικά checklist behavior. Για τον collector σημαίνει ότι η κουβέντα δεν ξεκινά από το μηδέν. Ξεκινά από ήδη κατακτημένες συνήθειες του hobby γύρω από το τι θεωρεί premium pull structure και ποια αισθητική είναι worthy of chase.
TEXT
                    ),
                    $this->section(
                        'Το box configuration δείχνει σαφές hobby intent',
                        <<<'TEXT'
Η επίσημη Panini ανακοίνωση δίνει καθαρό hobby breakdown: 12 packs των 12 cards, 18 Prizm parallels, 12 inserts ή insert parallels και κατά μέσο όρο έξι autographs ή memorabilia cards ανά hobby box. Αυτή η πληροφορία είναι κρίσιμη γιατί επιτρέπει στον collector να καταλάβει αν πρόκειται για release που θα στηριχθεί κυρίως σε singles, sealed breaks ή signature-driven chases.

Όταν ένα προϊόν μπαίνει με τόσο βαριά hit structure, το secondary market συνήθως αρχίζει να οργανώνεται γρήγορα γύρω από λίγες κατηγορίες: key prospects, ultra-rare inserts, μεγάλα autos και visual standouts. Η Panini ουσιαστικά μας λέει από την ανακοίνωση κιόλας πού θα στηθεί το πρώτο κύμα ενδιαφέροντος.
TEXT,
                        [
                            '18 parallels ανά hobby box σημαίνουν έντονη παράλληλη ιεραρχία από την αρχή.',
                            'Τα έξι autos ή memorabilia cards δίνουν σοβαρό hit-driven χαρακτήρα στο release.',
                            'Όσο πιο καθαρή είναι η hit structure, τόσο πιο γρήγορα αρχίζουν να ξεχωρίζουν τα πραγματικά chase singles.',
                        ]
                    ),
                    $this->section(
                        'Prospects, stars και legends σημαίνουν ευρύ collector base',
                        <<<'TEXT'
Η ανακοίνωση μιλά για prospects, current stars και legends, ενώ αναφέρει και inserts όπως το Color Blast, μαζί με signatures ονομάτων όπως ο Paul Skenes και multi-signature constructions όπως το “USA Baseball Greats”. Αυτό είναι σημαντικό γιατί αποτρέπει το προϊόν από το να γίνει μονοδιάστατο. Δεν στοχεύει μόνο prospect flippers ή μόνο veteran collectors. Προσπαθεί να κρατήσει πολλές collector πόρτες ανοιχτές ταυτόχρονα.

Τέτοια products συχνά δουλεύουν καλύτερα μακροπρόθεσμα όταν οι sellers ξέρουν να διαχωρίζουν αμέσως τι είναι short-term excitement και τι μπορεί να μείνει ως πραγματικό hobby anchor. Το ότι ένα card τραβά το βλέμμα στην πρώτη εβδομάδα δεν σημαίνει ότι θα αντέξει το ίδιο καλά μετά το σβήσιμο του release cycle.
TEXT
                    ),
                    $this->section(
                        'Πώς διαβάζεται αυτό στην Cardora',
                        <<<'TEXT'
Για τους Cardora sellers η είδηση είναι χρήσιμη γιατί δίνει έγκαιρο context πριν βγουν μαζικά singles. Αν κάποιος σκοπεύει να διαθέσει cards από το release, χρειάζεται πιο καθαρό τίτλο, σωστή αναφορά parallel, ακριβές numbering και καθαρές close-up φωτογραφίες ώστε να ξεχωρίζει μέσα στον θόρυβο του launch.

Για buyers, η σωστή στάση είναι να ξεχωρίζουν από νωρίς ποια pulls αγοράζουν επειδή είναι πραγματικά σημαντικά και ποια επειδή απλώς βρίσκονται μέσα στην πρώτη εβδομάδα ενθουσιασμού. Το σωστό reading της επίσημης ανακοίνωσης σε βοηθά ακριβώς σε αυτό: να βλέπεις τη δομή πριν χαθείς στο hype.
TEXT
                    ),
                ],
                tags: ['Panini', 'Prizm', 'Baseball', 'Card Market', 'Collector News'],
                isFeatured: false,
                gradient: 'from-[#7e5a1b] via-[#22324c] to-[#0a1421]',
                label: 'Official Source',
                authorRoleEl: 'Συντακτική ομάδα Cardora',
                authorRoleEn: 'Cardora editorial team',
                source: [
                    'publisher' => 'Panini',
                    'title' => 'Panini Prizm USA Stars & Stripes Baseball Brings the Heat',
                    'url' => 'https://blog.paniniamerica.net/panini-prizm-usa-stars-stripes-baseball-brings-the-heat/',
                    'published_at' => '2026-04-02',
                ],
                sourceNoteEl: 'Το άρθρο βασίζεται στην επίσημη Panini ανακοίνωση για το 2026 Prizm USA Stars & Stripes Baseball και στη δομή του release που παρουσιάστηκε στις 2 Απριλίου 2026.',
            ),
            $this->article(
                categoryKey: 'comics_books',
                authorKey: 'maria',
                slug: 'daredevil-1-2026-giati-to-neo-run-kai-ta-blind-bag-variants-travane-tous-collectors',
                titleEl: 'Daredevil #1 (2026): γιατί το νέο run και τα blind bag variants τραβάνε collectors',
                titleEn: 'Daredevil #1 (2026): why the new run and blind bag variants matter to collectors',
                excerptEl: 'Η επίσημη Marvel παρουσίαση του νέου Daredevil #1 δείχνει πώς ένα fresh start αποκτά μεγαλύτερο collector βάρος όταν δένεται με blind bag variants και ισχυρό cover program.',
                excerptEn: 'Marvel’s official rollout for Daredevil #1 shows how a fresh start gains collector weight when tied to blind bags and a strong cover program.',
                publishedAt: '2026-04-02 18:45:00',
                introEl: <<<'TEXT'
Το νέο Daredevil #1 της 1ης Απριλίου 2026 δεν παρουσιάζεται από τη Marvel σαν ένα ακόμα relaunch. Παρουσιάζεται σαν all-new and unprecedented era, με καινούργιο status quo, νέο villain και, το πιο σημαντικό συλλεκτικά, με έντονο blind bag και variant framing. Αυτό ακριβώς είναι που κάνει το issue άξιο προσοχής από collectors και όχι μόνο από regular readers.
TEXT,
                sectionsEl: [
                    $this->section(
                        'Το #1 εξακολουθεί να έχει βαρύτητα όταν το launch είναι καθαρό',
                        <<<'TEXT'
Η Marvel δίνει στο issue όλα τα βασικά στοιχεία που θέλει ο collector για να το κοιτάξει σοβαρά: νέα δημιουργική ομάδα, νέο status quo, νέο villain και ξεκάθαρο σημείο εκκίνησης για παλιούς και νέους αναγνώστες. Αυτή η καθαρότητα έχει σημασία γιατί τα #1 issues κουράζουν όταν μοιάζουν ανακυκλωμένα, αλλά δυναμώνουν όταν ο publisher δείχνει ότι όντως ξεκινά νέα φάση.

Στον Daredevil, η ύπαρξη νέου villain όπως ο Omen και το framing γύρω από μια κατάσταση που δεν έχουμε ξαναδεί δημιουργούν καλή collector γλώσσα. Δεν εγγυώνται ιστορική αξία, αλλά δίνουν σαφές narrative reason για να σταθεί το πρώτο issue πιο σοβαρά.
TEXT
                    ),
                    $this->section(
                        'Τα blind bags αλλάζουν το πώς χτίζεται η πρώιμη αγορά',
                        <<<'TEXT'
Η επίσημη Marvel κάλυψη για τα True Believer Blind Bags κάνει το collector angle ακόμη πιο ενδιαφέρον. Όταν ένα issue διατίθεται μέσα από blind bag format που μπορεί να κρύβει regular covers, rare blind bag exclusives και ακόμη και one-of-one style sketch surprises, το early market δεν λειτουργεί πια μόνο σαν “βάζω το #1 στο pull list μου”.

Λειτουργεί και σαν sealed chase mechanic. Αυτό φέρνει διαφορετικό αγοραστικό κοινό: όχι μόνο αναγνώστες του Daredevil, αλλά και variant hunters, sealed experiment buyers και collectors που κυνηγούν short-term scarcity μέσα από distribution format. Και αυτό αλλάζει τον ρυθμό της secondary αγοράς στις πρώτες εβδομάδες.
TEXT,
                        [
                            'Blind bag distribution σημαίνει ότι το ίδιο issue μπορεί να αποκτήσει διαφορετικές collector διαδρομές.',
                            'Ισχυρό cover line-up αυξάνει το ενδιαφέρον ακόμη και σε buyers που δεν κυνηγούν το main cover.',
                            'Τα πρώτα weeks comps πρέπει να διαβάζονται προσεκτικά γιατί συχνά μπερδεύουν sealed premium με πραγματικό long-term demand.',
                        ]
                    ),
                    $this->section(
                        'Το δυνατό cover πρόγραμμα ενισχύει το launch',
                        <<<'TEXT'
Η Marvel αναδεικνύει ένα ιδιαίτερα βαρύ variant πρόγραμμα με ονόματα όπως Mark Bagley, Alex Maleev, Dan Panosian και Bill Sienkiewicz. Αυτό είναι συλλεκτικά σημαντικό, γιατί το launch αποκτά πολλαπλές εισόδους: character collectors, artist collectors, Daredevil completionists και blind bag hunters.

Όσο πιο δυνατό είναι το cover ecosystem γύρω από ένα πρώτο issue, τόσο πιο πολύ χωρίζεται η αγορά σε διαφορετικά υποκοινά. Κάποιοι θα κυνηγήσουν main cover accessibility, κάποιοι artist prestige, κάποιοι sealed blind bag novelty. Αυτό συχνά κάνει το πρώτο κύμα αγοράς πιο έντονο και πιο θορυβώδες από ό,τι σε ένα απλό relaunch.
TEXT
                    ),
                    $this->section(
                        'Πώς το διαχειρίζεσαι σωστά σε marketplace περιβάλλον',
                        <<<'TEXT'
Αν βγει τέτοιο issue σε Cardora listing, χρειάζεται απόλυτη ακρίβεια: main cover ή variant, sealed blind bag ή opened copy, condition, spine visibility, bag and board κατάσταση και φυσικά αν υπάρχει κάποια ιδιαιτερότητα διανομής που να στηρίζει την τιμή. Δεν αρκεί ο τίτλος “Daredevil #1”.

Για buyers, η ώριμη προσέγγιση είναι να ξεχωρίζουν το πραγματικό long-term collector appeal από το launch premium. Τα καλύτερα #1 issues δεν είναι πάντα αυτά που φωνάζουν περισσότερο την πρώτη εβδομάδα. Είναι αυτά που παραμένουν σημαντικά όταν φύγει η πρώτη σκόνη του release.
TEXT
                    ),
                ],
                tags: ['Marvel', 'Daredevil', 'Comics', 'Variants', 'Collector News'],
                isFeatured: false,
                gradient: 'from-[#6f4a19] via-[#25324f] to-[#09121d]',
                label: 'Official Source',
                authorRoleEl: 'Συντακτική ομάδα Cardora',
                authorRoleEn: 'Cardora editorial team',
                source: [
                    'publisher' => 'Marvel',
                    'title' => 'Daredevil (2026) #1 / True Believer Blind Bag rollout',
                    'url' => 'https://www.marvel.com/comics/issue/127597/daredevil_2026_1',
                    'published_at' => '2026-04-01',
                ],
                sourceNoteEl: 'Το άρθρο βασίζεται στην επίσημη issue page της Marvel για το Daredevil (2026) #1 και στη σχετική Marvel παρουσίαση των blind bag variant covers.',
            ),
        ];
    }

    protected function article(
        string $categoryKey,
        string $authorKey,
        string $slug,
        string $titleEl,
        string $titleEn,
        string $excerptEl,
        string $excerptEn,
        string $publishedAt,
        string $introEl,
        array $sectionsEl,
        array $tags,
        bool $isFeatured = false,
        string $gradient = 'from-[#29465d] via-[#182033] to-[#09111d]',
        string $label = 'Editorial',
        string $authorRoleEl = 'Συντακτική ομάδα Cardora',
        string $authorRoleEn = 'Cardora editorial team',
        array $source = [],
        string $sourceNoteEl = '',
    ): array {
        $content = [
            'translations' => [
                'el' => [
                    'intro' => trim($introEl),
                    'sections' => $sectionsEl,
                ],
            ],
        ];

        if ($sourceNoteEl !== '') {
            $content['translations']['el']['source_note'] = trim($sourceNoteEl);
        }

        if (! empty($source)) {
            $content['source'] = $source;
        }

        return [
            'category_key' => $categoryKey,
            'author_key' => $authorKey,
            'slug' => $slug,
            'published_at' => $publishedAt,
            'tags' => $tags,
            'is_featured' => $isFeatured,
            'cover_media' => [
                'gradient' => $gradient,
                'label' => $label,
            ],
            'content' => $content,
            'seo' => [
                'translations' => [
                    'el' => [
                        'title' => $titleEl,
                        'excerpt' => $excerptEl,
                        'read_time' => '8 λεπτά ανάγνωση',
                        'author_role' => $authorRoleEl,
                    ],
                    'en' => [
                        'title' => $titleEn,
                        'excerpt' => $excerptEn,
                        'read_time' => '8 min read',
                        'author_role' => $authorRoleEn,
                    ],
                ],
            ],
        ];
    }

    protected function section(string $title, string $body, array $bullets = []): array
    {
        return [
            'title' => $title,
            'paragraphs' => $this->paragraphs($body)->all(),
            'bullets' => collect($bullets)->map(fn ($bullet) => trim((string) $bullet))->filter()->values()->all(),
        ];
    }

    protected function paragraphs(string $body): Collection
    {
        return collect(preg_split("/\R{2,}/u", trim($body)) ?: [])
            ->map(fn ($paragraph) => trim((string) $paragraph))
            ->filter()
            ->values();
    }
}
