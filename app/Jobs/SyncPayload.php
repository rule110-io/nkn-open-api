<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;

use App\Helpers\PubKey2Wallet;
use App\AddressStatistic;

class SyncPayload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $payload;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        switch ($payload->payloadType) {
            case "SUBSCRIBE_TYPE":
                $subscriberWallet = PubKey2Wallet::encode($this->payload->subscriber);
                $this->payload->subscriberWallet = $subscriberWallet;

                $subscriberAddressStatistic = AddressStatistic::where('address',$subscriberWallet);
                if($subscriberAddressStatistic){
                    $subscriberAddressStatistic->transaction_count = ($subscriberAddressStatistic->transaction_count + 1);
                    $subscriberAddressStatistic->save();
                }
            break;
            case "UNSUBSCRIBE_TYPE":
                $subscriberWallet = PubKey2Wallet::encode($this->payload->subscriber);
                $this->payload->subscriberWallet = $subscriberWallet;

                $subscriberAddressStatistic = AddressStatistic::where('address',$subscriberWallet);
                if($subscriberAddressStatistic){
                    $subscriberAddressStatistic->transaction_count = ($subscriberAddressStatistic->transaction_count + 1);
                    $subscriberAddressStatistic->save();
                }
            break;
            case "GENERATE_ID_TYPE":
                $generateWallet = PubKey2Wallet::encode($this->payload->public_key);
                $this->payload->generateWallet = $generateWallet;

                $generateAddressStatistic = AddressStatistic::where('address',$generateWallet);
                if($generateAddressStatistic){
                    $generateAddressStatistic->transaction_count = ($generateAddressStatistic->transaction_count + 1);
                    $generateAddressStatistic->save();
                }
            break;
        }

        $this->payload->save();
    }

    public function tags()
    {
        return ['SyncPayload', $this->payload->id];
    }
}
