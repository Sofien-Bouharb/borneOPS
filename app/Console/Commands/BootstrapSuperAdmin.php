<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use PragmaRX\Google2FA\Google2FA;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRStringText;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'app:bootstrap-super-admin';

    protected $description = 'Create or update the Super Administrator account with mandatory TOTP 2FA';

    public function handle(): int
    {
        $email = $this->ask('Email');
        $name = $this->ask('Name');
        $password = $this->secret('Password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles(['Super Administrator']);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $qrCodeUri = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

     $options = new QROptions([
    'outputInterface' => QRStringText::class,
]);

$this->line((new QRCode($options))->render($qrCodeUri));

        $this->line((new QRCode($options))->render($qrCodeUri));

        $this->info("Secret (manual entry fallback): {$secret}");

        $code = $this->ask('Scan the QR code above, then enter the 6-digit code from your authenticator app to confirm');

        if (! $google2fa->verifyKey($secret, $code)) {
            $this->error('Invalid code. 2FA was NOT enabled. Run this command again to retry.');

            return self::FAILURE;
        }

        $user->two_factor_secret = $secret;
        $user->two_factor_enabled = true;
        $user->save();

        $this->info("Super Administrator '{$email}' is set up with 2FA enabled.");

        return self::SUCCESS;
    }
}
