<?php

namespace App\Modules\Content\Console;

use App\Modules\Content\Packages\FormatGuide;
use Illuminate\Console\Command;

/**
 * Writes resources/content-format/AI_AUTHORING_GUIDE.md from its source and the block registry
 * (D71). Run it after changing the source, a block schema or a fixture; --check only compares.
 */
final class FormatGuideCommand extends Command
{
    protected $signature = 'content:format-guide {--check : Fail if the committed guide is out of date instead of writing it}';

    protected $description = 'Generate the AI authoring guide for content packages';

    public function handle(FormatGuide $guide): int
    {
        $path = FormatGuide::path('AI_AUTHORING_GUIDE.md');
        $fresh = $guide->render();

        if ($this->option('check')) {
            if (@file_get_contents($path) !== $fresh) {
                $this->error('AI_AUTHORING_GUIDE.md is out of date: run php artisan content:format-guide.');

                return self::FAILURE;
            }
            $this->info('The AI authoring guide is current.');

            return self::SUCCESS;
        }

        if (@file_put_contents($path, $fresh) === false) {
            $this->error("Could not write {$path}. In the dev container, run as root: docker compose exec -u root api php artisan content:format-guide");

            return self::FAILURE;
        }
        $this->info('Wrote resources/content-format/AI_AUTHORING_GUIDE.md.');

        return self::SUCCESS;
    }
}
