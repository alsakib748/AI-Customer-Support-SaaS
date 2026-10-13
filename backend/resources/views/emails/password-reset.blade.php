<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset your password</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hello {{ $userName }},</p>

    <p>We received a request to reset the password for your account.</p>

    <p>
        <a href="{{ $resetUrl }}"
           style="display:inline-block;padding:10px 18px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;">
            Reset password
        </a>
    </p>

    <p>If the button does not work, copy and paste this link into your browser:</p>
    <p><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>

    <p>This link will expire shortly. If you did not request a password reset, you can safely ignore this email — your password will remain unchanged.</p>
</body>
</html>
