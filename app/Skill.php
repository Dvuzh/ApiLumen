<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $table = 'skills';

    protected $primaryKey = 'skill_id';

     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['skill_name', 'topic_id', 'subject_id', 'order', 'status', 'published_status', 'created_date', 'user_id'];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }

    public function contents()
    {
        return $this->hasMany('App\Content', 'skill_id', 'skill_id');
    }

    public function skillResults()
    {
        return $this->hasMany('App\SkillResult', 'skill_id', 'skill_id');
    }

    public function quizResults()
    {
        return $this->hasMany('App\QuizResult', 'skill_id', 'skill_id');
    }
}
