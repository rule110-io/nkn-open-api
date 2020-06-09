<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;

class SyncAddressBalance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $addressStatistic;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($addressStatistic)
    {
        $this->addressStatistic = $addressStatistic;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $requestContent = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'json' => [
                "id" => 1,
                "method" => "getbalancebyaddr",
                "params" => [
                    "address" => $this->addressStatistic->address,
                ],
                "jsonrpc" => "2.0"
            ]
        ];

        try {
            $client = new GuzzleHttpClient();

            $apiRequest = $client->Post(config('nkn.remote-node.address') . ':' . config('nkn.remote-node.port'), $requestContent);
            $response = json_decode($apiRequest->getBody(), true, 512, JSON_BIGINT_AS_STRING);
            if(isset($response["result"]["amount"])){
                $this->addressStatistic->balance = floatval($response["result"]["amount"]) * 100000000;
                $this->addressStatistic->save();
            }

        } catch (RequestException $re) { }
    }

    public function tags()
    {
        return ['SyncAddressBalance', $this->addressStatistic->address];
    }
}
