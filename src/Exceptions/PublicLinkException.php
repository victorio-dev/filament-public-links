<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Exceptions;

use RuntimeException;

/**
 * Base class of every exception the package throws, so callers can catch them in one place.
 */
abstract class PublicLinkException extends RuntimeException {}
