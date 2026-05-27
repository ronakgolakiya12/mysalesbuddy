@component('mail::message')
# Your coaching analysis is ready

Hello {{ $user->name }},

The coaching analysis for **{{ $payload['meeting_title'] ?? 'Untitled meeting' }}** is now available.

@isset($payload['overall_score'])
**Overall score:** {{ $payload['overall_score'] }}/10
@endisset

@component('mail::button', ['url' => config('app.url') . '/meetings/' . ($payload['meeting_id'] ?? '') . '/coaching'])
View Coaching
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
