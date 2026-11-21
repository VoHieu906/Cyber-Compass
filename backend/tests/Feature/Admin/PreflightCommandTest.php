<?php

it('passes a safe production configuration', function () {
    safeProductionConfig();

    $this->artisan('cybercompass:preflight')
        ->expectsOutputToContain('Configuration is ready for production.')
        ->assertSuccessful();
});

it('fails the deployment on an unsafe setting', function (array $override, string $label) {
    safeProductionConfig();
    config($override);

    $this->artisan('cybercompass:preflight')
        ->expectsOutputToContain("FAIL  {$label}")
        ->assertFailed();
})->with([
    'debug on' => [['app.debug' => true], 'Debug mode is off'],
    'no key' => [['app.key' => ''], 'An application key is set'],
    'http api' => [['app.url' => 'http://api.example.org'], 'The API URL uses HTTPS'],
    'insecure cookie' => [['session.secure' => null], 'Session cookies are Secure'],
    'script-readable cookie' => [['session.http_only' => false], 'Session cookies are HttpOnly'],
    'SameSite none' => [['session.same_site' => 'none'], 'Session cookies are SameSite lax or strict'],
    'cookie domain elsewhere' => [['session.domain' => '.other.org'], 'The session cookie domain covers the app and the API'],
    'frontend not stateful' => [['sanctum.stateful' => ['localhost:3000']], 'Sanctum treats the frontend as stateful'],
    'CORS wildcard' => [['cors.allowed_origins' => ['*']], 'CORS allows only the frontend'],
    'extra CORS origin' => [['cors.allowed_origins' => ['https://app.example.org', 'http://localhost:3000']], 'CORS allows only the frontend'],
    'mail to the log' => [['mail.default' => 'log'], 'Mail is delivered, not logged'],
    'sync queue' => [['queue.default' => 'sync'], 'Jobs go to the queue worker'],
    'local uploads' => [['cybercompass.uploads_disk' => 'public'], 'Uploads go to the private S3 bucket'],
    'debug logs' => [['logging.channels.stderr.level' => 'debug'], 'Logs are not at debug level'],
    'debug logs through a stack' => [['logging.default' => 'stack', 'logging.channels.stack' => ['driver' => 'stack', 'channels' => ['stderr', 'single']], 'logging.channels.single.level' => 'debug'], 'Logs are not at debug level'],
    'no web server token' => [['cybercompass.internal_api_token' => 'short'], 'The web server has its own API token'],
    'demo password' => [['cybercompass.demo_admin_password' => 'demo-pass-1'], 'No demo account passwords'],
]);

it('fails outside production', function () {
    safeProductionConfig();
    app()->detectEnvironment(fn () => 'local');

    $this->artisan('cybercompass:preflight')->expectsOutputToContain('FAIL  APP_ENV is production')->assertFailed();
});

it('keeps the API documentation closed in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/docs/api')->assertForbidden();
});
