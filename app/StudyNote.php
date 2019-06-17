<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StudyNote extends Model
{
    protected $primaryKey = 'question_id';
    
    /**
    * Indicates if the model should be timestamped.
    *
    * @var bool
    */
    public $timestamps = false;

    public $feedback = '';

    protected $table = 'study_notes';

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
        'study_note_content',
        'created_date'
    ];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }
}
