<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Payload;
use App\Jobs\SyncPayload;

class SyncPayloads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payloads:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the payload of GENERATE_ID_TYPE and SUBSCRIBE_TYPE';

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
        $payloads = Payload::where('payloadType','GENERATE_ID_TYPE')->orWhere('payloadType','SUBSCRIBE_TYPE')->orWhere('payloadType','UNSUBSCRIBE_TYPE')->get();
        Payload::chunk(1000, function ($payloads) {
            foreach ($payloads as $payload) {
                SyncPayload::dispatch($payloads)->onQueue('balanceSync');
            }
        });
    }
}
