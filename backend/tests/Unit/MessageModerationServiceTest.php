<?php

namespace Tests\Unit;

use App\Services\MessageModerationService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class MessageModerationServiceTest extends TestCase
{
    public static function marketplaceMessages(): iterable
    {
        foreach (['marketplace', 'regressions'] as $fixture) {
            $cases = require __DIR__.'/../Fixtures/MessageModeration/'.$fixture.'.php';
            foreach ($cases['clean'] as $index => $text) {
                yield $fixture.' clean '.$index => [$text, null];
            }
            foreach ($cases['blocked'] as $index => [$text, $flag]) {
                yield $fixture.' blocked '.$index => [$text, $flag];
            }
        }
        foreach (range(1, 20) as $first) {
            foreach (range(1, 20) as $second) {
                yield "issues $first and $second" => ["Τα τεύχη $first και $second είναι διαθέσιμα;", null];
            }
        }
    }

    #[DataProvider('marketplaceMessages')]
    public function test_marketplace_messages(string $text, ?string $flag): void
    {
        $result = (new MessageModerationService)->moderate($text);
        $this->assertSame($text, $result['original_body']);
        if ($flag === null) {
            $this->assertSame('clean', $result['moderation_status']);
            $this->assertSame([], $result['moderation_flags']);
            $this->assertSame(0, $result['moderation_score']);
            $this->assertFalse($result['requires_admin_review']);
            $this->assertNull($result['masked_body']);
        } else {
            $this->assertSame('masked', $result['moderation_status']);
            $this->assertContains($flag, $result['moderation_flags']);
            $this->assertTrue($result['requires_admin_review']);
            $this->assertNotNull($result['masked_body']);
            $this->assertNotSame($text, $result['masked_body']);
            $this->assertTrue(mb_check_encoding($result['masked_body'], 'UTF-8'));
        }
    }

    public function test_only_the_address_span_is_hidden(): void
    {
        $text = 'Ευχαριστώ! Οδός Πατησίων 12 είναι η διεύθυνση. Τα τεύχη 1 και 6 είναι σωστά.';
        $masked = (new MessageModerationService)->moderate($text)['masked_body'];
        $this->assertStringNotContainsString('Πατησίων', $masked);
        $this->assertStringContainsString('είναι η διεύθυνση. Τα τεύχη 1 και 6 είναι σωστά.', $masked);
        $this->assertStringStartsWith('Ευχαριστώ!', $masked);
    }

    public function test_overlapping_unicode_spans_remain_valid_and_hidden(): void
    {
        $result = (new MessageModerationService)->moderate('Διεύθυνση: οδός Πατησίων 12, Αθήνα 12345. buyer@example.com');
        $this->assertTrue(mb_check_encoding($result['masked_body'], 'UTF-8'));
        foreach (['Πατησίων', '12345', 'buyer@example.com'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $result['masked_body']);
        }
    }

    public function test_empty_input_and_repeated_calls_do_not_retain_flags(): void
    {
        $service = new MessageModerationService;
        $service->moderate('buyer@example.com');
        foreach ([null, '', '   ', 'Τα τεύχη 1 και 5'] as $text) {
            $result = $service->moderate($text);
            $this->assertSame('clean', $result['moderation_status']);
            $this->assertSame([], $result['moderation_flags']);
            $this->assertNull($result['masked_body']);
        }
    }

    public function test_it_keeps_clean_messages_clean(): void
    {
        $result = (new MessageModerationService)->moderate('Καλησπέρα, όλα καλά;');

        $this->assertSame('clean', $result['moderation_status']);
        $this->assertFalse($result['requires_admin_review']);
        $this->assertNull($result['masked_body']);
    }

    public function test_it_flags_phone_prompt_attempts(): void
    {
        $result = (new MessageModerationService)->moderate('Το τηλεφωνο σου;');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('contact_details', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_obfuscated_address_attempts(): void
    {
        $result = (new MessageModerationService)->moderate('δι-εθυ-νση');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('address_attempt', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_fully_split_greek_address_keyword(): void
    {
        $result = (new MessageModerationService)->moderate('Δ-ι-ε-υ-θ-η-ν-σ-η');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('address_attempt', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_explicit_addresses(): void
    {
        $result = (new MessageModerationService)->moderate('Καλλισθένους 66 Αθηνα');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('address_attempt', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_abusive_language_in_greek(): void
    {
        $result = (new MessageModerationService)->moderate('γαμω');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('abusive_language', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_off_platform_prompts(): void
    {
        $result = (new MessageModerationService)->moderate('Στείλε μου στο whatsapp');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('off_platform_attempt', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_mixed_script_phone_prompt(): void
    {
        $result = (new MessageModerationService)->moderate('tηlefωnο σου;');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('contact_details', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }
}
