<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Cache;



class DeleteAddressBookItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $addressBookItem;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($addressBookItem)
    {
        $this->addressBookItem = $addressBookItem;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Cache::forget('addressBookItem-addresses-' . $this->addressBookItem->address);
        Cache::forget('addressBookItem-name-' . $this->addressBookItem->name);
        $this->addressBookItem->delete();
    }
    public function tags()
    {
        return ['DeleteAddressBookItem', $this->addressBookItem->id];
    }
}
