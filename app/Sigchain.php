<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Sigchain extends Model
{
    protected $fillable = ['nonce','dataSize','dataHash','blockHash','srcPubkey','srcId','destPubkey','destId','created_at'];
    public $timestamps = false;

    public function payload()
    {
    	return $this->belongsTo('App\Payload');
    }
    public function sigchain_elems()
    {
    	return $this->hasMany('App\SigchainElem');
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
