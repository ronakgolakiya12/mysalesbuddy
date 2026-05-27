@component('mail::message')
# Transcript processing failed

Hello {{ $user->name }},

We were unable to generate a transcript for your meeting **{{ $payload['meeting_title'] ?? 'Untitled meeting' }}**.

Our team has been notified. You can retry by re-uploading the recording or contact support if the problem persists.

@component('mail::button', ['url' => config('app.url') . '/meetings/' . ($payload['meeting_id'] ?? '')])
View Meeting
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
