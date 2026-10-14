<x-mail::message>
# New Demo Request

A new demo request has been submitted.

**Details:**
- Name: {{ \->full_name }}
- Email: {{ \->email }}
- Company: {{ \->company ?? 'N/A' }}
- Team Size: {{ \->team_size ?? 'N/A' }}
- Interests: {{ \->interests ?? 'N/A' }}

**Message:**
{{ \->message ?? 'No additional message' }}

<x-mail::button :url="config('app.frontend_url', config('app.url')) . '/admin/requests/demo'">
View in Admin
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
