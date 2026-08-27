<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $field = 'quantity',
    ) {
        parent::__construct($message);
    }

    public function report(): false
    {
        return false;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => [
                    $this->field => [$this->getMessage()],
                ],
            ], 422);
        }

        return back()
            ->withErrors([$this->field => $this->getMessage()])
            ->withInput();
    }
}
