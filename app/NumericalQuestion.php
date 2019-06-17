<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NumericalQuestion extends Model
{
    protected $primaryKey = 'question_id';
    
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $table = 'numerical_questions';

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
