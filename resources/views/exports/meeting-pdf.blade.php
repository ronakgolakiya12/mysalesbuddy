<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $meeting->title ?? 'Meeting Report' }}</title>
    <style>
        @page { margin: 30px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.5;
        }
        h1 {
            font-size: 22px;
            margin: 0 0 8px 0;
            color: #111827;
        }
        h2 {
            font-size: 16px;
            margin: 24px 0 8px 0;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        h3 {
            font-size: 13px;
            margin: 16px 0 4px 0;
            color: #374151;
        }
        .meta {
            color: #6b7280;
            font-size: 10px;
            margin-bottom: 12px;
        }
        .meta strong { color: #374151; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .score-box {
            background: #f3f4f6;
            border-left: 4px solid #4f46e5;
            padding: 12px 16px;
            margin: 12px 0;
            font-size: 14px;
        }
        .score-box .score {
            font-size: 28px;
            font-weight: 700;
            color: #4f46e5;
        }
        ul {
            margin: 4px 0 8px 16px;
            padding: 0;
        }
        li { margin-bottom: 2px; }
        .page-break { page-break-after: always; }
        .segment {
            margin-bottom: 6px;
            padding: 6px 8px;
            border-left: 2px solid #d1d5db;
        }
        .segment .speaker {
            font-weight: 600;
            color: #4f46e5;
            font-size: 10px;
        }
        .segment .ts {
            color: #9ca3af;
            font-size: 9px;
            margin-left: 6px;
        }
        .segment .body {
            margin-top: 2px;
            color: #1f2937;
        }
        .footer {
            color: #9ca3af;
            font-size: 9px;
            text-align: center;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <h1>{{ $meeting->title ?? 'Meeting Report' }}</h1>
    <div class="meta">
        <strong>Provider:</strong> {{ $meeting->provider instanceof \App\Support\Enums\MeetingProvider ? $meeting->provider->value : $meeting->provider }}
        &nbsp;|&nbsp;
        <strong>Status:</strong> <span class="badge">{{ $meeting->status instanceof \App\Support\Enums\MeetingStatus ? $meeting->status->value : $meeting->status }}</span>
        @if($meeting->started_at)
            &nbsp;|&nbsp;<strong>Started:</strong> {{ $meeting->started_at->toDateTimeString() }}
        @endif
        @if($meeting->duration_seconds)
            &nbsp;|&nbsp;<strong>Duration:</strong> {{ $meeting->durationFormatted() }}
        @endif
    </div>

    @if($analysis && $analysis->completed_at)
        <h2>Coaching Analysis</h2>
        <div class="score-box">
            <span class="score">{{ $analysis->overall_score }}/10</span>
            &nbsp;Overall score
        </div>

        @php
            $output = $analysis->output_json ?? [];
        @endphp

        @if(!empty($output['summary']))
            <h3>Summary</h3>
            <p>{{ $output['summary'] }}</p>
        @endif

        @if(!empty($output['strengths']))
            <h3>Strengths</h3>
            <ul>
                @foreach($output['strengths'] as $item)
                    <li>{{ is_array($item) ? ($item['text'] ?? json_encode($item)) : $item }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty($output['improvements']))
            <h3>Areas to Improve</h3>
            <ul>
                @foreach($output['improvements'] as $item)
                    <li>{{ is_array($item) ? ($item['text'] ?? json_encode($item)) : $item }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty($output['questions_asked']))
            <h3>Questions Asked</h3>
            <ul>
                @foreach($output['questions_asked'] as $item)
                    <li>{{ is_array($item) ? ($item['text'] ?? json_encode($item)) : $item }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty($output['objections']))
            <h3>Objections</h3>
            <ul>
                @foreach($output['objections'] as $item)
                    <li>{{ is_array($item) ? ($item['text'] ?? json_encode($item)) : $item }}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty($output['next_steps']))
            <h3>Next Steps</h3>
            <ul>
                @foreach($output['next_steps'] as $item)
                    <li>{{ is_array($item) ? ($item['text'] ?? json_encode($item)) : $item }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    <div class="page-break"></div>

    <h2>Transcript</h2>
    @forelse($segments as $segment)
        <div class="segment">
            <span class="speaker">{{ $segment->speaker_label ?? 'Unknown' }}</span>
            <span class="ts">{{ gmdate('i:s', (int) ($segment->start_ms / 1000)) }}</span>
            <div class="body">{{ $segment->body }}</div>
        </div>
    @empty
        <p>No transcript segments available.</p>
    @endforelse

    <div class="footer">
        Generated by {{ config('app.name') }} on {{ now()->toDateTimeString() }} UTC
    </div>
</body>
</html>
