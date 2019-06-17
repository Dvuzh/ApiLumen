<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuestionResult extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $table = 'question_results';

    protected $fillable = [ 'user_id','subject_id','skill_id','question_id','type','result','time_used','time_limit','timestamp'];
}
