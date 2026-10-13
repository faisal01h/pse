<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the checklist and written decision for each vetted record.
     */
    public function up()
    {
        if (! Schema::hasTable('public_records')) {
            return;
        }

        Schema::table('public_records', function (Blueprint $table) {
            if (! Schema::hasColumn('public_records', 'review_note')) {
                $table->text('review_note')->nullable();
            }
            if (! Schema::hasColumn('public_records', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
            if (! Schema::hasColumn('public_records', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('public_records', 'check_source')) {
                $table->boolean('check_source')->default(false);
            }
            if (! Schema::hasColumn('public_records', 'check_wording')) {
                $table->boolean('check_wording')->default(false);
            }
            if (! Schema::hasColumn('public_records', 'check_category')) {
                $table->boolean('check_category')->default(false);
            }
            if (! Schema::hasColumn('public_records', 'check_duplicate')) {
                $table->boolean('check_duplicate')->default(false);
            }
            if (! Schema::hasColumn('public_records', 'check_legal_label')) {
                $table->boolean('check_legal_label')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (! Schema::hasTable('public_records')) {
            return;
        }

        Schema::table('public_records', function (Blueprint $table) {
            if (Schema::hasColumn('public_records', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            $columns = array_filter([
                Schema::hasColumn('public_records', 'review_note') ? 'review_note' : null,
                Schema::hasColumn('public_records', 'reviewed_at') ? 'reviewed_at' : null,
                Schema::hasColumn('public_records', 'check_source') ? 'check_source' : null,
                Schema::hasColumn('public_records', 'check_wording') ? 'check_wording' : null,
                Schema::hasColumn('public_records', 'check_category') ? 'check_category' : null,
                Schema::hasColumn('public_records', 'check_duplicate') ? 'check_duplicate' : null,
                Schema::hasColumn('public_records', 'check_legal_label') ? 'check_legal_label' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
