<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Payload extends Model
{
    protected $fillable = ['payloadType', 'sender', 'senderWallet', 'recipientWallet', 'recipient', 'amount', 'submitter', 'registrant', 'registrantWallet', 'name', 'subscriber', 'identifier', 'topic', 'bucket', 'duration', 'meta', 'public_key', 'registration_fee', 'nonce', 'txn_expiration', 'signerPk', 'symbol', 'total_supply', 'precision', 'nano_pay_expiration', 'created_at'];
    public $timestamps = false;


    public function transaction()
    {
        return $this->belongsTo('App\Transaction');
    }
    public function sigchain()
    {
        return $this->hasOne('App\Sigchain');
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
