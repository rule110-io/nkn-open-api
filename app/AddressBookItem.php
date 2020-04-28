<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AddressBookItem extends Model
{
    protected $fillable = ['name','public_key','address','expires_at'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id', 'created_at', 'updated_at'
    ];

}
