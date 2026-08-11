<?php

namespace App\Services;

use App\Models\User;

class UserNotificationPreferenceService
{
    public function defaults(): array
    {
        return [
            'messages' => [
                'in_app' => true,
                'email' => true,
                'push' => true,
            ],
            'orders' => [
                'in_app' => true,
                'email' => true,
                'push' => true,
            ],
            'follows' => [
                'in_app' => true,
                'email' => true,
                'push' => true,
            ],
            'support' => [
                'in_app' => true,
                'email' => true,
                'push' => true,
            ],
            'security' => [
                'in_app' => true,
                'email' => true,
                'push' => true,
                'locked' => true,
            ],
        ];
    }

    public function normalize(?array $preferences): array
    {
        $preferences = is_array($preferences) ? $preferences : [];
        $normalized = [];

        foreach ($this->defaults() as $category => $defaultSection) {
            $section = is_array($preferences[$category] ?? null) ? $preferences[$category] : [];

            $normalized[$category] = [
                'in_app' => array_key_exists('in_app', $section)
                    ? (bool) $section['in_app']
                    : (bool) ($defaultSection['in_app'] ?? true),
                'email' => array_key_exists('email', $section)
                    ? (bool) $section['email']
                    : (bool) ($defaultSection['email'] ?? true),
                'push' => array_key_exists('push', $section)
                    ? (bool) $section['push']
                    : (bool) ($defaultSection['push'] ?? true),
            ];

            if (! empty($defaultSection['locked'])) {
                $normalized[$category]['locked'] = true;
                $normalized[$category]['in_app'] = true;
                $normalized[$category]['email'] = true;
                $normalized[$category]['push'] = true;
            }
        }

        return $normalized;
    }

    public function forUser(User|int|null $user): array
    {
        if ($user instanceof User) {
            return $this->normalize($user->notification_preferences);
        }

        if (is_int($user)) {
            $raw = User::query()->whereKey($user)->value('notification_preferences');

            return $this->normalize(is_array($raw) ? $raw : null);
        }

        return $this->normalize(null);
    }

    public function allowsInApp(User|int|null $user, ?string $category): bool
    {
        if (! $category) {
            return true;
        }

        $preferences = $this->forUser($user);

        return (bool) data_get($preferences, sprintf('%s.in_app', $category), true);
    }

    public function allowsEmail(User|int|null $user, ?string $category): bool
    {
        if (! $category) {
            return true;
        }

        $preferences = $this->forUser($user);

        return (bool) data_get($preferences, sprintf('%s.email', $category), true);
    }

    public function allowsPush(User|int|null $user, ?string $category): bool
    {
        if (! $category) {
            return true;
        }

        $preferences = $this->forUser($user);

        return (bool) data_get($preferences, sprintf('%s.push', $category), true);
    }
}
