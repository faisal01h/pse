<?php

namespace App\Http\Controllers\SciPol;

use App\Http\Controllers\Controller;
use App\SciPol\Workspace;
use App\SciPol\XlsxExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace, XlsxExporter $exporter): BinaryFileResponse
    {
        $bundle = $workspace->exportBundle($request);
        $stamp = now()->format('Ymd-His');
        $scope = preg_replace('/[^a-z]/', '', (string) $request->query('scope', 'compare')) ?: 'compare';

        return $exporter->download($bundle, "scipol-{$scope}-{$stamp}.xlsx");
    }
}
