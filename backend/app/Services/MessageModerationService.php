<?php

namespace App\Services;

use Illuminate\Support\Str;

class MessageModerationService
{
    /**
     * @return array{
     *     original_body:string,
     *     masked_body:?string,
     *     moderation_status:string,
     *     moderation_flags:array<int,string>,
     *     moderation_score:int,
     *     requires_admin_review:bool
     * }
     */
    public function moderate(?string $body): array
    {
        $originalBody = trim((string) $body);

        if ($originalBody === '') {
            return [
                'original_body' => '',
                'masked_body' => null,
                'moderation_status' => 'clean',
                'moderation_flags' => [],
                'moderation_score' => 0,
                'requires_admin_review' => false,
            ];
        }

        $flags = [];
        $score = 0;
        $segments = [];
        $maskEntireMessage = false;
        $normalized = $this->normalize($originalBody);
        $compact = $this->compactNormalize($originalBody);
        $canonical = $this->canonicalNormalize($originalBody);

        foreach ($this->exactRules() as $rule) {
            if (! preg_match_all($rule['regex'], $originalBody, $ruleMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($ruleMatches[0] as $match) {
                [$matchedText, $offset] = $match;

                if (! is_string($matchedText) || $matchedText === '') {
                    continue;
                }

                if (($rule['name'] ?? null) === 'phone' && $this->digitCount($matchedText) < 8) {
                    continue;
                }

                $segments[] = [
                    'start' => (int) $offset,
                    'length' => strlen($matchedText),
                ];
                $flags[] = $rule['flag'];
                $score += (int) $rule['score'];
            }
        }

        foreach ($this->semanticRules() as $rule) {
            $target = $rule['target'] ?? 'normalized';
            $subject = match ($target) {
                'compact' => $compact,
                'canonical' => $canonical,
                default => $normalized,
            };

            if (! preg_match($rule['regex'], $subject)) {
                continue;
            }

            $flags[] = $rule['flag'];
            $score += (int) $rule['score'];
            $maskEntireMessage = $maskEntireMessage || (bool) ($rule['mask_entire'] ?? true);
        }

        $flags = array_values(array_unique($flags));
        $requiresAdminReview = $flags !== [];

        $maskedBody = null;

        if ($maskEntireMessage) {
            $maskedBody = $this->maskWholeMessage($originalBody);
        } elseif ($segments !== []) {
            $maskedBody = $this->maskSegments($originalBody, $segments);
        }

        return [
            'original_body' => $originalBody,
            'masked_body' => $maskedBody !== $originalBody ? $maskedBody : null,
            'moderation_status' => $flags === [] ? 'clean' : 'masked',
            'moderation_flags' => $flags,
            'moderation_score' => $score,
            'requires_admin_review' => $requiresAdminReview,
        ];
    }

    protected function exactRules(): array
    {
        return [
            [
                'name' => 'email',
                'flag' => 'contact_details',
                'score' => 5,
                'regex' => '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            ],
            [
                'name' => 'url',
                'flag' => 'contact_details',
                'score' => 4,
                'regex' => '/(?:https?:\/\/|www\.|instagram|insta|facebook|messenger|telegram|t\.me|whatsapp|viber|discord|signal|skype|snapchat|wechat|line|tiktok|gmail|hotmail|outlook|yahoo|protonmail)/iu',
            ],
            [
                'name' => 'phone',
                'flag' => 'contact_details',
                'score' => 6,
                'regex' => '/(?:\+?\d[\d\s().\-]{6,}\d)/u',
            ],
            [
                'name' => 'iban',
                'flag' => 'off_platform_attempt',
                'score' => 6,
                'regex' => '/\b[A-Z]{2}\d{2}[A-Z0-9]{10,30}\b/u',
            ],
            [
                'name' => 'postal_code',
                'flag' => 'address_attempt',
                'score' => 3,
                'regex' => '/\b\d{5}\b/u',
            ],
        ];
    }

    protected function semanticRules(): array
    {
        return [
            [
                'flag' => 'address_attempt',
                'score' => 5,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/(?:die(?:f|ph|y|i|u)?th(?:i|y)?ns(?:i|y)|address|street|road|avenue|postcode|postalcode|taxydromikos|orofos|koydoyni|doorbell|floor)/u',
            ],
            [
                'flag' => 'address_attempt',
                'score' => 6,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/(?:[a-z]{5,}\d{1,4}[a-z]{3,})/u',
            ],
            [
                'flag' => 'address_attempt',
                'score' => 5,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/(?:athina|athens|thessaloniki|patra|patras|peiraias|piraeus|larisa|volos|irakleio|heraklion|ioannina|chalkida|chania|rethymno|kavala|lamia|serres|tripoli|kalamata)\d{0,5}/u',
            ],
            [
                'flag' => 'contact_details',
                'score' => 6,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/(?:tilef(?:o|w)no|thlef(?:o|w)no|tilephono|kinito|kinhto|mobile|phone|telephon|arithm|number|callme|textme|sms|myemail|stoixeiaepikoinonias|dosemoytotilefono|dosemoykinito|steilemoytotilefono|steilemoykinito|poiototelefono|totilefonosoy)/u',
            ],
            [
                'flag' => 'off_platform_attempt',
                'score' => 6,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/(?:ektosplatformas|ektoscardora|pareme|steilemoy|stilemoy|grapsemoy|callme|textme|payme|banktransfer|wiretransfer|paypal|revolut|iban|crypto|bitcoin|usdt|ethereum|whatsapp|whtsapp|telegram|viber|discord|instagram|insta|facebook|messenger|gmail|hotmail|outlook|yahoo|protonmail)/u',
            ],
            [
                'flag' => 'abusive_language',
                'score' => 4,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/(?:ghamw|ghamo|ghami|ghamis|ghamise|ghamisoy|ghamiet|ghamimen|gamw|gamo|gami|gamis|gamise|gamisou|gamiet|gamimen|malak|malakia|malakism|poyst|poust|poytan|poutan|poyts|pouts|poytsa|poutsa|moyn|moun|kariol|papar|arxid|arhid|skata|skase|bitch|asshole|motherfucker|fucking|fuck|shit|cunt|slut|whore|bastard|dickhead|retard|idiot)/u',
            ],
            [
                'flag' => 'threatening_language',
                'score' => 5,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/(?:thasevrw|thasxoliasw|thasfaksw|thaskotwsw|thasevrow|thaerthw|killyou|findyou|hurtyou|watchout)/u',
            ],
        ];
    }

    protected function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    protected function compactNormalize(string $value): string
    {
        $normalized = $this->normalize($value);

        $compact = preg_replace('/[^a-z0-9]+/u', '', $normalized);

        return is_string($compact) ? $compact : $normalized;
    }

    protected function canonicalNormalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, [
            'ά' => 'α',
            'έ' => 'ε',
            'ή' => 'η',
            'ί' => 'ι',
            'ϊ' => 'ι',
            'ΐ' => 'ι',
            'ό' => 'ο',
            'ύ' => 'υ',
            'ϋ' => 'υ',
            'ΰ' => 'υ',
            'ώ' => 'ω',
            '@' => 'a',
            '4' => 'a',
            '3' => 'e',
            '1' => 'i',
            '!' => 'i',
            '|' => 'i',
            '0' => 'o',
            '$' => 's',
            '5' => 's',
            '7' => 't',
            '8' => 'th',
            '9' => 'g',
            'α' => 'a',
            'β' => 'v',
            'γ' => 'g',
            'δ' => 'd',
            'ε' => 'e',
            'ζ' => 'z',
            'η' => 'i',
            'θ' => 'th',
            'ι' => 'i',
            'κ' => 'k',
            'λ' => 'l',
            'μ' => 'm',
            'ν' => 'n',
            'ξ' => 'x',
            'ο' => 'o',
            'π' => 'p',
            'ρ' => 'r',
            'σ' => 's',
            'ς' => 's',
            'τ' => 't',
            'υ' => 'y',
            'φ' => 'f',
            'χ' => 'x',
            'ψ' => 'ps',
            'ω' => 'o',
            'q' => 'k',
            'c' => 'k',
        ]);

        $canonical = preg_replace('/[^a-z0-9]+/u', '', $value);

        return is_string($canonical) ? $canonical : '';
    }

    protected function digitCount(string $value): int
    {
        return preg_match_all('/\d/u', $value);
    }

    protected function maskSegments(string $value, array $segments): string
    {
        usort($segments, fn (array $left, array $right) => $left['start'] <=> $right['start']);

        $masked = $value;
        $offsetDelta = 0;

        foreach ($segments as $segment) {
            $start = $segment['start'] + $offsetDelta;
            $length = $segment['length'];
            $slice = substr($masked, $start, $length);

            if ($slice === false || $slice === '') {
                continue;
            }

            $replacement = preg_replace('/[\p{L}\p{N}]/u', '*', $slice);

            if (! is_string($replacement)) {
                continue;
            }

            $masked = substr_replace($masked, $replacement, $start, $length);
            $offsetDelta += strlen($replacement) - $length;
        }

        return Str::squish($masked);
    }

    protected function maskWholeMessage(string $value): string
    {
        $masked = preg_replace('/[\p{L}\p{N}]/u', '*', $value);

        return is_string($masked) ? Str::squish($masked) : $value;
    }
}
