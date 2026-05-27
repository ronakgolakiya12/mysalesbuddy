<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedMeetingUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $parts = parse_url($value);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use http or https.');

            return;
        }

        $host = strtolower((string) $parts['host']);

        if ($this->isPrivateOrLocalHost($host)) {
            $fail('The :attribute must not target an internal address.');

            return;
        }

        /** @var array<int, string> $allowed */
        $allowed = (array) config('security.allowed_meeting_hosts', []);
        if ($allowed === []) {
            $fail('No allowed meeting hosts are configured.');

            return;
        }

        foreach ($allowed as $allowedHost) {
            $allowedHost = strtolower((string) $allowedHost);
            if ($allowedHost === '') {
                continue;
            }

            if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
                return;
            }
        }

        $fail('The :attribute host is not allowed.');
    }

    private function isPrivateOrLocalHost(string $host): bool
    {
        if ($host === 'localhost') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // IPv4 private and loopback ranges, plus link-local and cloud metadata.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        // IPv6 loopback and unique-local fc00::/7.
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $lower = strtolower($host);
            if ($lower === '::1' || str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd') || str_starts_with($lower, 'fe80')) {
                return true;
            }
        }

        return false;
    }
}
