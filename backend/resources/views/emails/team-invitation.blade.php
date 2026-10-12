<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Workspace invitation</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="font-size: 18px;">You've been invited to {{ $workspaceName }}</h1>

    <p>
        @if ($inviterName)
            {{ $inviterName }} has invited you
        @else
            You have been invited
        @endif
        to join the <strong>{{ $workspaceName }}</strong> workspace as an <strong>{{ $role }}</strong>.
    </p>

    <p>
        <a href="{{ $acceptUrl }}" style="display: inline-block; padding: 10px 18px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Accept invitation
        </a>
    </p>

    <p style="color: #6b7280; font-size: 13px;">
        If the button doesn't work, copy this link into your browser:<br>
        {{ $acceptUrl }}
    </p>

    @if ($expiresAt)
        <p style="color: #6b7280; font-size: 13px;">This invitation expires on {{ $expiresAt }}.</p>
    @endif
</body>
</html>
