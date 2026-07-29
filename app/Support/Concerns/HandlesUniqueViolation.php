<?php

namespace App\Support\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait HandlesUniqueViolation
{
    /**
     * True if the exception is a database unique-constraint violation,
     * regardless of driver (pg/mysql/sqlite).
     */
    protected function isUniqueViolation(QueryException $e): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return ($e->errorInfo[0] ?? null) === '23505';
        }
        if ($driver === 'mysql') {
            return ($e->errorInfo[1] ?? null) === 1062;
        }
        if ($driver === 'sqlite') {
            return str_contains($e->getMessage(), 'UNIQUE constraint failed');
        }

        return false;
    }

    /**
     * Inspect a unique-violation exception and return the field name
     * whose constraint was violated (best-effort, driver-dependent).
     *
     * Returns 'phone_number', 'email', or null when the field can't be
     * determined. Callers should fall back to a generic error in that case.
     */
    protected function uniqueViolationField(QueryException $e): ?string
    {
        $message = $e->getMessage();
        $lower = strtolower($message);

        if (str_contains($lower, 'phone_number') || str_contains($message, 'phone number')) {
            return 'phone_number';
        }
        if (str_contains($lower, 'email')) {
            return 'email';
        }
        if (str_contains($lower, 'name')) {
            return 'name';
        }

        // Driver-specific index name hints (best-effort).
        $info = $e->errorInfo[2] ?? '';
        if (is_string($info) && $info !== '') {
            if (str_contains(strtolower($info), 'phone')) {
                return 'phone_number';
            }
            if (str_contains(strtolower($info), 'email')) {
                return 'email';
            }
        }

        return null;
    }

    /**
     * Build a 422 response for a unique-constraint violation using the
     * actual violated field (not a hard-coded "phone_number"). Falls back
     * to a generic message when the field can't be determined.
     */
    protected function uniqueViolationResponse(QueryException $e, ?string $defaultField = null): \Illuminate\Http\JsonResponse
    {
        $field = $this->uniqueViolationField($e) ?? $defaultField ?? 'phone_number';

        $messages = [
            'phone_number' => 'Bu telefon numarası zaten kullanılıyor.',
            'email' => 'Bu email adresi zaten kullanılıyor.',
            'name' => 'Bu isim zaten kullanılıyor.',
        ];

        $message = $messages[$field] ?? 'Bu değer zaten kullanılıyor.';

        return response()->json([
            'message' => $message,
            'errors' => [$field => [$message]],
        ], 422);
    }
}