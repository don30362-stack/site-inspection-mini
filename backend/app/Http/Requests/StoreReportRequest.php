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
            'site_name' => ['required', 'string', 'max:100'],
            'issue_type' => ['required', 'string', Rule::in(Report::ISSUE_TYPES)],
            'severity' => ['required', 'string', Rule::in(Report::SEVERITIES)],
            'description' => ['required', 'string', 'max:1000'],
            'photo' => [
                'bail', 'required', 'file', 'max:20480', 'image',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp',
                'dimensions:min_width=1,min_height=1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => '請拍照或選取照片。',
            'photo.uploaded' => '照片上傳失敗，請確認檔案大小與伺服器上傳限制。',
            'photo.max' => '照片不得超過 20 MiB。',
            'photo.image' => '請提供真正的圖片檔案。',
            'photo.mimetypes' => '目前支援 JPEG、PNG、GIF、WebP；HEIC／HEIF 自動轉換尚未提供。',
            'photo.dimensions' => '無法辨識圖片內容，請重新選取照片。',
        ];
    }
}
