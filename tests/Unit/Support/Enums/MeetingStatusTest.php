<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Enums;

use App\Support\Enums\MeetingStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MeetingStatusTest extends TestCase
{
    #[DataProvider('cases')]
    public function test_label_is_non_empty_string(MeetingStatus $status): void
    {
        $label = $status->label();

        $this->assertIsString($label);
        $this->assertNotSame('', $label);
    }

    /**
     * @return array<string, array{MeetingStatus}>
     */
    public static function cases(): array
    {
        return [
            'scheduled' => [MeetingStatus::Scheduled],
            'bot_joining' => [MeetingStatus::BotJoining],
            'recording' => [MeetingStatus::Recording],
            'processing' => [MeetingStatus::Processing],
            'ready' => [MeetingStatus::Ready],
            'failed' => [MeetingStatus::Failed],
            'cancelled' => [MeetingStatus::Cancelled],
        ];
    }

    public function test_is_terminal_for_ready_and_failed(): void
    {
        $this->assertTrue(MeetingStatus::Ready->isTerminal());
        $this->assertTrue(MeetingStatus::Failed->isTerminal());
    }

    public function test_is_terminal_false_for_others(): void
    {
        $this->assertFalse(MeetingStatus::Scheduled->isTerminal());
        $this->assertFalse(MeetingStatus::BotJoining->isTerminal());
        $this->assertFalse(MeetingStatus::Recording->isTerminal());
        $this->assertFalse(MeetingStatus::Processing->isTerminal());
        $this->assertFalse(MeetingStatus::Cancelled->isTerminal());
    }
}
