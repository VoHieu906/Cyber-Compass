<?php

namespace App\Modules\Admin\Console;

use Illuminate\Console\Command;

/**
 * Refuses a production deployment whose configuration is unsafe (docs/SECURITY_REVIEW.md,
 * OWASP "security misconfiguration"). The migration task runs it first on AWS, so a missing
 * Secure cookie flag, an open CORS list or debug mode stops the deployment before anything
 * changes. It reads only configuration and never prints a secret's value.
 *
 *   php artisan cybercompass:preflight
 */
final class PreflightCommand extends Command
{
    protected $signature = 'cybercompass:preflight';

    protected $description = 'Check that the configuration is safe for production (fails the deployment if not)';

    public function handle(): int
    {
        $failures = 0;
        foreach ($this->checks() as [$ok, $label, $fix]) {
            if ($ok) {
                $this->line("  <fg=green>ok</>    {$label}");

                continue;
            }
            $failures++;
            $this->line("  <fg=red>FAIL</>  {$label}: {$fix}");
        }

        if ($failures > 0) {
            $this->error("{$failures} production setting(s) are unsafe. Fix them before deploying.");

            return self::FAILURE;
        }

        $this->info('Configuration is ready for production.');

        return self::SUCCESS;
    }

    /** @return list<array{0: bool, 1: string, 2: string}> */
    private function checks(): array
    {
        $frontend = (string) config('app.frontend_url');
        $frontendHost = (string) parse_url($frontend, PHP_URL_HOST);
        $apiHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $cookieDomain = ltrim((string) config('session.domain'), '.');
        $origins = (array) config('cors.allowed_origins');
        $logLevels = self::logLevels((string) config('logging.default'));

        return [
            [app()->environment('production'), 'APP_ENV is production', 'set APP_ENV=production'],
            [config('app.debug') === false, 'Debug mode is off', 'set APP_DEBUG=false (debug pages leak code and settings)'],
            [filled(config('app.key')), 'An application key is set', 'set APP_KEY from Secrets Manager'],
            [str_starts_with((string) config('app.url'), 'https://'), 'The API URL uses HTTPS', 'set APP_URL=https://api.<domain>'],
            [str_starts_with($frontend, 'https://'), 'The frontend URL uses HTTPS', 'set FRONTEND_URL=https://app.<domain>'],
            [config('session.secure') === true, 'Session cookies are Secure', 'set SESSION_SECURE_COOKIE=true'],
            [config('session.http_only') === true, 'Session cookies are HttpOnly', 'leave SESSION_HTTP_ONLY unset or true'],
            [in_array(config('session.same_site'), ['lax', 'strict'], true), 'Session cookies are SameSite lax or strict', 'set SESSION_SAME_SITE=lax'],
            [
                $cookieDomain !== '' && self::within($frontendHost, $cookieDomain) && self::within($apiHost, $cookieDomain),
                'The session cookie domain covers the app and the API',
                'set SESSION_DOMAIN=.<domain> (D41)',
            ],
            [in_array($frontendHost, (array) config('sanctum.stateful'), true), 'Sanctum treats the frontend as stateful', 'set SANCTUM_STATEFUL_DOMAINS=app.<domain>'],
            [
                $origins === [$frontend],
                'CORS allows only the frontend',
                'set CORS_ALLOWED_ORIGINS (or FRONTEND_URL) to the https frontend URL alone',
            ],
            [! in_array(config('mail.default'), ['log', 'array'], true), 'Mail is delivered, not logged', 'set MAIL_MAILER=ses (D39)'],
            [config('queue.default') !== 'sync', 'Jobs go to the queue worker', 'set QUEUE_CONNECTION=database (D37)'],
            [
                config('cybercompass.uploads_disk') === 's3' && filled(config('filesystems.disks.s3.bucket')),
                'Uploads go to the private S3 bucket',
                'set UPLOADS_DISK=s3 and AWS_BUCKET',
            ],
            [
                ($transfers = (array) config('filesystems.disks.'.config('cybercompass.content_packages.disk'))) && ($transfers['driver'] ?? null) === 's3' && filled($transfers['bucket'] ?? null),
                'Content packages go to their private S3 bucket',
                'set TRANSFERS_DISK=transfers and AWS_TRANSFERS_BUCKET (the api and the worker do not share a disk, D70)',
            ],
            [
                $logLevels !== [] && ! in_array('debug', $logLevels, true),
                'Logs are not at debug level',
                'set LOG_LEVEL=info or warning',
            ],
            [
                strlen((string) config('cybercompass.internal_api_token')) >= 32,
                'The web server has its own API token',
                'set INTERNAL_API_TOKEN (32+ random characters) on the api and web services',
            ],
            [
                blank(config('cybercompass.demo_user_password')) && blank(config('cybercompass.demo_admin_password')),
                'No demo account passwords are configured',
                'leave DEMO_USER_PASSWORD and DEMO_ADMIN_PASSWORD empty',
            ],
        ];
    }

    /** @return list<string> levels of the channel, or of every channel a stack writes to */
    private static function logLevels(string $channel): array
    {
        $config = (array) config("logging.channels.{$channel}");
        if (($config['driver'] ?? null) === 'stack') {
            $levels = [];
            foreach ((array) ($config['channels'] ?? []) as $inner) {
                $levels = [...$levels, ...self::logLevels((string) $inner)];
            }

            return $levels;
        }

        return [(string) ($config['level'] ?? 'debug')];
    }

    private static function within(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
