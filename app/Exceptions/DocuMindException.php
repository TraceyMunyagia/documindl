<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Catch this one type to handle every DocuMind-specific failure. */
abstract class DocuMindException extends RuntimeException {}
