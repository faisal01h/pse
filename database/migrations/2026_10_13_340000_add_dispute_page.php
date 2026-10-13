<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages') || DB::table('pages')->where('slug', 'dispute')->exists()) {
            return;
        }

        $now = now();

        DB::table('pages')->insert([
            'title' => 'Dispute',
            'slug' => 'dispute',
            'format' => 'markdown',
            'body' => "Dispute adalah cara menyatakan bahwa sebuah arsip yang terbit tidak sesuai sumbernya.\n\nSertakan tautan arsip dan sumber pembanding. Ubah teks ini dari menu kelola halaman.",
            'pinned' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('pages')) {
            DB::table('pages')->where('slug', 'dispute')->delete();
        }
    }
};
