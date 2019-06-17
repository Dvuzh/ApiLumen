<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AuthorCode extends Model
{

    protected $primaryKey = 'author_code_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}
