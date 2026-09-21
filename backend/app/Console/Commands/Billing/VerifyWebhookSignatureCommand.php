<?php

namespace App\Console\Commands\Billing;

use App\Payments\PaymentGatewayManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:verify-webhook-signature-command')]
#[Description('Command description')]
class VerifyWebhookSignatureCommand extends Command
{

    protected $signature = 'billing:verify-webhook
                            {provider : stripe|paypal}
                            {--payload= : Path to payload JSON file}
                            {--signature= : Value of the signature header}';

    protected $description = 'Verify a webhook signature for debugging';

    /**
     * Execute the console command.
     */
    public function handle(PaymentGatewayManager $gateways)
    {
        $provider  = $this->argument('provider');
        $path      = $this->option('payload');
        $signature = $this->option('signature');

        if (!$path || !file_exists($path)) {
            $this->error('Payload file not found.');
            return self::FAILURE;
        }

        $payload = file_get_contents($path);

        $headers = match ($provider) {
            'stripe' => ['stripe-signature' => [$signature]],
            'paypal' => ['paypal-transmission-sig' => [$signature]],
            default  => [],
        };

        $gateway = $gateways->driver($provider);
        $ok = $gateway->verifyWebhook($payload, $headers);

        $this->info($ok ? '✓ Signature VALID' : '✗ Signature INVALID');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
