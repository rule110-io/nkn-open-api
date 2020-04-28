<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\AddressBookItem;
use Cache;

/**
 * @group Address book
 *
 * Endpoints for querying registered names in the NKN blockchain
 */
class AddressBookItemController extends Controller
{
    /**
     * Get all registered wallet names
     *
     * Returns paginated list of all registered wallet names, the registrants public key and the associated wallet address.
     *
     * @queryParam per_page Number of results per page Example: 4
     * @queryParam page The page you want to return Example: 1
     *
     */
    public function showWalletNames(Request $request)
    {
        $paginate = $request->get('per_page', 10);
        $page = $request->has('page') ? $request->query('page') : 1;

        $walletNames = Cache::remember('last-' . $paginate . '-addressBookItems-page-' . $page, config('nkn.update-interval'), function () use ($paginate) {
            return AddressBookItem::simplePaginate($paginate);
        });

        return response()->json($walletNames);
    }

    /**
     * Get names by address
     *
     * Returns the names associated to specific wallet address
     *
     * @urlParam walletAddress required The wallet address. Example: NKNXXXXXGKct2cZuhSGW6xqiqeFVd5nJtAzg
     *
     */
    public function getNameByAddress($address)
    {
        $addressBookItems = Cache::rememberForever('addressBookItem-addresses-' . $address, function () use ($address) {
            return AddressBookItem::where('address', $address)->get();
        });
        return response()->json($addressBookItems);
    }

    /**
     * Get address by name
     *
     * Returns the address of a specific name
     *
     * @urlParam walletName The wallet name. Example: ChrisT_NKNx
     *
     */
    public function getAddressByName($name)
    {
        $addressBookItem = Cache::rememberForever('addressBookItem-name-' . $name, function () use ($name) {
            return AddressBookItem::where('name', $name)->first();
        });
        return response()->json($addressBookItem);
    }
}
