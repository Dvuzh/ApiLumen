<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SkillResult extends Model
{
    protected $primaryKey = 'skill_result_id';
    
    /**
    * Indicates if the model should be timestamped.
    *
    * @var bool
    */
    public $timestamps = false;

    protected $table = 'skill_results';

}
