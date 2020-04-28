<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\User;
use App\Node;
use App\Wallet;
use App\CrawledNode;
use App\Transaction;

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
        $rawQuery .= "FROM ( SELECT fee FROM transactions WHERE \"txType\" = 'TRANSFER_ASSET_TYPE' ORDER BY created_at DESC LIMIT 200) as s;";

        $tx = Cache::remember('avgTxFee', 600, function ()  use ($rawQuery) {
            return DB::select(DB::raw($rawQuery));
        });

        return response()->json($tx[0]->avgfee/100000000);
    }

}
