<?php

namespace App\Http\Controllers;

use App\Actions\DeleteReport;
use App\Http\Requests\DeleteReportRequest;
use App\Http\Requests\IndexReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportStatusRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Support\ReportPhotoQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ReportController extends Controller
{
    public function destroy(DeleteReportRequest $request, Report $report, DeleteReport $delete): JsonResponse
    {
        $hash = config('inspection.admin_password_hash');
        try {
            if (! is_string($hash) || ! Hash::isHashed($hash)) {
                throw new RuntimeException('Management password is not configured.');
            }
            $valid = Hash::check($request->validated('admin_password'), $hash);
        } catch (RuntimeException) {
            return response()->json(['data' => null, 'message' => '管理密碼尚未正確設定，刪除功能暫停使用。', 'errors' => (object) []], 503);
        }
        if (! $valid) {
            return response()->json(['data' => null, 'message' => '管理密碼錯誤。', 'errors' => ['admin_password' => ['管理密碼錯誤。']]], 403);
        }
        if (! $delete->handle($report->id)) {
            return response()->json(['data' => null, 'message' => '紀錄已移除，但照片備份清理未完成。請勿重複刪除，請聯絡管理者。', 'errors' => (object) []], 500);
        }

        return response()->json(['data' => null, 'message' => '巡檢紀錄與照片已刪除。', 'errors' => (object) []]);
    }

    public function index(IndexReportRequest $request): JsonResponse
    {
        $query = Report::with('images');
        foreach ($request->safe()->only(['site_name', 'issue_type', 'severity', 'status']) as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        $keyword = $request->validated('keyword');
        if ($keyword !== null) {
            if (str_contains($keyword, "\0")) {
                // SQLite LIKE stops at NUL; instr preserves literal substring matching.
                $query->whereRaw('instr(lower(description), lower(?)) > 0', [$keyword]);
            } else {
                $pattern = '%'.strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->whereRaw("(description LIKE ? ESCAPE '!' OR (instr(description, char(0)) > 0 AND instr(lower(description), lower(?)) > 0))", [$pattern, $keyword]);
            }
        }

        $reports = $query->latest()->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => ReportResource::collection($reports->getCollection()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
                'from' => $reports->firstItem(),
                'to' => $reports->lastItem(),
            ],
            'message' => '查詢成功。',
            'errors' => (object) [],
        ]);
    }

    public function store(StoreReportRequest $request, ReportPhotoQuota $quota): JsonResponse
    {
        return $quota->synchronized(function () use ($request, $quota) {
            $disk = Storage::disk('public');
            $quota->ensureCapacity($disk, $request->file('photos'));
            $paths = [];

            try {
                $report = DB::transaction(function () use ($request, $disk, &$paths) {
                    $report = Report::create($request->safe()->except(['photos', 'photo']));
                    foreach ($request->file('photos') as $index => $photo) {
                        // Register before writing so partial writes are included in cleanup.
                        $filename = Str::uuid().'.'.$photo->extension();
                        $path = 'reports/'.$filename;
                        $paths[] = $path;
                        if ($disk->putFileAs('reports', $photo, $filename) !== $path) {
                            throw new RuntimeException('Unable to store report photo.');
                        }
                        $report->images()->create(['image_path' => $path, 'sort_order' => $index]);
                    }

                    return $report->load('images');
                });
            } catch (Throwable $exception) {
                // Try every path even if deletion of one path fails.
                $cleanupFailed = false;
                foreach ($paths as $path) {
                    try {
                        $cleanupFailed = ! $disk->delete($path) || $cleanupFailed;
                    } catch (Throwable $cleanupException) {
                        report($cleanupException);
                        $cleanupFailed = true;
                    }
                }
                if ($cleanupFailed) {
                    throw new RuntimeException('Unable to clean up all failed report photos.', previous: $exception);
                }

                throw $exception;
            }

            return response()->json([
                'data' => new ReportResource($report),
                'message' => '巡檢紀錄已建立。',
                'errors' => (object) [],
            ], 201);
        });
    }

    public function show(Report $report): JsonResponse
    {
        $report->load('images');

        return response()->json([
            'data' => new ReportResource($report),
            'message' => '查詢成功。',
            'errors' => (object) [],
        ]);
    }

    public function updateStatus(UpdateReportStatusRequest $request, Report $report): JsonResponse
    {
        $report->status = $request->validated('status');
        $report->save();
        $report->load('images');

        return response()->json([
            'data' => new ReportResource($report),
            'message' => '處理狀態已更新。',
            'errors' => (object) [],
        ]);
    }
}
