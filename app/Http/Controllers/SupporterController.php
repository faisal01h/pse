<?php
 
namespace App\Http\Controllers;
 
use App\Http\Controllers\Controller;
use App\Models\PseSupporter;
use App\Models\PSEvidence;
 
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
}