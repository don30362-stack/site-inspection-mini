<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['site_name', 'issue_type', 'severity', 'description', 'image_path'])]
class Report extends Model
{
    public const ISSUE_TYPES = ['系統櫃安裝', '水電', '裝修', '安全防護', '其他'];

    public const SEVERITIES = ['low', 'medium', 'high'];
}
