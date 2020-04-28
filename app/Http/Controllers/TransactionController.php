<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Block;
use App\Transaction;
use App\Payload;
use App\Sigchain;

use Cache;

use DB;

/**
 * @group Transactions
 *
 * Endpoints for querying transactions
 */
class TransactionController extends Controller
{
    /**
     * Get all transactions
     *
     * Returns all transactions with corresponding block, payloads, outputs, inputs and attributes in simple pagination format starting with the latest one
     *
     * @queryParam per_page Number of results per page Example:4
     * @queryParam txType Filter results by txType - single or comma separated. No-example
     *
     */
    public function showAll(Request $request)
    {
        // TODO: If txType Sigchain then also add sigchain_elems
        $txType = $request->get('txType');
        $txType = explode(',', $txType);
        $paginate = $request->get('per_page', 10);
        $page = $request->has('page') ? $request->query('page') : 1;

        if ($txType[0]) {
            $transactions = Transaction::select('hash', 'block_height', 'txType', 'created_at')->whereIn("txType", $txType)
                ->orderBy('created_at', 'desc')->simplePaginate($paginate);
        } else {
            $transactions = Cache::remember('last-' . $paginate . '-transactions-page-' . $page, config('nkn.update-interval'), function () use ($paginate) {
                return Transaction::select('hash', 'block_height', 'txType', 'created_at')
                    ->orderBy('created_at', 'desc')->simplePaginate($paginate);
            });
        };


        $transactionCount = Transaction::select(DB::raw("max(id) as id"))->pluck('id');
        $blockCount = Block::select(DB::raw("max(id) as id"))->pluck('id');


        // Create a response and modify a header value
        $response = response()->json([
            'transactions' => $transactions,
            'avgSize' => round($transactionCount[0] / $blockCount[0], 2),
            'sumTransactions' => $transactionCount[0]
        ]);

        return $response;
    }

    /**
     * Get single transaction by hash
     *
     * Returns a specific block based on the height or block hash
     *
     * @urlParam tHash Hash of the Transaction Example: dc5a95f9739ee386f4179bb463846532608efb82db1e504b64ff3b718cc58572
     *
     */
    public function show($tHash)
    {
        $transactions_query = Transaction::query()->where('hash', $tHash);
        $transactions = $transactions_query
            ->first();

        return response()->json($transactions);
    }


    /**
     * Get Transaction payload
     *
     * Returns the payload data of a transaction
     *
     * @urlParam tHash Hash of the Transaction Example: dc5a95f9739ee386f4179bb463846532608efb82db1e504b64ff3b718cc58572
     *
     */
    public function showTransactionPayload($tHash)
    {
        $id = Cache::rememberForever('transaction-id-of-hash-' . $tHash, function () use ($tHash) {
            return Transaction::where('hash', $tHash)
                ->pluck('id')
                ->first();
        });

        $payload = Payload::where('transaction_id', $id)
            ->first();
        if ($payload->payloadType == "SIG_CHAIN_TXN_TYPE") {
            $sigChain = Sigchain::where('payload_id', $payload->id)
                ->with('sigchain_elems')
                ->first();
            $payload->sigchain = $sigChain;
        }
        return response()->json($payload);
    }
}
