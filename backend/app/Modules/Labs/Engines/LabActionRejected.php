<?php

namespace App\Modules\Labs\Engines;

use RuntimeException;

/** A malformed or out-of-bounds action. Rendered as 422 with the message under `action`. */
final class LabActionRejected extends RuntimeException {}
