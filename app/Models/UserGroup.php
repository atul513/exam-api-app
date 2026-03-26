<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserGroup extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_members');
    }

    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(QuizSchedule::class, 'quiz_schedule_groups', 'user_group_id', 'schedule_id');
    }
}
