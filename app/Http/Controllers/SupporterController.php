<?php
 
namespace App\Http\Controllers;
 
use App\Http\Controllers\Controller;
use App\Models\PseSupporter;
use App\Models\PSEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
 
class SupporterController extends Controller
{
    public function getAll() {
        $sup = PseSupporter::where('status', "1")->get();
        $evi = PSEvidence::get();
        for($i = 0; $i < count($sup); ++$i) {
            $sup[$i]["evidences"] = array();
            $k = 0;
            for($j = 0; $j < count($evi); ++$j) {
                if($sup[$i]["id"] === $evi[$j]["pse_supporter_id"]) {
                    $sup[$i]["evidences"] = $evi[$i]; // need fix for multiple evidence
                    ++$k;
                    // array_push($sup[$i]["evidences"], $evi[$j]);
                }
            }
        }
        return $sup;
    }

    public function getPendingReviewLaporan() {
        $sup = PseSupporter::where('status', "0")->get();
        $evi = PSEvidence::get();
        for($i = 0; $i < count($sup); ++$i) {
            $sup[$i]["evidences"] = array();
            $k = 0;
            for($j = 0; $j < count($evi); ++$j) {
                if($sup[$i]["id"] === $evi[$j]["pse_supporter_id"]) {
                    $sup[$i]["evidences"] = $evi[$i]; // need fix for multiple evidence
                    ++$k;
                    // array_push($sup[$i]["evidences"], $evi[$j]);
                }
            }
        }
        return $sup;
    }

    public function create(Request $request) {
        $request->validate([
            'name' => 'required|string',
            'position' => 'required|string',
            'department' => 'required|string',
            'evtype' => 'required|string',
            'link' => 'required|string',
        ]);
        $party = $request->party;
        if(strtolower($party) === "independen" || strtolower($party) === "independent") $party = strtolower($party);

        $supporterId = PseSupporter::create([
            'name' => $request->name,
            'position' => $request->position,
            'department' => $request->department,
            'party' => $party,
            'status' => 0
        ]);

        $evidenceId = PSEvidence::create([
            'pse_supporter_id' => $supporterId->id,
            'url' => $request->link,
            'type' =>$request->evtype,
        ]);

        return redirect('/');
    }

    public function approveLaporan(Request $request) {
        $request->validate([
            'supporter_id' => 'required'
        ]);
        
        $sup = PseSupporter::findOrFail(intval($request->supporter_id));
        $sup->status = 1;
        $sup->save();
        return json_encode([
            "sup" => $sup,
        ]);
    }
}