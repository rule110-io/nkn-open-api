<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SigchainElem extends Model
{
    protected $fillable = ['id2','pubkey','wallet', 'nextPubkey','mining','sigAlgo','signature','vrf','proof','created_at'];
    public $timestamps = false;

    public function sigchain()
    {
    	return $this->belongsTo('App\Sigchain');
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
