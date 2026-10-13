<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>You have been added to {{ $tenantName }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hello {{ $userName }},</p>

    <p>
        @if ($inviterName)
            {{ $inviterName }} has added you to the <strong>{{ $tenantName }}</strong> workspace.
        @else
            You have been added to the <strong>{{ $tenantName }}</strong> workspace.
        @endif
    </p>

    <p>
        <a href="{{ $dashboardUrl }}"
           style="display:inline-block;padding:10px 18px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;">
            Open your dashboard
        </a>
    </p>

    <p>If the button does not work, copy and paste this link into your browser:</p>
    <p><a href="{{ $dashboardUrl }}">{{ $dashboardUrl }}</a></p>

    <p>If you were not expecting this, please contact your workspace administrator.</p>
</body>
</html>
