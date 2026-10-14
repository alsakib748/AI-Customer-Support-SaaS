<x-mail::message>
# Demo Request Received

Hi {{ \->full_name }},

Thank you for requesting a demo. We've received your request and will contact you soon to schedule a convenient time.

**Your Details:**
- Name: {{ \->full_name }}
- Email: {{ \->email }}
- Company: {{ \->company ?? 'N/A' }}
- Team Size: {{ \->team_size ?? 'N/A' }}

We'll get back to you as soon as possible.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
