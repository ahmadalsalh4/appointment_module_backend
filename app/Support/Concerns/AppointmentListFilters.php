<?php

namespace App\Support\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Shared helpers for the appointment list endpoints (admin/customer/staff).
 * Centralises:
 *   - filter allowlist enforcement (422 on unknown keys)
 *   - rule definitions for status/date/sort/pagination
 *   - per-status query-key application
 *
 * Keeps each AppointmentController method thin and consistent.
 */
trait AppointmentListFilters
{
    /**
     * @param  array<int, string>  $allowedKeys
     * @return array<int, string>
     */
    protected function validateListRequest(Request $request, array $allowedKeys): array
    {
        $this->rejectUnknownFilters($request, $allowedKeys);

        $statusIds = \App\Models\Status::pluck('id')->all();

        $validated = $request->validate([
            'tab' => ['sometimes', 'in:upcoming,pending,completed,cancelled'],
            'status_id' => ['sometimes', 'integer', Rule::in($statusIds)],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'sort_by' => ['sometimes', 'in:start_date,state_id,created_at'],
            'sort_order' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return $validated;
    }

    /**
     * Apply the validated filters to a list query.
     */
    protected function applyListFilters($query, Request $request, array $validated, bool $withCustomer = true): void
    {
        if ($request->filled('tab')) {
            $query->tab($request->tab);
        }
        if ($request->filled('status_id')) {
            $query->byStatus((int) $validated['status_id']);
        }
        if ($request->filled('date')) {
            $query->onDate($validated['date']);
        }

        $sortOrder = strtolower((string) ($validated['sort_order'] ?? 'asc'));
        $sortOrder = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'asc';

        $sortBy = (string) ($validated['sort_by'] ?? '');
        if (in_array($sortBy, ['start_date', 'state_id', 'created_at'], true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('start_date', 'asc');
        }
    }

    protected function paginationSize(Request $request): int
    {
        return max(1, min(100, (int) $request->get('per_page', 15)));
    }

    /**
     * @param  array<int, string>  $allowedKeys
     */
    protected function rejectUnknownFilters(Request $request, array $allowedKeys): void
    {
        $unknown = array_values(array_diff(array_keys($request->query()), $allowedKeys));
        if (! empty($unknown)) {
            abort(response()->json([
                'message' => 'Bilinmeyen filtre parametreleri: '.implode(', ', $unknown),
                'errors' => array_fill_keys($unknown, ['Bu filtre kabul edilmiyor.']),
            ], 422));
        }
    }
}