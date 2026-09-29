<?php

namespace App\Console\Commands;

use App\Models\PageSeoSetting;
use Illuminate\Console\Command;

/**
 * One-off content fill for the 15-page SEO copy batch delivered 2026-09-29
 * (meta title/description/H1/H2 for home, the 4 category pages, about,
 * draws, and blog -- el+en except blog which was only given in en).
 * Updates existing pre-seeded rows (page_key+locale unique), never
 * creates new ones -- matches PageSeoSettingResource's own constraint.
 */
class SeedSeoContentBatch2026 extends Command
{
    protected $signature = 'cardora:seed-seo-batch-2026 {--dry-run}';

    protected $description = 'Fill in the 2026-09-29 SEO content batch (title/description/h1/h2) for 15 pages';

    private const ROWS = [
        ['home', 'el', 'Cardora | Marketplace για Συλλεκτικά Αντικείμενα', 'Ανακάλυψε, αγόρασε, πούλησε και αντάλλαξε συλλεκτικά αντικείμενα στο Cardora. Το marketplace για κάρτες, φιγούρες, κόμικς και συλλέκτες.', 'Cardora: Το Marketplace για Συλλέκτες', 'Αγόρασε, Πούλησε και Αντάλλαξε Συλλεκτικά'],
        ['category.cards', 'el', 'Συλλεκτικές Κάρτες Pokémon, TCG & Αθλητικές | Cardora', 'Βρες συλλεκτικές κάρτες Pokémon, Yu-Gi-Oh!, Magic: The Gathering, One Piece και αθλητικές κάρτες. Αγόρασε, πούλησε ή αντάλλαξε κάρτες στο Cardora.', 'Συλλεκτικές Κάρτες για Κάθε Συλλέκτη', 'Pokémon, Yu-Gi-Oh!, Magic και Αθλητικές Κάρτες'],
        ['category.figures', 'el', 'Συλλεκτικές Φιγούρες & Funko Pop | Cardora', 'Ανακάλυψε συλλεκτικές φιγούρες, Funko Pop, anime, action figures και gaming figures. Βρες μοναδικά κομμάτια για τη συλλογή σου στο Cardora.', 'Συλλεκτικές Φιγούρες στο Cardora', 'Funko Pop, Anime, Gaming και Action Figures'],
        ['category.comics', 'el', 'Κόμικς, Manga & Graphic Novels | Cardora', 'Ανακάλυψε συλλεκτικά κόμικς, manga, graphic novels και ειδικές εκδόσεις. Βρες παλιά και νέα τεύχη και βιβλία από άλλους συλλέκτες στο Cardora.', 'Συλλεκτικά Κόμικς, Manga και Graphic Novels', 'Ανακάλυψε Εκδόσεις Marvel, DC και Περισσότερα'],
        ['category.misc', 'el', 'Λοιπά Συλλεκτικά & Memorabilia | Cardora', 'Εξερεύνησε επιτραπέζια παιχνίδια, memorabilia, συλλεκτικό merchandise, υπογεγραμμένα αντικείμενα, props, replicas και αξεσουάρ συλλογής στο Cardora.', 'Περισσότερα Συλλεκτικά Αντικείμενα', 'Επιτραπέζια, Memorabilia, Merchandise και Αξεσουάρ'],
        ['about', 'el', 'Πώς Λειτουργεί το Cardora | Marketplace Συλλεκτικών', 'Μάθε πώς λειτουργεί το Cardora για αγοραπωλησίες συλλεκτικών. Δες πληροφορίες για τη διαδικασία, τις πληρωμές και την εμπειρία αγοραστών και πωλητών.', 'Πώς Λειτουργεί το Cardora', 'Αγορές και Πωλήσεις Συλλεκτικών με Ενημέρωση σε Κάθε Βήμα'],
        ['draws', 'el', 'Ανταλλαγή Συλλεκτικών Καρτών | Cardora', 'Βρες συλλέκτες για ανταλλαγή Pokémon και άλλων TCG καρτών. Ανακάλυψε ευκαιρίες για να συμπληρώσεις τη συλλογή σου μέσα από το Cardora.', 'Αντάλλαξε Συλλεκτικές Κάρτες στο Cardora', 'Βρες Κάρτες Pokémon και TCG για Ανταλλαγή'],

        ['home', 'en', 'Cardora | Collectibles Marketplace to Buy, Sell & Trade', 'Discover collectibles and connect with collectors. Buy, sell and trade trading cards, figures, comics, memorabilia and more on Cardora.', 'Cardora: A Marketplace for Collectors', 'Buy, Sell and Trade Collectibles Online'],
        ['category.cards', 'en', 'Trading Cards Marketplace | Pokémon, TCG & Sports | Cardora', 'Explore Pokémon, Yu-Gi-Oh!, Magic: The Gathering, One Piece and sports trading cards. Find cards for your collection or list your own on Cardora.', 'Trading Cards for Every Collector', 'Explore Pokémon, Yu-Gi-Oh!, Magic and Sports Cards'],
        ['category.figures', 'en', 'Collectible Figures & Funko Pop | Cardora', 'Browse collectible figures, Funko Pop, anime figures, action figures and gaming collectibles. Discover new pieces for your collection on Cardora.', 'Explore Collectible Figures on Cardora', 'Funko Pop, Anime, Gaming and Action Figures'],
        ['category.comics', 'en', 'Collectible Comics, Manga & Graphic Novels | Cardora', 'Discover collectible comics, manga, graphic novels and special editions. Explore vintage and modern titles from fellow collectors on Cardora.', 'Collectible Comics, Manga and Graphic Novels', 'Explore Marvel, DC and More'],
        ['category.misc', 'en', 'Collectibles, Memorabilia & Accessories | Cardora', 'Explore board games, memorabilia, collectible merchandise, signed items, props, replicas and trading card accessories on Cardora.', 'Discover More Collectibles on Cardora', 'Board Games, Memorabilia, Merchandise and Accessories'],
        ['about', 'en', 'How Cardora Works | Collectibles Marketplace', 'Learn how Cardora works for buying and selling collectibles. Find information about the process, payments and safeguards for buyers and sellers.', 'How Does Cardora Work?', 'Buying and Selling Collectibles on Cardora'],
        ['draws', 'en', 'Trade Collectible Cards Online | Cardora', 'Connect with collectors to trade Pokémon and other TCG cards. Discover card swaps that can help you complete your collection on Cardora.', 'Trade Collectible Cards on Cardora', 'Find Pokémon and TCG Cards to Swap'],
        ['blog', 'en', 'Collecting Guides, Trading Card Tips & News | Cardora', 'Read collecting guides, trading card tips and collectibles market updates. Learn about card values, grading, pricing and safe shipping with Cardora.', 'The Cardora Collectibles Blog', 'Guides, Market News and Tips for Collectors'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $missing = 0;

        foreach (self::ROWS as [$pageKey, $locale, $title, $description, $h1, $h2]) {
            $row = PageSeoSetting::query()->where('page_key', $pageKey)->where('locale', $locale)->first();

            if (! $row) {
                $this->error("Missing pre-seeded row for {$pageKey}/{$locale} -- skipping (resource forbids creating new ones).");
                $missing++;

                continue;
            }

            $this->line("{$pageKey}/{$locale}: \"{$title}\"");

            if (! $dryRun) {
                $row->update([
                    'meta_title' => $title,
                    'meta_description' => $description,
                    'h1' => $h1,
                    'h2' => $h2,
                ]);
            }
            $updated++;
        }

        $this->info(($dryRun ? '[dry-run] Would update' : 'Updated')." {$updated} rows, {$missing} missing.");

        return self::SUCCESS;
    }
}
