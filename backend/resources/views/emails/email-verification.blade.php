<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Verify your email address</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hello {{ $userName }},</p>

    <p>Thanks for creating your account. Please confirm your email address to activate it.</p>

    <p>
        <a href="{{ $verifyUrl }}"
           style="display:inline-block;padding:10px 18px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;">
            Verify email address
        </a>
    </p>

    <p>If the button does not work, copy and paste this link into your browser:</p>
    <p><a href="{{ $verifyUrl }}">{{ $verifyUrl }}</a></p>

    <p>If you did not create this account, you can safely ignore this email.</p>
</body>
</html>
