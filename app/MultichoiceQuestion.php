<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MultichoiceQuestion extends Model
{
    protected $primaryKey = 'question_id';
    
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
    
    protected $table = 'multichoice_questions';

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
        'option_1',
        'option_2',
        'option_3',
        'option_4',
        'feedback',
        'answer',
        'time_limit',
        'created_date'
    ];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }
}
