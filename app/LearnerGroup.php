<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LearnerGroup extends Model
{
    protected $primaryKey = 'group_id';

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
    protected $fillable = ['group_name', 'subject_id', 'limit', 'group_access_code', 'user_id', 'created_date'];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }

    public function learnerGroupMemberships()
    {
        return $this->hasMany('App\LearnerGroupMembership', 'group_id', 'group_id');
    }
}
