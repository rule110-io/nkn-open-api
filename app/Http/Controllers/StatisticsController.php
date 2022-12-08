<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\User;
use App\Node;
use App\Wallet;
use App\CrawledNode;
use App\Transaction;
use App\Header;
use App\AddressStatistic;

use Cache;
use DB;


/**
 * @group Statistics
 *
 * Endpoints for overall statistics queries
 */
class StatisticsController extends Controller
{
    /**
     * Blocks per Day
     *
     * Returns block count for the last 14 days.
     *
     */
    public function dailyBlocks()
    {
        $rawQuery = "SELECT date,COALESCE(count, 0) AS count ";
        $rawQuery .= "FROM  ( ";
        $rawQuery .= "    SELECT date::date ";
        $rawQuery .= "    FROM   generate_series(now()-'14 days'::interval ";
        $rawQuery .= "                         , now() ";
        $rawQuery .= "                         , interval  '1 day') date ";
        $rawQuery .= "    ) d ";
        $rawQuery .= " LEFT   JOIN ( ";
        $rawQuery .= "    SELECT created_at::date AS date ";
        $rawQuery .= "         , count(*) AS count ";
        $rawQuery .= "    FROM   blocks ";
        $rawQuery .= "    WHERE  created_at >= now()-'14 days'::interval ";
        $rawQuery .= "    AND    created_at <= now() ";
        $rawQuery .= "    GROUP  BY 1 ";
        $rawQuery .= "    ) t USING (date) ";
        $rawQuery .= " ORDER  BY date;";

        $result = Cache::remember('dailyBlocks', config('nkn.update-interval'), function () use ($rawQuery) {
            return DB::select(DB::raw($rawQuery));
        });

        return response()->json($result);
    }

    /**
     * Transactions per day
     *
     * Returns transaction count for the last 14 days.
     *
     */
    public function dailyTransactions()
    {
        $rawQuery = "SELECT date,COALESCE(count, 0) AS count ";
        $rawQuery .= "FROM  ( ";
        $rawQuery .= "    SELECT date::date ";
        $rawQuery .= "    FROM   generate_series(now()-'14 days'::interval ";
        $rawQuery .= "                         , now() ";
        $rawQuery .= "                         , interval  '1 day') date ";
        $rawQuery .= "    ) d ";
        $rawQuery .= " LEFT   JOIN ( ";
        $rawQuery .= "    SELECT created_at::date AS date ";
        $rawQuery .= "         , count(*) AS count ";
        $rawQuery .= "    FROM   transactions ";
        $rawQuery .= "    WHERE  created_at >= now()-'14 days'::interval ";
        $rawQuery .= "    AND    created_at <= now() ";
        $rawQuery .= "    GROUP  BY 1 ";
        $rawQuery .= "    ) t USING (date) ";
        $rawQuery .= " ORDER  BY date;";

        $result = Cache::remember('dailyTransactions', config('nkn.update-interval'), function () use ($rawQuery) {
            return DB::select(DB::raw($rawQuery));
        });

        return response()->json($result);
    }

    /**
     * Average transaction fee
     *
     * Returns the average transaction fee of the latest 200 transactions.
     *
     */

    public function avgTxFee()
    {
        $rawQuery = "SELECT avg(s.fee) as avgfee ";
        $rawQuery .= "FROM ( SELECT fee FROM transactions ORDER BY created_at DESC LIMIT 200) as s;";

        $tx = Cache::remember('avgTxFee', 600, function ()  use ($rawQuery) {
            return DB::select(DB::raw($rawQuery));
        });

        return response()->json($tx[0]->avgfee/100000000);
    }

    /**
     * Number of Blocks/Transactions
     *
     * Returns the number of blocks and transactions currently stored in the database
     *
     */

    public function countBlocksTransactions()
    {
        $rawQuery = "SELECT reltuples AS COUNT FROM row_counts WHERE relname='transactions';";
        $txs = Cache::remember('count-tx-qry', config('nkn.update-interval'), function () use ($rawQuery) {
            return DB::select(DB::raw($rawQuery));
        });
        $blocks  = Cache::remember('count-block-qry', config('nkn.update-interval'), function () {
            return Header::select(DB::raw("MAX(height)"))
                ->get();
        });
        $rawQuery = "SELECT reltuples AS COUNT FROM row_counts WHERE relname='address_statistics';";
        $txs2 = Cache::remember('sumAddresses', config('nkn.update-interval'), function () use ($rawQuery) {
            return AddressStatistic::select(DB::raw("MAX(id)"))
                ->get();
        });

        // Create a response and modify a header value
        $response = response()->json([
            'blockCount' => $blocks[0]->max,
            'txCount' => $txs[0]->count,
            'addressCount' => $txs2[0]->max,
        ]);

        return $response;
    }

    /**
     * Supplies
     *
     * Calculates the current supply data of NKN tokens
     *
     */

    public function getSupply(Request $request)
    {
        $result = Cache::remember('supply_data', config('nkn.update-interval'), function () {
            return AddressStatistic::select(
                DB::raw('sum(balance)::BIGINT as total_supply'),
                DB::raw('sum(balance) FILTER (WHERE address != \'NKNFCrUMFPkSeDRMG2ME21hD6wBCA2poc347\')::BIGINT as circulating_supply')
            )
                ->get();
        });

        if($request->get('q') == 'totalsupply'){
            return response()->json($result[0]->total_supply/100000000);
        }

        // Create a response and modify a header value
        $response = response()->json([
            'max_supply' => 100000000000000000/100000000,
            'total_supply' => $result[0]->total_supply/100000000,
            'circulating_supply' => $result[0]->circulating_supply/100000000
        ]);

        return $response;
    }

}
