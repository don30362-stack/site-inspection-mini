<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class DeleteReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        // Never accept the management password in a URL query string.
        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }

    public function rules(): array
    {
        return ['admin_password' => ['bail', 'required', 'string', 'max:72', function (string $attribute, string $value, Closure $fail) {
            // Bcrypt ignores bytes after byte 72; reject instead of comparing a truncated password.
            if (strlen($value) > 72) {
                $fail('管理密碼過長。');
            }
        }]];
    }

    public function messages(): array
    {
        return [
            'admin_password.required' => '請輸入管理密碼。',
            'admin_password.string' => '管理密碼格式不正確。',
            'admin_password.max' => '管理密碼過長。',
        ];
    }
}
