<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccessCode extends Model
{

    protected $primaryKey = 'access_code_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}
