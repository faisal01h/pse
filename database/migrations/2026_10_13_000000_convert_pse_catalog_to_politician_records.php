<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the PSE-support catalog with politician profiles and sourced records.
     *
     * Existing rows documented support for a regulation. They are removed instead
     * of being relabeled as public remarks or legal cases.
     */
    public function up()
    {
        if (Schema::hasTable('psesupporters') && ! Schema::hasTable('politicians')) {
            Schema::rename('psesupporters', 'politicians');
        }

        if (Schema::hasTable('psevidences') && ! Schema::hasTable('public_records')) {
            Schema::rename('psevidences', 'public_records');
        }

        if (! Schema::hasTable('politicians') || ! Schema::hasTable('public_records')) {
            return;
        }

        $legacyCatalog = Schema::hasColumn('politicians', 'quoted_statement')
            || Schema::hasColumn('public_records', 'pse_supporter_id');

        if ($legacyCatalog) {
            DB::table('public_records')->delete();
            DB::table('politicians')->delete();
        }

        if (Schema::hasColumn('politicians', 'pictureUrl')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->renameColumn('pictureUrl', 'picture_url');
            });
        }

        if (Schema::hasColumn('politicians', 'department')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->renameColumn('department', 'institution');
            });
        }

        if (Schema::hasColumn('politicians', 'quoted_statement')) {
            Schema::table('politicians', function (Blueprint $table) {
                $table->dropColumn('quoted_statement');
            });
        }

        if (Schema::hasColumn('public_records', 'pse_supporter_id')) {
            Schema::table('public_records', function (Blueprint $table) {
                $table->renameColumn('pse_supporter_id', 'politician_id');
            });
        }

        if (Schema::hasColumn('public_records', 'url')) {
            Schema::table('public_records', function (Blueprint $table) {
                $table->renameColumn('url', 'source_url');
            });
        }

        if (Schema::hasColumn('public_records', 'type')) {
            Schema::table('public_records', function (Blueprint $table) {
                $table->renameColumn('type', 'source_type');
            });
        }

        Schema::table('public_records', function (Blueprint $table) {
            if (! Schema::hasColumn('public_records', 'category')) {
                $table->string('category')->default('statement');
            }
            if (! Schema::hasColumn('public_records', 'summary')) {
                $table->text('summary')->nullable();
            }
            if (! Schema::hasColumn('public_records', 'quote')) {
                $table->text('quote')->nullable();
            }
            if (! Schema::hasColumn('public_records', 'occurred_on')) {
                $table->date('occurred_on')->nullable();
            }
            if (! Schema::hasColumn('public_records', 'status')) {
                $table->unsignedTinyInteger('status')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // The previous catalog cannot be restored. Fresh installs use the new tables directly.
    }
};
