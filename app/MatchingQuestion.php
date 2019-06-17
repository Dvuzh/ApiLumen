<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MatchingQuestion extends Model
{
    protected $primaryKey = 'question_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $table = 'matching_questions';

       /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = [
        'subject_id',
        'content_id',
        'user_id',
        'status',
        'published_status',
        'question_content',
        'category_a_option_1',
        'category_a_option_2',
        'category_a_option_3',
        'category_a_option_4',
        'category_b_option_1',
        'category_b_option_2',
        'category_b_option_3',
        'category_b_option_4',
        'feedback',
        'time_limit',
        'created_date'
    ];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }
}
