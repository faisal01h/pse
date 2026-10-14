<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\SciPol\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class HubController extends Controller
{
    public function __invoke(Workspace $workspace): Response
    {
        return Inertia::render('SciPol/Index', [
            'codebook' => $workspace->codebook(),
            'tools' => [
                [
                    'href' => route('scipol.compare'),
                    'title' => 'Bandingkan',
                    'blurb' => 'Matriks 2–3 tokoh atau partai: sumbu, sikap isu, tautan, afiliasi, dan hitungan rekaman.',
                ],
                [
                    'href' => route('scipol.issues.index'),
                    'title' => 'Peta isu',
                    'blurb' => 'Crosstab aktor menurut dukung / tolak / campuran pada isu bernama.',
                ],
                [
                    'href' => route('scipol.affiliations'),
                    'title' => 'Jejaring afiliasi',
                    'blurb' => 'Kepemilikan dan endorsemen bisnis yang sudah diterbitkan, bisa disaring sektor.',
                ],
                [
                    'href' => route('scipol.topics'),
                    'title' => 'Arsip topik',
                    'blurb' => 'Korpus rekaman terbit menurut topik, kategori, dan kata kunci.',
                ],
                [
                    'href' => route('scipol.practice'),
                    'title' => 'Latihan sumber',
                    'blurb' => 'Checklist membaca sumber lalu mengajukan atau menyanggah data.',
                ],
            ],
        ]);
    }
}
