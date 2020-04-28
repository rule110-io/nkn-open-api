<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Payload;
use App\Transaction;
use App\AddressBookItem;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;

use Cache;

use DB;

/**
 * @group Addresses
 *
 * Endpoints for address-based queries
 */
class AddressController extends Controller
{
    /**
     * Get all addresses
     *
     * Returns paginated list of all addresses
     *
     * @queryParam per_page Number of results per page Example: 4
     * @queryParam page The page you want to return Example: 1
     *
     */
    public function showAll(Request $request)
    {

        $paginate = $request->get('per_page', 10);
        $page = $request->has('page') ? $request->query('page') : 1;

        $payloadsQuery = Payload::select(DB::raw("v.walletAddress as address, count(*) as count_transactions, min(created_at) as first_transaction, max(created_at) as last_transaction"))
            ->crossJoin(DB::raw("lateral (values (\"senderWallet\"), (\"recipientWallet\")) v(walletAddress)"))
            ->whereRaw('"payloadType" = \'COINBASE_TYPE\'')
            ->orWhereRaw('"payloadType" = \'TRANSFER_ASSET_TYPE\'')
            ->groupBy(DB::raw('v.walletAddress'));


        $results = Cache::remember('sumAddresses', config('nkn.update-interval'), function () use ($payloadsQuery) {
            return DB::select(DB::raw("select count(*) from (" . $payloadsQuery->toSql() . ") as addresses"));
        });

        $payload = Cache::remember('last-' . $paginate . '-addresses-page-' . $page, config('nkn.update-interval'), function () use ($paginate, $payloadsQuery) {
            return $payloadsQuery->orderBy('last_transaction', 'desc')->simplePaginate($paginate);
        });

        // Create a response and modify a header value
        $response = response()->json([
            'addresses' => $payload,
            'sumAddresses' => $results[0]->count
        ]);

        return $response;
    }

    /**
     * Get single address by height/hash
     *
     * Returns a specific block based on the height or block hash
     *
     * @urlParam address required The wallet address. Example: NKNXXXXXGKct2cZuhSGW6xqiqeFVd5nJtAzg
     *
     */
    public function show($address)
    {
        $payload = Cache::remember('address-' . $address, config('nkn.update-interval'), function () use ($address) {
            return Payload::select(DB::raw("v.walletAddress as address, count(*) as count_transactions, min(created_at) as first_transaction, max(created_at) as last_transaction"))
            ->crossJoin(DB::raw("lateral (values (\"senderWallet\"), (\"recipientWallet\"), (\"registrantWallet\")) v(walletAddress)"))
            ->whereRaw('v.walletAddress = \'' . $address . '\'')
            ->groupBy(DB::raw('v.walletAddress'))
            ->first();
        });

        $addressBookItems = Cache::rememberForever('addressBookItem-addresses-' . $address, function () use ($address) {
            return AddressBookItem::where('address', $address)->get();
        });



        if ($payload) {

            if ($addressBookItems){
                $payload["name"] = $addressBookItems->pluck("name");
            }
            else{
                $payload["name"] = "";
            }
            $requestContent = [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    "id" => 1,
                    "method" => "getbalancebyaddr",
                    "params" => [
                        "address" => $address,
                    ],
                    "jsonrpc" => "2.0"
                ]
            ];

            try {
                $client = new GuzzleHttpClient();

                $apiRequest = $client->Post('http://mainnet-seed-0006.nkn.org:30003', $requestContent);
                $response = json_decode($apiRequest->getBody(), true, 512, JSON_BIGINT_AS_STRING);
                if(isset($response["result"]["amount"])){
                    $payload->balance = $response["result"]["amount"];
                }

            } catch (RequestException $re) { }
        } else {
            return response()->json([
                'address' => $address,
                'name' => '',
                'count_transactions' => 0,
                'first_transaction' => null,
                'last_transaction' => null,
                'balance' => 0
            ]);
        }
        return response()->json($payload);
    }

    /**
     * Get Address transactions
     *
     * Returns paginated list of all transactions of an address
     *
     * @urlParam address required The wallet address. Example: NKNXXXXXGKct2cZuhSGW6xqiqeFVd5nJtAzg
     * @queryParam per_page Number of results per page Example: 4
     * @queryParam page The page you want to return Example: 1
     *
     */
    public function showAddressTransactions($address, Request $request)
    {
        // TODO: Filter by txType
        $page = $request->has('page') ? $request->query('page') : 1;
        $paginate = $request->get('per_page', 10);

        $transactions = Cache::remember('last-' . $paginate . '-transactions-for-address-'.$address.'-page-' . $page, config('nkn.update-interval'), function () use ($paginate, $address) {
            return Transaction::whereHas('payload', function ($query) use ($address) {
                $query->where('senderWallet', $address)
                    ->orWhere('recipientWallet', $address)
                    ->orWhere('registrantWallet', $address);
            })
                ->orderBy('created_at', 'desc')
                ->simplePaginate($paginate);
        });

        return response()->json($transactions);
    }
}
