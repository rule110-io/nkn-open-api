<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Program extends Model
{
    protected $fillable = ['code','parameter','created_at'];
    public $timestamps = false;

    public function transaction()
    {
    	return $this->belongsTo('App\Transaction');
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
