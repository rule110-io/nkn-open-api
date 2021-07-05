<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Transaction;
use App\Header;
use App\AddressBookItem;
use App\AddressStatistic;

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

        $pageSize = $request->get('per_page', 10);
        $paginate = min($pageSize, 250);

        $page = $request->has('page') ? $request->query('page') : 1;

        $addresses = Cache::remember('last-' . $paginate . '-addresses-page-' . $page, config('nkn.update-interval'), function () use ($paginate) {
            return AddressStatistic::select('address', 'transaction_count as count_transactions', 'first_transaction', 'last_transaction', 'balance')->orderBy('last_transaction', 'desc')->simplePaginate($paginate);
        });
        $count = Cache::remember('sumAddresses', config('nkn.update-interval'), function (){
            return DB::table('address_statistics')->count();
        });
        dd($count[0]->count);
        // Create a response and modify a header value
        $response = response()->json([
            'addresses' => $addresses,
            'sumAddresses' => $count
        ]);

        return $response;
    }

    /**
     * Get single address by walletAddr
     *
     * Returns a specific address based on the wallet address
     *
     * @urlParam address required The wallet address. Example: NKNXXXXXGKct2cZuhSGW6xqiqeFVd5nJtAzg
     *
     */
    public function show($address)
    {
        $payload = Cache::remember('address-' . $address, config('nkn.update-interval'), function () use ($address) {
            return AddressStatistic::select('address', 'transaction_count as count_transactions', 'first_transaction', 'last_transaction','balance')->where('address', $address)->first();
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
            return response()->json($payload);
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
        $page = $request->has('page') ? $request->query('page') : 1;
        $pageSize = $request->get('per_page', 10);
        $paginate = min($pageSize, 250);
        $txType = $request->get('txType');
        $txType = explode(',', $txType);

        if ($txType[0]) {
            $transactions = Transaction::whereIn("txType", $txType)
                    ->whereHas('payload', function ($query) use ($address) {
                        $query->where('senderWallet', $address)
                            ->orWhere('recipientWallet', $address)
                            ->orWhere('registrantWallet', $address)
                            ->orWhere('generateWallet', $address)
                            ->orWhere('subscriberWallet', $address);
                    })
                    ->with(['payload','programs','payload.sigchain','payload.sigchain.sigchain_elems'])
                    ->orderBy('block_id', 'desc')
                    ->simplePaginate($paginate);
        } else {
            $transactions = Cache::remember('last-' . $paginate . '-transactions-for-address-'.$address.'-page-' . $page, config('nkn.update-interval'), function () use ($paginate, $address) {
                return Transaction::whereHas('payload', function ($query) use ($address) {
                    $query->where('senderWallet', $address)
                        ->orWhere('recipientWallet', $address)
                        ->orWhere('registrantWallet', $address)
                        ->orWhere('generateWallet', $address)
                        ->orWhere('subscriberWallet', $address);
                })
                    ->with(['payload','programs','payload.sigchain','payload.sigchain.sigchain_elems'])
                    ->orderBy('block_id', 'desc')
                    ->simplePaginate($paginate);
            });
        };



        return response()->json($transactions);
    }

    /**
     * Mined to Address Check
     *
     * Checks if an address has already mined to a beneficiaryWallet
     *
     * @urlParam address required The wallet address. Example: NKNXXXXXGKct2cZuhSGW6xqiqeFVd5nJtAzg
     * @urlParam beneficiaryAddress required The wallet address. Example: NKNXXXXXGKct2cZuhSGW6xqiqeFVd5nJtAzg
     *
     */

    public function hasMinedToAddress($address,$beneficiaryAddress)
    {
        $donation = Header::where('wallet', $address)->where('benificiaryWallet', $beneficiaryAddress)->first();

        if ($donation) {
            return response(null, 202);
        } else {
            return response(null, 200);
        }
    }
}
