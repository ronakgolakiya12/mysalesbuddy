<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $meeting->title ?? 'Meeting Report' }}</title>
    <style>
        @page { margin: 24px 28px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5px;
            color: #1f2937;
            line-height: 1.55;
            margin: 0;
        }

        /* ---------- Header ---------- */
        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 6px 0;
            color: #111827;
            font-weight: 700;
        }
        .meta-grid {
            margin-top: 8px;
            color: #6b7280;
            font-size: 9.5px;
        }
        .meta-grid td {
            padding: 1px 14px 1px 0;
            vertical-align: top;
        }
        .meta-grid .label {
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-size: 8.5px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            background: #ecfdf5;
            color: #047857;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ---------- Section headings ---------- */
        h2 {
            font-size: 13px;
            margin: 20px 0 8px 0;
            color: #4f46e5;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }
        h3 {
            font-size: 11px;
            margin: 12px 0 4px 0;
            color: #1f2937;
            font-weight: 700;
        }

        /* ---------- Score card ---------- */
        .score-card {
            background: #f5f3ff;
            border: 1px solid #ddd6fe;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }
        .score-card .score {
            font-size: 30px;
            font-weight: 800;
            color: #4f46e5;
            line-height: 1;
        }
        .score-card .score-suffix {
            font-size: 14px;
            color: #6366f1;
            font-weight: 600;
        }
        .score-card .rationale {
            margin-top: 6px;
            color: #4b5563;
            font-size: 10.5px;
        }

        /* ---------- One-liner ---------- */
        .takeaway {
            background: #eef2ff;
            border-left: 4px solid #4f46e5;
            padding: 10px 14px;
            margin: 12px 0;
            color: #312e81;
            font-style: italic;
            font-size: 11px;
        }

        /* ---------- Talk time bar ---------- */
        .talk-time {
            margin: 8px 0 4px 0;
        }
        .talk-time-bar {
            height: 10px;
            background: #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
        }
        .talk-time-bar .fill {
            height: 100%;
            background: #4f46e5;
        }
        .talk-time-legend {
            display: table;
            width: 100%;
            margin-top: 4px;
            font-size: 9.5px;
            color: #6b7280;
        }
        .talk-time-legend .left { display: table-cell; text-align: left; color: #4f46e5; font-weight: 600; }
        .talk-time-legend .right { display: table-cell; text-align: right; }

        /* ---------- Strength / Opportunity cards ---------- */
        .card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 12px;
            margin: 6px 0;
        }
        .card.strength { border-left: 3px solid #10b981; background: #f0fdf4; }
        .card.opportunity { border-left: 3px solid #f59e0b; background: #fffbeb; }
        .card .title { font-weight: 700; font-size: 10.5px; color: #111827; }
        .card .detail { margin-top: 3px; color: #374151; font-size: 10px; }
        .card .evidence {
            margin-top: 6px;
            padding-left: 8px;
            border-left: 2px solid #e5e7eb;
            color: #6b7280;
            font-size: 9.5px;
            font-style: italic;
        }

        /* ---------- Discovery checklist ---------- */
        .checklist {
            margin: 6px 0;
        }
        .checklist .row {
            padding: 3px 0;
            font-size: 10px;
        }
        .checklist .yes { color: #047857; font-weight: 700; }
        .checklist .no { color: #b91c1c; font-weight: 700; }

        /* ---------- Next step ---------- */
        .next-step {
            background: #f9fafb;
            border-radius: 6px;
            padding: 10px 12px;
            margin-top: 6px;
            font-size: 10.5px;
        }
        .next-step .clarity {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            margin-right: 8px;
        }
        .next-step .clarity.clear { background: #d1fae5; color: #047857; }
        .next-step .clarity.vague { background: #fef3c7; color: #92400e; }
        .next-step .clarity.missing { background: #fee2e2; color: #b91c1c; }

        /* ---------- Empty coaching placeholder ---------- */
        .placeholder {
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 6px;
            padding: 14px;
            color: #6b7280;
            font-size: 10.5px;
            text-align: center;
        }

        /* ---------- Transcript ---------- */
        .page-break { page-break-before: always; }
        .turn {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .turn-header {
            margin-bottom: 4px;
        }
        .turn-header .speaker {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .turn-header .ts {
            margin-left: 8px;
            color: #9ca3af;
            font-size: 9px;
        }
        .turn-body {
            padding-left: 4px;
            border-left: 2px solid #c7d2fe;
            margin-left: 4px;
        }
        .turn-body .line {
            padding: 2px 0 2px 8px;
            font-size: 10.5px;
        }
        .turn-body .line .inline-ts {
            color: #c5cad1;
            font-size: 8.5px;
            margin-right: 6px;
            font-family: 'DejaVu Sans Mono', monospace;
        }

        /* ---------- Footer ---------- */
        .footer {
            color: #9ca3af;
            font-size: 8.5px;
            text-align: center;
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #f3f4f6;
        }
    </style>
</head>
<body>
    @php
        $statusVal = $meeting->status instanceof \App\Support\Enums\MeetingStatus ? $meeting->status->value : (string) $meeting->status;
        $providerVal = $meeting->provider instanceof \App\Support\Enums\MeetingProvider ? $meeting->provider->value : (string) $meeting->provider;
        $providerLabel = match ($providerVal) {
            'google_meet' => 'Google Meet',
            'teams' => 'Microsoft Teams',
            'zoom' => 'Zoom',
            default => ucfirst((string) $providerVal),
        };
        $hasCoaching = $analysis && $analysis->completed_at;
        $output = $hasCoaching ? ($analysis->output_json ?? []) : [];
        $repPct = $analysis?->talk_time_rep;
        $prospectPct = $analysis?->talk_time_prospect;
        $hasTalkTime = $repPct !== null && $prospectPct !== null;
    @endphp

    {{-- ====================== HEADER ====================== --}}
    <div class="header">
        <h1>{{ $meeting->title ?? 'Untitled Meeting' }}</h1>
        <table class="meta-grid" cellspacing="0" cellpadding="0">
            <tr>
                <td><span class="label">Provider</span></td>
                <td>{{ $providerLabel }}</td>
                <td><span class="label">Status</span></td>
                <td><span class="badge">{{ strtoupper(str_replace('_', ' ', $statusVal)) }}</span></td>
            </tr>
            @if($meeting->started_at)
                <tr>
                    <td><span class="label">Started</span></td>
                    <td>{{ $meeting->started_at->format('d M Y, H:i') }}</td>
                    <td><span class="label">Duration</span></td>
                    <td>{{ $meeting->durationFormatted() ?? '—' }}</td>
                </tr>
            @endif
            @if($meeting->external_meeting_url)
                <tr>
                    <td><span class="label">Meeting</span></td>
                    <td colspan="3" style="color:#6b7280;">{{ $meeting->external_meeting_url }}</td>
                </tr>
            @endif
        </table>
    </div>

    {{-- ====================== COACHING ====================== --}}
    <h2>Coaching Analysis</h2>

    @if($hasCoaching)
        @if($analysis->overall_score !== null)
            <div class="score-card">
                <span class="score">{{ $analysis->overall_score }}</span><span class="score-suffix">/10</span>
                @if(!empty($output['rationale']))
                    <div class="rationale">{{ $output['rationale'] }}</div>
                @endif
            </div>
        @endif

        @if(!empty($output['one_liner']))
            <div class="takeaway">💡 {{ $output['one_liner'] }}</div>
        @endif

        @if($hasTalkTime)
            <h3>Talk time ratio</h3>
            <div class="talk-time">
                <div class="talk-time-bar"><div class="fill" style="width: {{ max(0, min(100, $repPct)) }}%;"></div></div>
                <div class="talk-time-legend">
                    <span class="left">You — {{ $repPct }}%</span>
                    <span class="right">Other — {{ $prospectPct }}%</span>
                </div>
            </div>
        @endif

        @if(!empty($output['next_step_clarity']) || !empty($output['next_step_detail']))
            <h3>Next step</h3>
            <div class="next-step">
                @php $clarity = $output['next_step_clarity'] ?? 'missing'; @endphp
                <span class="clarity {{ $clarity }}">
                    @switch($clarity)
                        @case('clear') ✓ Clear @break
                        @case('vague') ~ Vague @break
                        @default ✗ Missing
                    @endswitch
                </span>
                {{ $output['next_step_detail'] ?? '' }}
            </div>
        @endif

        @if(!empty($output['discovery_quality']))
            @php $dq = $output['discovery_quality']; @endphp
            <h3>Discovery quality</h3>
            <div class="checklist">
                <div class="row">
                    <span class="{{ ($dq['pain_uncovered'] ?? false) ? 'yes' : 'no' }}">{{ ($dq['pain_uncovered'] ?? false) ? '✓' : '✗' }}</span>
                    Pain uncovered
                </div>
                <div class="row">
                    <span class="{{ ($dq['impact_quantified'] ?? false) ? 'yes' : 'no' }}">{{ ($dq['impact_quantified'] ?? false) ? '✓' : '✗' }}</span>
                    Impact quantified
                </div>
                <div class="row">
                    <span class="{{ ($dq['decision_process_explored'] ?? false) ? 'yes' : 'no' }}">{{ ($dq['decision_process_explored'] ?? false) ? '✓' : '✗' }}</span>
                    Decision process explored
                </div>
                <div class="row">
                    <span class="{{ ($dq['timeline_confirmed'] ?? false) ? 'yes' : 'no' }}">{{ ($dq['timeline_confirmed'] ?? false) ? '✓' : '✗' }}</span>
                    Timeline confirmed
                </div>
            </div>
        @endif

        @if(!empty($output['strengths']) && is_array($output['strengths']))
            <h3>Strengths</h3>
            @foreach($output['strengths'] as $item)
                @if(is_array($item))
                    <div class="card strength">
                        <div class="title">{{ $item['title'] ?? 'Strength' }}</div>
                        @if(!empty($item['detail']))<div class="detail">{{ $item['detail'] }}</div>@endif
                        @if(!empty($item['evidence']['quote']))
                            <div class="evidence">"{{ $item['evidence']['quote'] }}"</div>
                        @endif
                    </div>
                @else
                    <div class="card strength"><div class="detail">{{ $item }}</div></div>
                @endif
            @endforeach
        @endif

        @if(!empty($output['opportunities']) && is_array($output['opportunities']))
            <h3>Coaching opportunities</h3>
            @foreach($output['opportunities'] as $item)
                @if(is_array($item))
                    <div class="card opportunity">
                        <div class="title">{{ $item['title'] ?? 'Opportunity' }}</div>
                        @if(!empty($item['detail']))<div class="detail">{{ $item['detail'] }}</div>@endif
                        @if(!empty($item['suggestion']))<div class="detail"><strong>Suggestion:</strong> {{ $item['suggestion'] }}</div>@endif
                        @if(!empty($item['evidence']['quote']))
                            <div class="evidence">"{{ $item['evidence']['quote'] }}"</div>
                        @endif
                    </div>
                @else
                    <div class="card opportunity"><div class="detail">{{ $item }}</div></div>
                @endif
            @endforeach
        @endif

        @if(!empty($output['objection_handling']))
            @php $obj = $output['objection_handling']; @endphp
            <h3>Objection handling</h3>
            @if(!empty($obj['summary']))
                <p style="color:#4b5563; font-size:10px; margin-top:0;">{{ $obj['summary'] }}</p>
            @endif
            @if(!empty($obj['objections']) && is_array($obj['objections']))
                @foreach($obj['objections'] as $o)
                    <div class="card">
                        <div class="title">Objection: {{ $o['objection'] ?? '' }}</div>
                        <div class="detail"><strong>Response:</strong> {{ $o['response_summary'] ?? '' }}</div>
                        <div class="detail">
                            <span class="clarity {{ ($o['resolved'] ?? false) ? 'clear' : 'missing' }}">
                                {{ ($o['resolved'] ?? false) ? '✓ Resolved' : '✗ Unresolved' }}
                            </span>
                        </div>
                    </div>
                @endforeach
            @endif
        @endif
    @else
        <div class="placeholder">
            No coaching analysis available yet for this meeting.
        </div>
    @endif

    {{-- ====================== TRANSCRIPT ====================== --}}
    @if($segments->isNotEmpty())
        <div class="page-break"></div>
        <h2>Transcript</h2>
        @php
            $turns = [];
            $currentTurn = null;
            foreach ($segments as $seg) {
                $speaker = $seg->speaker_label ?? 'Unknown';
                if ($currentTurn === null || $currentTurn['speaker'] !== $speaker) {
                    if ($currentTurn !== null) {
                        $turns[] = $currentTurn;
                    }
                    $currentTurn = [
                        'speaker' => $speaker,
                        'start_ms' => (int) $seg->start_ms,
                        'lines' => [],
                    ];
                }
                $currentTurn['lines'][] = [
                    'ts' => gmdate('i:s', (int) ($seg->start_ms / 1000)),
                    'body' => (string) $seg->body,
                ];
            }
            if ($currentTurn !== null) {
                $turns[] = $currentTurn;
            }
        @endphp

        @foreach($turns as $turn)
            <div class="turn">
                <div class="turn-header">
                    <span class="speaker">{{ $turn['speaker'] }}</span>
                    <span class="ts">{{ gmdate('i:s', (int) ($turn['start_ms'] / 1000)) }}</span>
                </div>
                <div class="turn-body">
                    @foreach($turn['lines'] as $line)
                        <div class="line"><span class="inline-ts">{{ $line['ts'] }}</span>{{ $line['body'] }}</div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <h2>Transcript</h2>
        <div class="placeholder">No transcript available for this meeting.</div>
    @endif

    <div class="footer">
        Generated by {{ config('app.name') }} on {{ now()->format('d M Y, H:i') }} UTC
    </div>
</body>
</html>
