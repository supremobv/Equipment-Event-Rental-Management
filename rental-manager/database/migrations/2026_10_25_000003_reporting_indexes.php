<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->index(['paid_on', 'id']));
        Schema::table('bookings', fn (Blueprint $t) => $t->index(['status', 'starts_on', 'ends_on']));
        Schema::table('maintenance', fn (Blueprint $t) => $t->index(['equipment_id', 'completed_at']));
    }

    public function down(): void
    {
        // MySQL may replace the automatic single-column FK index with our
        // composite index. Restore a supporting index before removing it.
        if (! Schema::hasIndex('maintenance', ['equipment_id'])) {
            Schema::table('maintenance', fn (Blueprint $t) => $t->index('equipment_id', 'maintenance_equipment_id_restore_index'));
        }
        Schema::table('payments', fn (Blueprint $t) => $t->dropIndex(['paid_on', 'id']));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropIndex(['status', 'starts_on', 'ends_on']));
        Schema::table('maintenance', fn (Blueprint $t) => $t->dropIndex(['equipment_id', 'completed_at']));
    }
};
