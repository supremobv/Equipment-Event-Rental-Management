<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Audit
{
    public static function log(string $action, string $subject = ''): void
    {
        DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'ip' => request()->ip(), 'created_at' => now()]);
    }
}
