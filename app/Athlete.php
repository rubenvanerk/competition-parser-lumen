<?php

namespace App;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Athlete extends Model
{
    use Sluggable;

    protected $connection = 'rankings';
    protected $table = 'rankings_athlete';
    public $timestamps = false;
    protected $fillable = ['name', 'gender', 'year_of_birth'];

    public function nationalities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'rankings_athlete_nationalities');
    }

    public function alias_of(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'alias_of_id');
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }
}
