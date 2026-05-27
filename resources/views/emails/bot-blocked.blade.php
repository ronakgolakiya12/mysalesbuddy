@component('mail::message')
# Notetaker was blocked

Hello {{ $user->name }},

Our notetaker bot was unable to join your meeting **{{ $payload['meeting_title'] ?? 'Untitled meeting' }}**.

@isset($payload['reason'])
**Reason:** {{ $payload['reason'] }}
@endisset

This typically happens when the meeting has not yet started, the URL is invalid, the meeting ended before the bot was admitted, or the host did not allow the participant in.

@component('mail::button', ['url' => config('app.url') . '/meetings/' . ($payload['meeting_id'] ?? '')])
View Meeting
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
