@component('mail::message')
# Transcript is taking longer than usual

Hello {{ $user->name }},

Your transcript for **{{ $payload['meeting_title'] ?? 'Untitled meeting' }}** is taking longer to process than normal. We are still working on it and will notify you as soon as it is ready.

@component('mail::button', ['url' => config('app.url') . '/meetings/' . ($payload['meeting_id'] ?? '')])
View Meeting
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
