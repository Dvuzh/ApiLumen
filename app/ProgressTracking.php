<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProgressTracking extends Model
{
    protected $primaryKey = 'tracking_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected $table = "progress_tracking";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'tracking_code', 'class_id', 'timestamp'];
}
