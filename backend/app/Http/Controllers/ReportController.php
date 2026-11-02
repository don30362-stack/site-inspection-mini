<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ReportController extends Controller
{
    public function index(IndexReportRequest $request): JsonResponse
    {
        $query = Report::with('images');
        foreach ($request->validated() as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        return response()->json([
            'data' => ReportResource::collection($query->latest()->orderByDesc('id')->get()),
            'message' => '查詢成功。',
            'errors' => (object) [],
        ]);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $disk = Storage::disk('public');
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
}
