<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $primaryKey = 'user_id';
    public $incrementing = false;

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
    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'email', 'created_at', 'limit'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    public function subjects()
    {
        return $this->hasMany('App\Subject', 'user_id', 'user_id');
    }

    public function getActiveSubjectsArray()
    {
        $userId = $this->user_id;
        
        return Subject::select(
            'subjects.subject_id',
            'subjects.internal_subject_name',
            'subjects.subject_name',
            'subjects.subject_tag',
            'subjects.subject_icon_url',
            'subjects.user_id',
            'subjects.limit',
            'author_memberships.author_memberships_id',
            'author_memberships.status as author_memberships_status',
            'subjects.marketplace_status',
            'users.email as owner_email'
        )->leftJoin('author_memberships', function ($join) use ($userId) {
            $join->on('subjects.subject_id', '=', 'author_memberships.subject_id')->where('author_memberships.user_id', '=', $userId);
        })->leftJoin('users', function ($join) {
            $join->on('users.user_id', '=', 'subjects.user_id');
        })->where('subjects.status', 'active')->where(function ($query) use ($userId) {
            $query->where('subjects.user_id', $userId)->orWhere('author_memberships.user_id', $userId);
        })->groupBy('subject_id')->orderBy('internal_subject_name')->get();
    }
}
