<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('format', 16)->default('markdown');
                $table->longText('body');
                $table->boolean('pinned')->default(false);
                $table->timestamps();
            });
        }

        $now = now();
        $pages = [
            [
                'title' => 'Legal',
                'slug' => 'legal',
                'body' => "Halaman ini memuat informasi hukum PolitiSEE.\n\nUbah teks ini dari menu kelola halaman.",
            ],
            [
                'title' => 'Ketentuan Layanan',
                'slug' => 'terms',
                'body' => "Dengan mengirim catatan, Anda menyatakan bahwa sumber yang ditautkan bersifat publik dan dapat diperiksa.\n\nUbah teks ini dari menu kelola halaman.",
            ],
            [
                'title' => 'Kebijakan Privasi',
                'slug' => 'privacy',
                'body' => "PolitiSEE tidak meminta akun untuk mengirim catatan. Kode pelacakan hanya disimpan sebagai hash, sehingga server tidak menyimpan kode yang bisa dibaca.\n\nUbah teks ini dari menu kelola halaman.",
            ],
        ];

        foreach ($pages as $page) {
            $exists = DB::table('pages')->where('slug', $page['slug'])->exists();

            if ($exists) {
                continue;
            }

            DB::table('pages')->insert([
                'title' => $page['title'],
                'slug' => $page['slug'],
                'format' => 'markdown',
                'body' => $page['body'],
                'pinned' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
