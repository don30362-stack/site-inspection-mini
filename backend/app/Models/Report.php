<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['site_name', 'issue_type', 'severity', 'description', 'image_path'])]
class Report extends Model
{
    //
}
