<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Transaction extends Model
{
    protected $fillable = ['hash','block_height','nonce','fee', 'attributes','txType','created_at'];
    protected $hidden = ['added_at'];

    public $timestamps = false;

    public function block()
    {
    	return $this->belongsTo('App\Block');
    }
    public function programs()
    {
    	return $this->hasMany('App\Program');
    }
    public function payload()
    {
    	return $this->hasOne('App\Payload');
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
