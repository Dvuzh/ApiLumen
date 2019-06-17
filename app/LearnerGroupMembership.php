<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LearnerGroupMembership extends Model
{
    protected $primaryKey = 'group_membership_id';

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
    protected $fillable = ['group_id', 'user_id', 'subject_id', 'created_date'];

}