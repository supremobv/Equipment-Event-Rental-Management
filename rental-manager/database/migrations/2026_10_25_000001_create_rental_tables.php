<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('viewer');
            $t->boolean('active')->default(true);
            $t->boolean('must_change_password')->default(true);
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('business_name')->default('Gather Rentals');
            $t->string('tagline')->default('Great events start here.');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->string('currency', 3)->default('PHP');
            $t->unsignedInteger('tax_bps')->default(0);
            $t->string('logo_path')->nullable();
            $t->text('terms')->nullable();
            $t->timestamps();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('equipment', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('sku')->unique();
            $t->text('description')->nullable();
            $t->string('location')->nullable();
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('daily_rate');
            $t->unsignedBigInteger('replacement_cost')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('company')->nullable();
            $t->string('email')->nullable();
            $t->string('phone');
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->string('number')->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('event_name');
            $t->string('venue')->nullable();
            $t->string('fulfillment')->default('pickup');
            $t->date('starts_on')->index();
            $t->date('ends_on')->index();
            $t->string('status')->default('quote')->index();
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('delivery_fee')->default(0);
            $t->unsignedInteger('tax_bps')->default(0);
            $t->unsignedBigInteger('tax')->default(0);
            $t->unsignedBigInteger('total');
            $t->unsignedBigInteger('deposit_required')->default(0);
            $t->unsignedBigInteger('return_fee')->default(0);
            $t->text('notes')->nullable();
            $t->text('return_notes')->nullable();
            $t->timestamp('checked_out_at')->nullable();
            $t->timestamp('returned_at')->nullable();
            $t->timestamps();
        });
        Schema::create('booking_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $t->string('name');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('daily_rate');
            $t->unsignedInteger('days');
            $t->unsignedBigInteger('total');
            $t->unique(['booking_id', 'equipment_id']);
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('kind');
            $t->unsignedBigInteger('amount');
            $t->string('method');
            $t->string('reference')->nullable();
            $t->text('notes')->nullable();
            $t->date('paid_on');
            $t->timestamps();
        });
        Schema::create('maintenance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->string('reason');
            $t->text('notes')->nullable();
            $t->date('due_on')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'maintenance', 'payments', 'booking_items', 'bookings', 'customers', 'equipment', 'categories', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active', 'must_change_password']));
    }
};
