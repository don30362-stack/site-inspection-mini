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
        $query = Report::query();
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
        $photo = $request->file('photo');
        // The extension comes from detected content, never the client filename.
        $filename = Str::uuid().'.'.$photo->extension();
        $path = 'reports/'.$filename;

        try {
            $report = DB::transaction(function () use ($request, $disk, $photo, $filename, $path) {
                if ($disk->putFileAs('reports', $photo, $filename) !== $path) {
                    throw new RuntimeException('Unable to store report photo.');
                }

                return Report::create([
                    ...$request->safe()->except('photo'),
                    'image_path' => $path,
                ]);
            });
        } catch (Throwable $exception) {
            // Also remove a partial write; the unique path cannot belong to an older report.
            if (! $disk->delete($path)) {
                throw new RuntimeException('Unable to clean up failed report photo.', previous: $exception);
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
        return response()->json([
            'data' => new ReportResource($report),
            'message' => '查詢成功。',
            'errors' => (object) [],
        ]);
    }
}
