<?php

use App\Tracking\SubmissionCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('tracking_code', 19)->nullable()->unique()->after('public_record_id');
        });

        DB::table('disputes')->whereNull('tracking_code')->orderBy('id')->lazyById()->each(function ($dispute) {
            DB::table('disputes')->where('id', $dispute->id)->update([
                'tracking_code' => SubmissionCode::generate(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropUnique(['tracking_code']);
            $table->dropColumn('tracking_code');
        });
    }
};
