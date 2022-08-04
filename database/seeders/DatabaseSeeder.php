<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PseSupporter;
use App\Models\PSEvidence;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        PseSupporter::create([
            'name' => 'Johnny Gerard Plate',
            'pictureUrl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/Menteri_Komunikasi_dan_Informatika%2C_Johnny_G._Plate.jpg/330px-Menteri_Komunikasi_dan_Informatika%2C_Johnny_G._Plate.jpg',
            'position' => 'Menteri',
            'department' => 'Kementerian Komunikasi dan Informatika',
            'quoted_statement' => 'Tugas pemerintah adalah menegakkan perundang-undangan di RI, UU mengamanatkan segenap PSE yang beroperasi di dalam negeri, baik PSE lokal mapun global perlu melakukan pendaftaran.',
            'party' => 'Nasdem',
            'status' => 1,
        ]);

        PseSupporter::create([
            'name' => 'Sandiaga Salahuddin Uno',
            'pictureUrl' => 'https://upload.wikimedia.org/wikipedia/commons/6/6f/Sandiaga_Uno%2C_Menteri_Pariwisata_dan_Ekonomi_Kreatif.png',
            'position' => 'Menteri',
            'department' => 'Kementerian Pariwisata dan Ekonomi Kreatif',
            'quoted_statement' => 'Kami mendukung penuh langkah tegas @kemkominfo untuk melakukan pemblokiran beberapa platform dan aplikasi digital luar negeri yang tidak mau melakukan pendaftaran dalam Penyelenggara Sistem Elektronik (PSE).',
            'party' => 'independen',
            'status' => 1,
        ]);

        PseSupporter::create([
            'name' => 'Tubagus Hasanuddin',
            'pictureUrl' => 'https://img-s-msn-com.akamaized.net/tenant/amp/entityid/AA10atmB.img?w=750&h=500&m=6&x=401&y=174&s=60&d=60',
            'position' => 'Anggota Komisi I DPR RI Fraksi PDI-P',
            'department' => 'Dewan Perwakilan Rakyat',
            'quoted_statement' => 'Apa yang dilakukan oleh Kominfo terkait pendaftaran PSE sudah sesuai koridor peraturan perundang-undangan yaitu Peraturan Pemerintah (PP) Nomor 5 Tahun 2021 tentang Penyelenggaraan Perizinan Berusaha Berbasis Risiko.',
            'party' => 'PDI-P',
            'status' => 1,
        ]);

        PseSupporter::create([
            'name' => 'Dave Akbarshah Fikarno',
            'pictureUrl' => null,
            'position' => 'Anggota Komisi I DPR RI Fraksi Golkar',
            'department' => 'Dewan Perwakilan Rakyat',
            'quoted_statement' => 'Pemblokiran terhadap PSE yang tidak terdaftar itu merupakan suatu hak kewajiban dari Kominfo, karena ini merupakan penegakan aturan berdasar UU.',
            'party' => 'Golkar',
            'status' => 1,
        ]);

        PseSupporter::create([
            'name' => 'Semuel Abrijani Pangerapan',
            'pictureUrl' => 'https://media-exp1.licdn.com/dms/image/C4D03AQFrKVfYLWmzzw/profile-displayphoto-shrink_400_400/0/1632456239193?e=1665014400&v=beta&t=KyGWPkHu-64iCPXM9xyw-6FkTAprCDTWOkMFDEXem3Q',
            'position' => 'Direktur Jenderal Aplikasi Informatika',
            'department' => 'Kementerian Komunikasi dan Informatika',
            'quoted_statement' => 'Kalau mereka gak mendaftar, itu ruginya mereka sendiri. Berarti tidak melihat bahwa Indonesia sebagai potensial marketnya. Kalau mereka gak daftar juga, itu justru memberikan peluang bagi anak bangsa untuk memenuhi kebutuhan masyarakat. Intinya kita tegas, ini adalah tata kelola, bukan pengendalian supaya kita tahu siapa saja yang beroperasi di Indonesia.',
            'party' => '',
            'status' => 1,
        ]);

        PseSupporter::create([
            'name' => 'Taufiq R. Abdullah',
            'pictureUrl' => null,
            'position' => 'Anggota Komisi I DPR RI Fraksi PKB',
            'department' => 'Dewan Perwakilan Rakyat',
            'quoted_statement' => 'Prinsipnya, pendaftaran itu sebuah keniscayaan. Negara berhak mengatur mereka, terutama demi melindungi masyarakat dari banyak hal. Misalnya penyebaran informasi yang membahayakan seperti terorisme, perjudian, pornografi, hoaks, penipuan dan sebagainya.',
            'party' => 'PKB',
            'status' => 1,
        ]);


        //Evidence
        PSEvidence::create([
            'pse_supporter_id' => 1,
            'url' => 'https://web.archive.org/web/20220801061818/https://www.kompas.tv/article/314654/full-menkominfo-johnny-plate-jawab-polemik-blokir-paypal-dan-steam-bahas-urgensi-daftar-pse',
            'type' => 'berita'
        ]);

        PSEvidence::create([
            'pse_supporter_id' => 2,
            'url' => 'https://twitter.com/sandiuno/status/1553304444263022592',
            'type' => 'tweet'
        ]);

        PSEvidence::create([
            'pse_supporter_id' => 3,
            'url' => 'https://www.msn.com/id-id/berita/nasional/legislator-pdi-p-sebut-kominfo-blokir-sejumlah-platform-untuk-melindungi-kepentingan-masyarakat/ar-AA10aKkC',
            'type' => 'berita'
        ]);

        PSEvidence::create([
            'pse_supporter_id' => 4,
            'url' => 'https://video.kompas.com/watch/165517/anggota-komisi-i-dpr-anggap-pemblokiran-oleh-kominfo-sudah-tepat',
            'type' => 'berita'
        ]);

        PSEvidence::create([
            'pse_supporter_id' => 5,
            'url' => 'https://id.berita.yahoo.com/dirjen-aptika-pendaftaran-pse-lingkup-083152581.html',
            'type' => 'berita'
        ]);

        PSEvidence::create([
            'pse_supporter_id' => 6,
            'url' => 'https://web.archive.org/web/20220801191831/https://warta.co.id/taufiq-abdullah-dukung-pemblokiran-steam-yang-tidak-mendaftarkan-diri-sebagai-pse',
            'type' => 'berita'
        ]);

        
    }
}
