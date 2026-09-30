<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A business rule refused the request. Renders 409 with a machine-readable code the app switches on.
 */
class ConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code'    => $this->errorCode,
            'message' => $this->getMessage(),
            ...$this->extra,
        ], 409);
    }
}
