<?php

namespace App\Domain\Kioku\Exceptions;

use RuntimeException;

/**
 * User-actionable concierge letter error (duplicate week, unknown character,
 * generation failure, invalid evaluation state). The command and controller
 * surface the message directly; programming failures must not use this.
 */
final class KiokuLetterException extends RuntimeException {}
