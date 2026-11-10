<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
            'site_name' => ['nullable', 'string', 'max:100'],
            'issue_type' => ['nullable', 'string', Rule::in(Report::ISSUE_TYPES)],
            'severity' => ['nullable', 'string', Rule::in(Report::SEVERITIES)],
            'status' => ['nullable', 'string', Rule::in(Report::STATUSES)],
        ];
    }
}
