<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    /**
     * The table associated with this model.
     *
     * @var string
     */
    protected $table = 'courses';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_name',
        'course_number',
        'course_name'
    ];


    /**
     * A course may have many outcomes.
     *
     * Get the outcomes for a given course.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function outcomes()
    {

        return $this->belongsToMany('App\Outcome', 'course_outcome', 'course_id', 'outcome_id');

    }


    /**
     * Subject name to upper case.
     *
     * @param  string  $value
     * @return string
     */
    public function setSubjectNameAttribute($value)
    {
        $this->attributes['subject_name'] = strtoupper($value);
    }

}
