# Laravel Vue Template

A Laravel template with Inertia, Vue, and an installable PWA.

## Local development

Requires PHP 8.5, Composer, and Node.js 24.11 or later in the 24.x series.

Run these commands in a fresh checkout.

```bash
composer setup
composer dev
```

## PWA push setup

After running `composer install`, generate a VAPID key pair for the target environment from the project directory.

```bash
php -r 'require "vendor/autoload.php"; $keys = \Minishlink\WebPush\VAPID::createVapidKeys(); printf("WEB_PUSH_VAPID_PUBLIC_KEY=%s\nWEB_PUSH_VAPID_PRIVATE_KEY=%s\n", $keys["publicKey"], $keys["privateKey"]);'
```

Copy the two lines into the target environment's `.env` file or environment variables. Store the private key as a secret. Keep the same key pair across deployments so existing subscriptions stay valid. If Laravel caches the configuration, rebuild it with `php artisan config:cache`.

Under **Settings → Notifications**, enable push for the account and use **Connect this device** on each device you want to receive notifications on. Grant browser permission when prompted. **Send test push** sends to every connected device on the account. Web Push requires HTTPS or localhost. On iPhone and iPad, open the app from the Home Screen.

## Documentation

Write and maintain all project documentation in English. Consult the [push notification guide](PUSH_NOTIFICATIONS.md) when changing push settings, subscriptions, or delivery.
