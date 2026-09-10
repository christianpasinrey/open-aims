<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Exceptions;

use RuntimeException;

/** The project or milestone used to narrow the map does not exist in the workspace. */
final class GraphScopeNotFound extends RuntimeException {}
