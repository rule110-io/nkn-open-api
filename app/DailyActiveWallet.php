<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DailyActiveWallet extends Model
{
    protected $fillable = ['date', 'count', 'active_wallets'];

    protected $casts = [
        'date' => 'date',
        'active_wallets' => 'array'
    ];
}
