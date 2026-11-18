<?php

use App\Support\Database\MySql;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing group (PayPal): plans, subscriptions, subscription_payments, paypal_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->enum('audience', ['owner', 'agent']);
            $table->unsignedInteger('max_listings')->nullable();      // NULL = unlimited
            $table->boolean('is_featured')->default(false);
            $table->smallInteger('search_boost')->default(0);
            $table->boolean('show_direct_contact')->default(false);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedInteger('price_month_cents')->nullable();
            $table->unsignedInteger('price_year_cents')->nullable();
            $table->string('paypal_plan_id_month', 40)->nullable();
            $table->string('paypal_plan_id_year', 40)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->unsignedSmallInteger('plan_id');
            $table->enum('billing_interval', ['month', 'year']);
            $table->enum('status', ['approval_pending', 'approved', 'active', 'suspended', 'cancelled', 'expired']);
            $table->string('paypal_subscription_id', 40)->nullable()->unique();
            $table->string('paypal_payer_id', 40)->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['user_id', 'status'], 'idx_sub_user');
            $table->index(['agency_id', 'status'], 'idx_sub_agency');
            $table->foreign('user_id', 'fk_sub_user')->references('id')->on('users');
            $table->foreign('agency_id', 'fk_sub_agency')->references('id')->on('agencies');
            $table->foreign('plan_id', 'fk_sub_plan')->references('id')->on('plans');
        });
        MySql::statement('ALTER TABLE subscriptions ADD CONSTRAINT chk_sub_payer CHECK ((user_id IS NULL) <> (agency_id IS NULL))');

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->string('paypal_transaction_id', 40)->unique();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3);
            $table->enum('status', ['completed', 'pending', 'refunded', 'failed']);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subscription_id', 'paid_at'], 'idx_pay_sub');
            $table->foreign('subscription_id', 'fk_pay_sub')->references('id')->on('subscriptions');
        });

        // Idempotency log for PayPal webhooks
        Schema::create('paypal_events', function (Blueprint $table) {
            $table->string('id', 60)->primary();
            $table->string('event_type', 80);
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paypal_events');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
