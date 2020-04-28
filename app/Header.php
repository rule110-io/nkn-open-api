<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Header extends Model
{
    protected $fillable = ['hash','version','prevBlockHash', 'transactionsRoot', 'stateRoot', 'timestamp', 'height', 'signature','randomBeacon', 'winnerHash', 'winnerType', 'signerPk', 'wallet','benificiaryWallet', 'signerId','created_at'];
    protected $hidden = ['added_at','block_id'];
    public $timestamps = false;

    public function block()
    {
    	return $this->belongsTo('App\Block');
    }
    public function program()
    {
    	return $this->hasOne('App\Program');
    }

    public function setTimestampAttribute($value)
    {
        $this->attributes['timestamp'] = Carbon::createFromTimestamp($value)->toDateTimeString();
    }
    public function setCreatedAtAttribute($value)
    {
        $this->attributes['created_at'] = Carbon::createFromTimestamp($value)->toDateTimeString();
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->added_at = $model->freshTimestamp();
        });
    }

}
