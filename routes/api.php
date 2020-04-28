<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('blocks', 'BlockController@showAll');
Route::get('blocks/{block_id}', 'BlockController@show');
//get transactions of specific block
Route::get('blocks/{block_id}/transactions', 'BlockController@showBlockTransactions');

//get all transactions
Route::get('transactions', 'TransactionController@showAll');
Route::get('transactions/{tHash}', 'TransactionController@show');
//get transactions of specific block
Route::get('transactions/{tHash}/payload', 'TransactionController@showTransactionPayload');

//get all addresses
Route::get('addresses', 'AddressController@showAll');
//get specific address
Route::get('addresses/{address}', 'AddressController@show');
//get transactions of specific address
Route::get('addresses/{address}/transactions', 'AddressController@showAddressTransactions');

Route::get('statistics/daily/blocks', 'StatisticsController@dailyBlocks');
Route::get('statistics/daily/transactions', 'StatisticsController@dailyTransactions');

Route::get('statistics/avgtxfee', 'StatisticsController@avgTxFee');

Route::get('address-book', 'AddressBookItemController@showWalletNames');
Route::get('address-book/address/{walletAddress}', 'AddressBookItemController@getNameByAddress');
Route::get('address-book/name/{walletName}', 'AddressBookItemController@getAddressByName');
