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

                if (($rule['name'] ?? null) === 'phone' && $this->isLabelledItemReference($originalBody, $offset, $matchedText)) {
                    continue;
                }

                if (($rule['name'] ?? null) === 'phone' && $this->isCalendarDate($matchedText)) {
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

        // Address patterns operate on the original text so a comic issue number
        // between ordinary words cannot become an invented street address.
        foreach ($this->addressPatterns() as $pattern) {
            if (! preg_match_all($pattern, $originalBody, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches[0] as [$matchedText, $offset]) {
                $segments[] = ['start' => $offset, 'length' => strlen($matchedText)];
                $flags[] = 'address_attempt';
                $score += 6;
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
                'regex' => '/(?:https?:\/\/[^\s<>]+|www\.[^\s<>]+|(?<![\p{L}\p{N}])(?:instagram|insta|facebook|messenger|telegram|t\.me|whatsapp|viber|discord|signal|skype|snapchat|wechat|tiktok|gmail|hotmail|outlook|yahoo|protonmail)(?![\p{L}\p{N}]))/iu',
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

        ];
    }

    protected function isLabelledItemReference(string $body, int $offset, string $matchedText): bool
    {
        // An explicit item identifier is not a phone number. This exemption applies
        // only to this number span; email, address and other checks still run.
        if (! preg_match('/^\d[\d-]*\d$/D', $matchedText) || $this->digitCount($matchedText) > 14) {
            return false;
        }
        $prefix = substr($body, 0, $offset);

        return (bool) preg_match('/(?:\b(?:ISBN(?:-1[03])?|EAN|SKU|PSA|BGS|CGC|tracking)|αριθμός\s+αποστολής|αριθμος\s+αποστολης|κωδικός\s+παραγγελίας|κωδικος\s+παραγγελιας)\s*[:#\-]?\s*$/iu', $prefix);
    }

    protected function isCalendarDate(string $value): bool
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})[-.](\d{2})[-.](\d{2})$/', $value, $parts)) {
            return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        }
        if (preg_match('/^(\d{2})[-.](\d{2})[-.](\d{4})$/', $value, $parts)) {
            return checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3]);
        }

        return false;
    }

    protected function addressPatterns(): array
    {
        $word = "[\\p{L}][\\p{L}.'’\\-]*";
        $street = '(?:οδός|οδος|οδό|οδο|οδ\.|λεωφόρος|λεωφορος|λεωφόρου|λεωφορου|λεωφ\.|odos|od\.|leoforos|leoforou|leof\.)';
        $city = '(?:Αθήνα|Αθήνας|Αθηνα|Αθηνας|Θεσσαλονίκη|Θεσσαλονικη|Πάτρα|Πατρα|Πειραιάς|Πειραιας|Λάρισα|Λαρισα|Βόλος|Βολος|Ηράκλειο|Ηρακλειο|Χανιά|Χανια|athina|athens|thessaloniki|patra|patras|piraeus|peiraias|larisa|volos|heraklion|irakleio|chania)';

        return [
            '/(?<!\p{L})'.$street.'\s+(?:'.$word.'\s+){1,5}\d{1,4}(?:\s?[A-ZΑ-Ω](?!\p{L}))?(?!\d)/iu',
            '/(?<![\p{L}\d])\d{1,4}\s+(?:'.$word.'\s+){1,5}(?:street|st\.|road|rd\.|avenue|ave\.|lane|drive|boulevard)\b/iu',
            '/(?<!\p{L})(?:'.$word.'\s+){1,4}\d{1,4}(?:\s*,\s*|\s+)'.$city.'(?!\p{L})/iu',
            '/(?<!\p{L})(?:διεύθυνσ[ηή]|διευθυνσ[ηή]|διευθυνση|diefthinsi|dieythinsi|address)\s*(?:μου|moy|mou)?\s*(?:είναι|ειναι|einai|is|:)\s*(?:'.$word.'\s+){1,5}\d{1,4}(?!\d)/iu',
            '/(?<!\p{L})(?:Τ\.?\s*Κ\.?|ταχυδρομικός\s+κώδικας|ταχυδρομικος\s+κωδικας|postcode|postal\s*code|zip\s*code)\s*[:=]?\s*\d{3}\s?\d{2}(?!\d)/iu',
            '/(?<!\p{L})(?:κουδούνι|κουδουνι|koudouni|koydoyni|doorbell)\s*[:=]\s*'.$word.'(?:\s+'.$word.')?/iu',
            '/(?<!\p{L})'.$city.'\s*,?\s*\d{3}\s?\d{2}(?!\d)/iu',
            '/(?<!\p{L})(?:στείλε|στειλε|δώσε|δωσε|steile|stile|dose|send|give)\s+(?:μου|mou|moy|me)\s+(?:(?:τη|την|ti|tin|your)\s+)?(?:διεύθυνση|διευθυνση|diefthinsi|dieythinsi|address)(?!\p{L})/iu',
        ];
    }

    protected function semanticRules(): array
    {
        return [
            [
                'flag' => 'contact_details',
                'score' => 6,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/\b(?:(?:tilef(?:o|w)no|thlef(?:o|w)no|tilephono|tilefono|kinito|kinhto|phone|mobile|email)\s+(?:soy|sou|moy|mou)|(?:my|your)\s+(?:phone|mobile|email)|(?:steile|stile|dose)\s*(?:moy|mou)\s*(?:to\s*)?(?:tilefono|kinito|email)|(?:send|give)\s+me\s+(?:your\s+)?(?:phone|mobile|email)|(?:call|text)\s*me|stoixeia\s*epikoinonias)\b|^(?:to\s*)?(?:tilef(?:o|w)no|thlef(?:o|w)no|tilephono|kinito|kinhto|phone|mobile|email|sms)$/u',
            ],
            [
                'flag' => 'off_platform_attempt',
                'score' => 6,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/\b(?:ektos\s*(?:platformas|cardora|kardora)|call\s*me|text\s*me|pay\s*me|bank\s*transfer|wire\s*transfer|paypal|revolut|iban|crypto|bitcoin|usdt|ethereum|whatsapp|whtsapp|telegram|viber|discord|instagram|insta|facebook|messenger|gmail|hotmail|outlook|yahoo|protonmail)\b/u',
            ],
            [
                'flag' => 'address_attempt',
                'score' => 6,
                'mask_entire' => true,
                'target' => 'canonical',
                // Standalone obfuscated requests, not ordinary mentions such as
                // "the address is already in the order".
                'regex' => '/^(?:dieythinsi|diethynsi|diefthinsi|diefthynsi|dieythynsi|address)$|\b(?:dieythinsi|dieythynsi|diefthinsi|diefthynsi)\s+(?:soy|sou|moy|mou)\b/u',
            ],
            [
                'flag' => 'abusive_language',
                'score' => 4,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/\b(?:g(?:h)?am(?:w|o|ise|isou|isoy|iesai|imene|imenos|imeni)|malak(?:a|as|es|ia|ies|ismenos|ismene)|p(?:ou|oy)st(?:i|is|ides)|p(?:ou|oy)tan(?:a|es)|p(?:ou|oy)ts(?:a|es)|m(?:ou|oy)n(?:i|ia)|kariol(?:a|is|es)|papar(?:a|as|es|ies)|ar[xh]id(?:i|ia)|skata|skase|bitch(?:es)?|asshole(?:s)?|motherfucker(?:s)?|fuck(?:ing|er|ed)?|shit|cunt|slut|whore|bastard|dickhead|retard|idiot)\b/u',
            ],
            [
                'flag' => 'threatening_language',
                'score' => 5,
                'mask_entire' => true,
                'target' => 'canonical',
                'regex' => '/\b(?:tha\s*se\s*(?:vrw|vro|vrow|sfaxo|sfakso|sfaksw|skotoso|skotwsw)|(?:kill|hurt)\s*you)\b/u',
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
        // strtr is non-recursive: strip accents before transliterating Greek.
        $value = strtr($value, ['ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ϊ' => 'ι', 'ΐ' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ϋ' => 'υ', 'ΰ' => 'υ', 'ώ' => 'ω']);
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
        ]);

        // Keep word boundaries. Joining an entire sentence made substrings in
        // product names (origami, mountain, instant) look like prohibited words.
        // Punctuation within an obfuscated word is still removed (e.g. f.u.c.k).
        $canonical = preg_replace('/[^a-z0-9\s]+/u', '', $value);
        $canonical = is_string($canonical) ? preg_replace('/\s+/u', ' ', trim($canonical)) : '';

        return is_string($canonical) ? $canonical : '';
    }

    protected function digitCount(string $value): int
    {
        return preg_match_all('/\d/u', $value);
    }

    protected function maskSegments(string $value, array $segments): string
    {
        usort($segments, fn (array $left, array $right) => $left['start'] <=> $right['start']);

        $merged = [];
        foreach ($segments as $segment) {
            $last = count($merged) - 1;
            if ($last >= 0 && $segment['start'] <= $merged[$last]['start'] + $merged[$last]['length']) {
                $merged[$last]['length'] = max($merged[$last]['start'] + $merged[$last]['length'], $segment['start'] + $segment['length']) - $merged[$last]['start'];
            } else {
                $merged[] = $segment;
            }
        }
        $segments = $merged;

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
