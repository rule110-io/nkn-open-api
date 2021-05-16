<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Sigchain;

use Cache;

/**
 * @group Sigchains
 *
 * Endpoints for sigchain-based queries
 */

class SigchainController extends Controller
{
    /**
     * Get all sigchains
     *
     * Returns a paginated list of all sigchains with corresponding sigchain elements starting with the latest one.
     *
     * @queryParam per_page Number of results per page Example: 4
     * @queryParam page The page you want to return Example: 1
     *
     */
    public function showAll(Request $request)
    {

        $pageSize = $request->get('per_page', 10);
        $paginate = min($pageSize, 250);
        $page = $request->has('page') ? $request->query('page') : 1;

        $sigchains = Cache::remember('last-' . $paginate . '-sigchains-page-' . $page, config('nkn.update-interval'), function () use ($paginate) {
            return Sigchain::orderby('id','desc')
            ->with('sigchain_elems')
            ->simplePaginate($paginate);
        });

        return response()->json($sigchains);

    }

}
