<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Block;
use App\Transaction;
use App\Header;

use Cache;

use DB;

/**
 * @group Blocks
 *
 * Endpoints for block-based queries
 */
class BlockController extends Controller
{
    /**
     * Get all blocks
     *
     * Returns a paginated list of all blocks with corresponding headers, average block size and sum of all blocks starting with the latest one.
     *
     * @queryParam per_page Number of results per page Example: 4
     * @queryParam page The page you want to return Example: 1
     *
     */
    public function showAll(Request $request)
    {

        $paginate = $request->get('per_page', 10);
        $page = $request->has('page') ? $request->query('page') : 1;

        $blocks = Cache::remember('last-' . $paginate . '-blocks-page-' . $page, config('nkn.update-interval'), function () use ($paginate) {
            return Block::orderBy('created_at', 'desc')
                ->with(['header:block_id,height,signerPk,wallet,benificiaryWallet,created_at'])
                ->withCount('transactions')
                ->simplePaginate($paginate);
        });

        $results = Cache::remember('block-stats', config('nkn.update-interval'), function () {
            return Block::select(DB::raw("MAX(id),ROUND(AVG(size),2)"))
                ->limit(100)
                ->get();
        });

        // Create a response and modify a header value
        $response = response()->json([
            'blocks' => $blocks,
            'avgSize' => $results[0]->round,
            'sumBlocks' => $results[0]->max
        ]);

        return $response;
    }

    /**
     * Get single block by height/hash
     *
     * Returns a specific block based on the height or block hash
     *
     * @urlParam block_id Hash or height of the block Example: 1000000
     *
     */
    public function show($block_id)
    {
        if (is_numeric($block_id)) {
            $id = Cache::rememberForever('block-id-of-height-' . $block_id, function () use ($block_id) {
                return Header::where('height', $block_id)
                    ->pluck('block_id')
                    ->first();
            });
            $block = Block::where('id', $id)
                ->with(['header'])
                ->withCount('transactions')
                ->first();
        } else {
            $block = Cache::rememberForever('block-hash-' . $block_id, function () use ($block_id) {
                return Block::where('hash', $block_id)
                    ->with(['header'])
                    ->withCount('transactions')
                    ->first();
            });
        }

        return response()->json($block);
    }

    /**
     * Get transactions
     *
     * Returns a paginated list of all transactions of a specific block based on the height or block hash
     *
     * @urlParam block_id Hash or height of the block Example: 1000000
     * @queryParam per_page Number of results per page Example: 4
     * @queryParam page The page you want to return Example: 1
     *
     */
    public function showBlockTransactions($block_id, Request $request)
    {
        $paginate = $request->get('per_page', 10);
        $page = $request->has('page') ? $request->query('page') : 1;

        if (is_numeric($block_id)) {
            $id = Cache::rememberForever('block-id-of-height-' . $block_id, function () use ($block_id) {
                return Header::where('height', $block_id)
                    ->pluck('block_id')
                    ->first();
            });
        } else {
            $id = Cache::rememberForever('block-id-of-hash-' . $block_id, function () use ($block_id) {
                return Block::where('hash', $block_id)
                    ->pluck('block_id')
                    ->first();
            });
        }

        $transactions = Cache::rememberForever('last-' . $paginate . '-transactions-for-block-' . $block_id . '-page-' . $page, function () use ($paginate, $id) {
            return Transaction::where('block_id', $id)
                ->orderBy('created_at', 'desc')
                ->simplePaginate($paginate);
        });

        return response()->json($transactions);
    }
}
