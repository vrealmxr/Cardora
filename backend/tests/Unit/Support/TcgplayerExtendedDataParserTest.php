<?php

namespace Tests\Unit\Support;

use App\Support\TcgplayerExtendedDataParser;
use PHPUnit\Framework\TestCase;

class TcgplayerExtendedDataParserTest extends TestCase
{
    /**
     * Real extendedData sample, Dragon Ball Super Card Game
     * (cardora-sets-data, "Galactic Battle.csv", SD1-01). The regression:
     * `name` (TCGplayer's compact key) is 'CardType' -- no space -- while
     * only `displayName` is 'Card Type' -- with a space. The original
     * implementation matched on `name`, so every single Dragon Ball card's
     * card_type imported as NULL (confirmed: 12,019/12,019 production
     * rows). `Number`/`Rarity` happened to still work because those two
     * fields' compact and display keys are identical, which is exactly
     * why the bug went unnoticed until Dragon Ball needed `Card Type`.
     */
    private const REAL_SAMPLE = <<<'EXT'
[{'name': 'Rarity', 'displayName': 'Rarity', 'value': 'Starter Rare'}, {'name': 'Number', 'displayName': 'Number', 'value': 'SD1-01'}, {'name': 'Description', 'displayName': 'Description', 'value': 'flavor text with an apostrophe: Vegeta''s pride'}, {'name': 'CardType', 'displayName': 'Card Type', 'value': 'Leader'}, {'name': 'Color', 'displayName': 'Color', 'value': 'Blue'}, {'name': 'SpecialTrait', 'displayName': 'Special Trait', 'value': 'Saiyan'}, {'name': 'Power', 'displayName': 'Power', 'value': '10000/150000'}]
EXT;

    public function test_it_extracts_card_type_despite_the_compact_key_having_no_space(): void
    {
        $this->assertSame('Leader', TcgplayerExtendedDataParser::field(self::REAL_SAMPLE, 'Card Type'));
    }

    public function test_it_still_extracts_fields_whose_compact_and_display_keys_match(): void
    {
        $this->assertSame('SD1-01', TcgplayerExtendedDataParser::field(self::REAL_SAMPLE, 'Number'));
        $this->assertSame('Starter Rare', TcgplayerExtendedDataParser::field(self::REAL_SAMPLE, 'Rarity'));
    }

    public function test_it_extracts_a_field_that_appears_after_the_target_field_in_the_list(): void
    {
        $this->assertSame('Saiyan', TcgplayerExtendedDataParser::field(self::REAL_SAMPLE, 'Special Trait'));
    }

    public function test_it_returns_null_for_a_field_not_present(): void
    {
        $this->assertNull(TcgplayerExtendedDataParser::field(self::REAL_SAMPLE, 'Combo Energy'));
    }

    public function test_it_returns_null_for_empty_extended_data(): void
    {
        $this->assertNull(TcgplayerExtendedDataParser::field('', 'Card Type'));
    }

    public function test_it_does_not_bleed_across_object_boundaries(): void
    {
        // 'displayName' and 'value' are matched WITHIN one {...} object only --
        // a field with no 'value' key must not accidentally pick up the next
        // object's value.
        $noValue = "[{'name': 'Notes', 'displayName': 'Notes'}, {'name': 'Number', 'displayName': 'Number', 'value': 'BT1-001'}]";
        $this->assertNull(TcgplayerExtendedDataParser::field($noValue, 'Notes'));
        $this->assertSame('BT1-001', TcgplayerExtendedDataParser::field($noValue, 'Number'));
    }
}
