<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->uuid('request_key')->nullable()->unique());
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropUnique(['request_key']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('request_key'));
    }
};
