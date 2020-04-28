<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\AddressBookItem;
use App\Jobs\DeleteAddressBookItem;

class RemoveExpiredAddressBookItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'address-book:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans up all expired address book items';

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
        $addressBookItems = AddressBookItem::where([
            ['expires_at', '<', Carbon::now()]
        ])
            ->get();
        foreach ($addressBookItems as $addressBookItem) {
            DeleteAddressBookItem::dispatch($addressBookItem);
        }
    }
}
