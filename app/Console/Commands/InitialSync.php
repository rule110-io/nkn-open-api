<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;

use App\Jobs\ProcessRemoteBlock;
use App\Header;

use Carbon\Carbon;

use Queue;
use Log;

class InitialSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes database with blockchain for the first time';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $currentBlockchainHeight = 0;
        $requestContent = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'json' => [
                "id" => 1,
                "method" => "getlatestblockheight",
                "params" => [
                    "height" => 0,
                ],
                "jsonrpc" => "2.0"
            ]
        ];
        try {
            // get the latest block height from the configured remote node
            $client = new GuzzleHttpClient();
            $apiRequest = $client->Post(config('nkn.remote-node.address') . ':' . config('nkn.remote-node.port'), $requestContent);
            $response = json_decode($apiRequest->getBody());
            $currentBlockchainHeight = $response->result;
        } catch (RequestException $re) {
            Log::channel('syncWithBlockchain')->error("Can't connect to mainnet-node!");
            throw $re;
        }

        // get latest blockheight in the database
        $localBlockHeight = Header::select('height')->orderBy('height', 'desc')->first();
        if ($localBlockHeight) {
            $localBlockHeight = $localBlockHeight->height;
        } else {
            $localBlockHeight = -1;
        }

        //push only the new Blocks to the processing queue
        for ($i = $localBlockHeight + 1; $i <= $currentBlockchainHeight; $i++) {
            ProcessRemoteBlock::dispatch($i,false)->onQueue('initialBlockchainCrawler');
        }

    }
}
