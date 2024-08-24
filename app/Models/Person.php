<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $hidden = [
        'api_key'
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'avatar',
        'title',
        'linkedin_url',
        'company_id',
        'last_synced_at',
        'is_internal',
        'api_key',
        'enabled'
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function dailyEmails(): HasMany
    {
        return $this->hasMany(DailyEmail::class);
    }

    public function events(): BelongsToMany
    {
        return $this->BelongsToMany(Event::class, 'event_person');
    }
}
