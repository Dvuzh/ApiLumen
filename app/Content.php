<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $primaryKey = 'content_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'content';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['content_item_name', 'subject_id', 'skill_id', 'order', 'type', 'status', 'published_status', 'created_date', 'user_id'];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }

    public function skill()
    {
        return $this->hasOne('App\Skill', 'skill_id', 'skill_id');
    }

    public function multichoiceQuestions()
    {
        return $this->hasMany('App\MultichoiceQuestion', 'content_id', 'content_id');
    }

    public function matchingQuestions()
    {
        return $this->hasMany('App\MatchingQuestion', 'content_id', 'content_id');
    }

    public function numericalQuestions()
    {
        return $this->hasMany('App\NumericalQuestion', 'content_id', 'content_id');
    }

    public function studyNotes()
    {
        return $this->hasMany('App\StudyNote', 'content_id', 'content_id');
    }

    public function getActiveQuestionsCount()
    {
        switch ($this->type) {
            case 'matchingQuestion':
                return $this->matchingQuestions()->where('status', 'active')->count();
            case 'multichoiceQuestion':
                return $this->multichoiceQuestions()->where('status', 'active')->count();
            case 'studyNote':
                return $this->studyNotes()->where('status', 'active')->count();
            case 'numericalQuestion':
                return $this->numericalQuestions()->where('status', 'active')->count();
        }
        return 0;
    }
}
