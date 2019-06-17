<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $primaryKey = 'subject_id';

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
    protected $fillable = ['subject_name', 'internal_subject_name', 'subject_tag', 'subject_icon_url', 'user_id', 'limit', 'status', 'marketplace_status', 'created_date'];


    public function authorMemberships()
    {
        return $this->hasMany('App\AuthorMembership', 'subject_id', 'subject_id');
    }

    public function accessCodeMemberships()
    {
        return $this->hasMany('App\AccessCodeMembership', 'subject_id', 'subject_id');
    }

    public function favourites()
    {
        return $this->hasMany('App\Favourite', 'subject_id', 'subject_id');
    }

    public function skills()
    {
        return $this->hasMany('App\Skill', 'subject_id', 'subject_id');
    }

    public function topics()
    {
        return $this->hasMany('App\Topic', 'subject_id', 'subject_id');
    }

    public function contents()
    {
        return $this->hasMany('App\Content', 'subject_id', 'subject_id');
    }

    public function multichoiceQuestions()
    {
        return $this->hasMany('App\MultichoiceQuestion', 'subject_id', 'subject_id');
    }

    public function matchingQuestions()
    {
        return $this->hasMany('App\MatchingQuestion', 'subject_id', 'subject_id');
    }

    public function numericalQuestions()
    {
        return $this->hasMany('App\NumericalQuestion', 'subject_id', 'subject_id');
    }

    public function studyNotes()
    {
        return $this->hasMany('App\StudyNote', 'subject_id', 'subject_id');
    }

    public function learnerGroups()
    {
        return $this->hasMany('App\LearnerGroup', 'subject_id', 'subject_id');
    }


    public function learnerGroupMemberships()
    {
        return $this->hasMany('App\LearnerGroupMembership', 'subject_id', 'subject_id');
    }

    public function getAuthorMembershipByUserid($userId)
    {
        return self::authorMemberships()->where('user_id', $userId)->first();
    }

    public function checkUserPermission($userId)
    {
        $authorMembership = $this->getAuthorMembershipByUserid($userId);
        return $this->user_id === $userId || $authorMembership;
    }
}
