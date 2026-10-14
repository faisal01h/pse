<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('SciPol/Latihan', [
            'report_url' => route('reports.create'),
            'track_url' => route('reports.track'),
            'dispute_url' => route('dispute.create'),
        ]);
    }
}
