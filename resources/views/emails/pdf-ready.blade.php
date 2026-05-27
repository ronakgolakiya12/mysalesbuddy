@component('mail::message')
# Your meeting PDF is ready

Hello {{ $user->name }},

The PDF export for your meeting **{{ $payload['meeting_title'] ?? 'Untitled meeting' }}** is ready to download. This download link will remain valid for 7 days.

@component('mail::button', ['url' => $payload['download_url'] ?? config('app.url')])
Download PDF
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
