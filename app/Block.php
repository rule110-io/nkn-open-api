<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Block extends Model
{

    protected $fillable = ['hash','size','created_at'];
    protected $hidden = ['created_at','added_at'];
    public $timestamps = false;

    public function header()
    {
    	return $this->hasOne('App\Header');
    }
    public function transactions()
    {
    	return $this->hasMany('App\Transaction');
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
