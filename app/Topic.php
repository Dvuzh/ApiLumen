<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $primaryKey = 'topic_id';

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
    protected $fillable = ['topic_name', 'subject_id', 'order', 'status', 'published_status', 'created_date', 'user_id'];

    public function subject()
    {
        return $this->hasOne('App\Subject', 'subject_id', 'subject_id');
    }

    public function skills()
    {
        return $this->hasMany('App\Skill', 'topic_id', 'topic_id');
    }
}
