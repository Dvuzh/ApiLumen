<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccessCodeMembership extends Model
{

    protected $primaryKey = 'access_membership_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'subject_id', 'date_registered', 'access_code', 'access_code_id', 'type', 'plan', 'group_id', 'order_number', 'expiration_date'];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }
}
