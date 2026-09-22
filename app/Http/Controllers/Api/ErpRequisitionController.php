<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Requisition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only procurement export designed for an external ERP/procurement tool.
 *
 * No ERP is allowed to mutate the NU Clark source records through this
 * endpoint. Consumers can pull normalized requisition headers + line items as
 * JSON or CSV and incrementally sync using updated_since.
 */
class ErpRequisitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);
        $limit = min(max((int) ($filters['limit'] ?? 100), 1), 500);

        $requisitions = $this->query($filters)
            ->limit($limit)
            ->get()
            ->map(fn (Requisition $requisition) => $this->transform($requisition))
            ->values();

        $this->audit($request, 'json', $filters, $requisitions->count());

        return response()->json([
            'schema_version' => '1.0',
            'generated_at' => now()->toIso8601String(),
            'count' => $requisitions->count(),
            'data' => $requisitions,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $limit = min(max((int) ($filters['limit'] ?? 500), 1), 5000);
        $rows = $this->query($filters)->limit($limit)->get();

        $this->audit($request, 'csv', $filters, $rows->count());

        $filename = 'requisitions-erp-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, [
                'requisition_no', 'status', 'requested_at', 'updated_at',
                'department_code', 'department_name', 'requester_name', 'requester_email',
                'purpose', 'item_code', 'item_name', 'item_type', 'unit',
                'quantity_requested', 'quantity_approved', 'unit_price', 'line_total',
            ]);

            foreach ($rows as $requisition) {
                foreach ($requisition->items as $line) {
                    fputcsv($out, [
                        $requisition->requisition_no,
                        $requisition->status,
                        optional($requisition->requested_at)->toIso8601String(),
                        optional($requisition->updated_at)->toIso8601String(),
                        $requisition->department?->code,
                        $requisition->department?->name,
                        $requisition->user?->name,
                        $requisition->user?->email,
                        $requisition->purpose,
                        $line->item?->item_code,
                        $line->item?->name,
                        $line->item?->item_type,
                        $line->item?->unit,
                        $line->quantity_requested,
                        $line->quantity_approved,
                        $line->unit_price,
                        $line->total_amount,
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'updated_since' => ['nullable', 'date'],
            'updated_until' => ['nullable', 'date'],
            'department_code' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);
    }

    private function query(array $filters): Builder
    {
        return Requisition::query()
            ->with([
                'user:id,name,email',
                'department:id,name,code',
                'items.item:id,item_code,name,item_type,unit',
            ])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['updated_since'] ?? null, function (Builder $query, string $date) {
                $query->where('updated_at', '>=', Carbon::parse($date));
            })
            ->when($filters['updated_until'] ?? null, function (Builder $query, string $date) {
                $query->where('updated_at', '<=', Carbon::parse($date));
            })
            ->when($filters['department_code'] ?? null, function (Builder $query, string $code) {
                $query->whereHas('department', fn (Builder $department) => $department->where('code', $code));
            })
            ->orderBy('updated_at')
            ->orderBy('id');
    }

    private function transform(Requisition $requisition): array
    {
        return [
            'requisition_no' => $requisition->requisition_no,
            'status' => $requisition->status,
            'status_label' => $requisition->statusLabel(),
            'requested_at' => $requisition->requested_at?->toIso8601String(),
            'updated_at' => $requisition->updated_at?->toIso8601String(),
            'department' => [
                'code' => $requisition->department?->code,
                'name' => $requisition->department?->name,
            ],
            'requester' => [
                'name' => $requisition->user?->name,
                'email' => $requisition->user?->email,
            ],
            'purpose' => $requisition->purpose,
            'total_amount' => round((float) $requisition->items->sum(fn ($line) => (float) ($line->total_amount ?? 0)), 2),
            'lines' => $requisition->items->map(fn ($line) => [
                'item_code' => $line->item?->item_code,
                'item_name' => $line->item?->name,
                'item_type' => $line->item?->item_type,
                'unit' => $line->item?->unit,
                'quantity_requested' => (int) $line->quantity_requested,
                'quantity_approved' => $line->quantity_approved === null ? null : (int) $line->quantity_approved,
                'unit_price' => $line->unit_price === null ? null : (float) $line->unit_price,
                'line_total' => $line->total_amount === null ? null : (float) $line->total_amount,
            ])->values(),
        ];
    }

    private function audit(Request $request, string $format, array $filters, int $count): void
    {
        Log::info('ERP requisition export accessed.', [
            'format' => $format,
            'ip' => $request->ip(),
            'filters' => $filters,
            'row_count' => $count,
        ]);
    }
}
