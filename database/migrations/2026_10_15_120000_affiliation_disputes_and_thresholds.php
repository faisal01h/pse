<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_affiliations', function (Blueprint $table) {
            $table->unsignedInteger('dispute_threshold')->default(10)->after('status');
            $table->boolean('dispute_retract_pending')->default(false)->after('dispute_threshold');
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->foreignId('business_affiliation_id')
                ->nullable()
                ->after('public_record_id')
                ->constrained('business_affiliations')
                ->cascadeOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // Rebuild disputes so public_record_id can be nullable without doctrine/dbal.
            Schema::create('disputes_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('public_record_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('business_affiliation_id')->nullable()->constrained('business_affiliations')->cascadeOnDelete();
                $table->string('tracking_code', 19)->nullable()->unique();
                $table->text('reason');
                $table->string('source_url', 2048);
                $table->string('source_type', 100);
                $table->string('status', 16)->default('pending');
                $table->text('review_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            $columns = Schema::hasColumn('disputes', 'tracking_code')
                ? 'id, public_record_id, business_affiliation_id, tracking_code, reason, source_url, source_type, status, review_note, reviewed_at, reviewed_by, created_at, updated_at'
                : 'id, public_record_id, business_affiliation_id, reason, source_url, source_type, status, review_note, reviewed_at, reviewed_by, created_at, updated_at';

            DB::statement('INSERT INTO disputes_tmp ('.$columns.') SELECT '.$columns.' FROM disputes');
            Schema::drop('disputes');
            Schema::rename('disputes_tmp', 'disputes');

            return;
        }

        Schema::table('disputes', function (Blueprint $table) {
            $table->dropForeign(['public_record_id']);
        });

        DB::statement('ALTER TABLE disputes MODIFY public_record_id BIGINT UNSIGNED NULL');

        Schema::table('disputes', function (Blueprint $table) {
            $table->foreign('public_record_id')->references('id')->on('public_records')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            if (Schema::hasColumn('disputes', 'business_affiliation_id')) {
                $table->dropConstrainedForeignId('business_affiliation_id');
            }
        });

        Schema::table('business_affiliations', function (Blueprint $table) {
            $table->dropColumn(['dispute_threshold', 'dispute_retract_pending']);
        });
    }
};
