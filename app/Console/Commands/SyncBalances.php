<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\AddressStatistic;
use App\Jobs\SyncAddressBalance;

class SyncBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balances:sync {--limit=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the balances of all addresses in the database ith RPC response';

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
        $limit = $this->option('limit');

        //dd($limit);

        if ($limit == 0){
            AddressStatistic::whereNull('balance')->chunk(1000, function ($addressStatistics) {
                foreach ($addressStatistics as $addressStatistic) {
                    SyncAddressBalance::dispatch($addressStatistic)->onQueue('balanceSync');
                }
            });
        }
        else{
            $maxId = AddressStatistic::whereNull('balance')->orderBy('id', 'asc')->offset($limit)->limit(1)->select('id')->first()->id;
            AddressStatistic::whereNull('balance')->where('id', '<', $maxId)->chunkById(1000, function ($addressStatistics) {
                foreach ($addressStatistics as $addressStatistic) {
                    SyncAddressBalance::dispatch($addressStatistic)->onQueue('balanceSync');
                }
            });
        }

    }
}
