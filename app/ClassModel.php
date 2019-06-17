<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    protected $primaryKey = 'class_id';

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
    protected $table = 'classes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'class_name', 'class_code', 'created_date', 'class_memberships_limit', 'icon_url', 'subject_1', 'subject_2', 'subject_3'];

    public static function getBySubjectId($subjectId)
    {
        return self::where('subject_1', $subjectId)->orWhere('subject_2', $subjectId)->orWhere('subject_3', $subjectId)->get();
    }

    public function progressTracking()
    {
        return $this->hasOne('App\ProgressTracking', 'class_id', 'class_id');
    }

    public function classMemberships()
    {
        return $this->hasMany('App\ClassMembership', 'class_id', 'class_id');
    }

    public static function generateClassCode()
    {
        $newClassCode = null;
        while (!$newClassCode) {
            $characters = '23456789ABCDEFGHJKLMNPRSTVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < 6; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            if (!ClassModel::where('class_code', $randomString)->first()) {
                $newClassCode = $randomString;
            }
        }
        return $newClassCode;
    }
}
