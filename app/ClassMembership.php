<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClassMembership extends Model
{
    protected $primaryKey = 'class_membership_id';

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
    protected $table = 'class_memberships';

     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'class_id'];

    public function classModel()
    {
        return $this->hasOne('App\ClassModel', 'class_id', 'class_id');
    }
}
