<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AddressStatistic extends Model
{
    protected $fillable = ['address','transaction_count','first_transaction','last_transaction'];

    public $timestamps = false;

}
