<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_name' => $this->site_name,
            'issue_type' => $this->issue_type,
            'severity' => $this->severity,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
