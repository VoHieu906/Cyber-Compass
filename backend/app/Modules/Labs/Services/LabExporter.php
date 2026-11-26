<?php

namespace App\Modules\Labs\Services;

use App\Models\Lab;
use App\Modules\Content\Services\PhpArrayWriter;

/**
 * Writes a lab's live definition as `labs/<slug>.php` (D73), so a lab made or edited in the admin
 * can be reviewed and committed. The file holds answers and solutions: it is an authoring file,
 * never served. `content:import` reads it back through LabFiles.
 */
final class LabExporter
{
    /** @return array{php: string, has_draft: bool} */
    public function export(Lab $lab): array
    {
        $def = LabDefinition::fromLab($lab);
        $php = "<?php\n\n/*\n * Lab \"{$lab->slug}\", exported from the admin console. Read by LabFiles; the per-lab file wins over labs.php.\n */\n\nreturn "
            .PhpArrayWriter::write([$lab->slug => $def]).";\n";

        return ['php' => $php, 'has_draft' => $lab->draft()->exists()];
    }
}
