<?php

/*
| Feature tests never pass through nginx and PHP-FPM, so the body limits set in the image are
| checked here: a content package part (plus its request overhead) must fit both, or the part
| would be refused or silently emptied in development (F6 Part C/D).
*/

/** The Dockerfile's shared base stage, which both the development and the production image build on. */
function baseStage(): string
{
    $dockerfile = (string) file_get_contents(__DIR__.'/../../../Dockerfile');
    preg_match('/^FROM .+ AS base$(.*?)^FROM /ms', $dockerfile, $m);

    return $m[1] ?? '';
}

function imageLimitBytes(string $name): int
{
    // One source for dev and production: production nginx and PHP take the same limits, and lesson
    // images still pass through the API there.
    expect(preg_match('/\b'.$name.'=(\d+)M\b/', baseStage(), $m))->toBe(1, "{$name} is not set in the Dockerfile's base stage");

    return (int) $m[1] * 1024 * 1024;
}

it('fits a content package part, with room for its request, under the image body limits', function () {
    $part = config('cybercompass.content_packages.part_bytes');
    $headroom = 1024 * 1024;

    expect($part + $headroom)->toBeLessThanOrEqual(imageLimitBytes('PHP_POST_MAX_SIZE'))
        ->and($part + $headroom)->toBeLessThanOrEqual(imageLimitBytes('NGINX_CLIENT_MAX_BODY_SIZE'))
        ->and(imageLimitBytes('NGINX_CLIENT_MAX_BODY_SIZE'))->toBe(imageLimitBytes('PHP_POST_MAX_SIZE'));
});

it('lets the 5 MB lesson image upload through', function () {
    expect(imageLimitBytes('PHP_UPLOAD_MAX_FILE_SIZE'))->toBeGreaterThanOrEqual(5 * 1024 * 1024);
});
