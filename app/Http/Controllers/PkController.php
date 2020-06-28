<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Header;

use Cache;
use DB;

/**
 * @group Sigchains
 *
 * Endpoints for sigchain-based queries
 */

class PkController extends Controller
{
    /**
     * Count blocks signed by public key
     *
     * Returns the count of signed blocks on a day by a specific public key.
     *
     * @urlParam pk required The public key. Example: 34691fa28d3e261f4f31639ef376365bc57b6583e59224006a568b76f14b947f
     * @queryParam date Date you want to display (Default today) : 2020-06-01
     *
     */
    public function countBlocks($pk, Request $request)
    {
        $date = $request->get('date', date("Y-m-d"));

        $data = Cache::remember('signed-blocks-' . $pk . '-date-' . $date, config('nkn.update-interval'), function () use ($pk,$date) {
            return Header::select(DB::raw("count(*)"))->where('signerPk',$pk)
            ->whereRaw('"created_at" > \''. $date .'\'::date')
            ->whereRaw('"created_at" < \''. $date .'\'::date +1')
            ->get();
        });
        return response()->json($data[0]['count']);

    }

}
