<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('reports:admin-password-hash', function () {
    $password = $this->secret('管理密碼（至少 12 字元）');
    if (! is_string($password) || mb_strlen($password) < 12 || strlen($password) > 72 || trim($password) === '') {
        $this->error('請使用至少 12 字元且不超過 72 位元組的非空白密碼。');

        return 1;
    }
    if ($this->secret('再次輸入管理密碼') !== $password) {
        $this->error('兩次輸入不一致。');

        return 1;
    }
    $this->line(Hash::make($password));

    return 0;
})->purpose('互動輸入管理密碼並產生伺服器端雜湊，不修改 .env');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
