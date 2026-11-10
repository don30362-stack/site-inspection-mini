<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['site_name', 'issue_type', 'severity', 'description'])]
class Report extends Model
{
    public const ISSUE_TYPES = ['系統櫃安裝', '水電', '裝修', '安全防護', '其他'];

    public const SEVERITIES = ['low', 'medium', 'high'];

    public const STATUSES = ['pending', 'in_progress', 'completed', 'reviewed'];

    protected $attributes = ['status' => 'pending'];

    public function images(): HasMany
    {
        return $this->hasMany(ReportImage::class)->orderBy('sort_order');
    }
}
