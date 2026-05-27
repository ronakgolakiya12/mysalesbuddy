<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\AllowedMeetingUrl;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AllowedMeetingUrlTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedUrls(): array
    {
        return [
            'localhost' => ['http://localhost/admin'],
            'loopback ipv4' => ['http://127.0.0.1/'],
            'loopback ipv6' => ['http://[::1]/'],
            'private 10.x' => ['http://10.0.0.5/'],
            'private 192.168.x' => ['http://192.168.1.1/'],
            'aws metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'gcp metadata' => ['http://metadata.google.internal/'],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme' => ['ftp://example.com/'],
            'disallowed host' => ['https://evil.example.com/path'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_blocked_urls_fail_validation(string $url): void
    {
        $validator = Validator::make(
            ['url' => $url],
            ['url' => [new AllowedMeetingUrl()]]
        );

        $this->assertTrue($validator->fails(), "URL should be blocked: {$url}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedUrls(): array
    {
        return [
            'google meet' => ['https://meet.google.com/abc-defg-hij'],
            'google meet subdomain' => ['https://foo.meet.google.com/abc-defg-hij'],
        ];
    }

    #[DataProvider('allowedUrls')]
    public function test_allowed_urls_pass_validation(string $url): void
    {
        $validator = Validator::make(
            ['url' => $url],
            ['url' => [new AllowedMeetingUrl()]]
        );

        $this->assertFalse($validator->fails(), "URL should be allowed: {$url}");
    }

    public function test_empty_value_fails_when_required(): void
    {
        $validator = Validator::make(
            ['url' => ''],
            ['url' => ['required', new AllowedMeetingUrl()]]
        );

        $this->assertTrue($validator->fails());
    }
}
