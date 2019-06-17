<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AuthorMembership extends Model
{
    protected $primaryKey = 'author_memberships_id';

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
    protected $fillable = ['user_id', 'subject_id', 'status', 'created_date'];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }
}
