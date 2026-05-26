<?php

namespace Tests\Unit;

use App\Services\MessageModerationService;
use Tests\TestCase;

class MessageModerationServiceTest extends TestCase
{
    public function test_it_keeps_clean_messages_clean(): void
    {
        $result = app(MessageModerationService::class)->moderate('Καλησπέρα, όλα καλά;');

        $this->assertSame('clean', $result['moderation_status']);
        $this->assertFalse($result['requires_admin_review']);
        $this->assertNull($result['masked_body']);
    }

    public function test_it_flags_phone_prompt_attempts(): void
    {
        $result = app(MessageModerationService::class)->moderate('Το τηλεφωνο σου;');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('contact_details', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_obfuscated_address_attempts(): void
    {
        $result = app(MessageModerationService::class)->moderate('δι-εθυ-νση');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('address_attempt', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_fully_split_greek_address_keyword(): void
    {
        $result = app(MessageModerationService::class)->moderate('Δ-ι-ε-υ-θ-η-ν-σ-η');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('address_attempt', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_explicit_addresses(): void
    {
        $result = app(MessageModerationService::class)->moderate('Καλλισθένους 66 Αθηνα');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('address_attempt', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_abusive_language_in_greek(): void
    {
        $result = app(MessageModerationService::class)->moderate('γαμω');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('abusive_language', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_off_platform_prompts(): void
    {
        $result = app(MessageModerationService::class)->moderate('Στείλε μου στο whatsapp');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('off_platform_attempt', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }

    public function test_it_flags_mixed_script_phone_prompt(): void
    {
        $result = app(MessageModerationService::class)->moderate('tηlefωnο σου;');

        $this->assertSame('masked', $result['moderation_status']);
        $this->assertTrue($result['requires_admin_review']);
        $this->assertContains('contact_details', $result['moderation_flags']);
        $this->assertNotNull($result['masked_body']);
    }
}