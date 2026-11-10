<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Also trim Unicode separators such as full-width spaces on mobile keyboards.
        foreach (['site_name', 'issue_type', 'severity', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['missing'],
            'site_name' => ['required', 'string', 'max:100'],
            'issue_type' => ['required', 'string', Rule::in(Report::ISSUE_TYPES)],
            'severity' => ['required', 'string', Rule::in(Report::SEVERITIES)],
            'description' => ['required', 'string', 'max:1000'],
            'photo' => ['prohibited'],
            'photos' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'photos.*' => [
                'bail', 'required', 'file', 'max:20480', 'image',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp',
                'dimensions:min_width=1,min_height=1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.prohibited' => '請改用 photos[] 上傳照片。',
            'photos.required' => '請至少新增一張現場照片。',
            'photos.min' => '請至少新增一張現場照片。',
            'photos.max' => '現場照片最多五張。',
            'photos.*.uploaded' => '照片上傳失敗，請確認檔案大小與伺服器上傳限制。',
            'photos.*.max' => '每張照片不得超過 20 MiB。',
            'photos.*.image' => '請提供真正的圖片檔案。',
            'photos.*.mimetypes' => '照片須為 JPEG、PNG、GIF 或 WebP；HEIC／HEIF 請由表單自動處理。',
            'photos.*.dimensions' => '無法辨識圖片內容，請重新選取照片。',
        ];
    }
}
