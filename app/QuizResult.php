<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $table = 'quiz_results';

    protected $fillable = ['percentage', 'user_id','subject_id','skill_id','time_limit','used_time','timestamp'];
}
