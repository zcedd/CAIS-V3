<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EverifyVerificationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $field = 'intake_method',
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
            ->withInput($request->except([
                'everify_fingerprint',
                'face_liveness_session_id',
                'everify_qr_value',
            ]));
    }
}
