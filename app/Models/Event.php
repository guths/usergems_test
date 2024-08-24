<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'start_at', 'end_at', 'last_updated'];

    public function people()
    {
        return $this->belongsToMany(Person::class, 'event_person');
    }
}
