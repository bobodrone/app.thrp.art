<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreatorApplication extends Model
{
    /** @use HasFactory<\Database\Factories\CreatorApplicationFactory> */
    use HasFactory;

    protected $table = 'creator_applications';

    protected $casts = [
        'status'      => ApplicationStatus::class,
        'applied_at'  => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected $fillable = ['email', 'name', 'message', 'status', 'reviewed_at'];

    public $timestamps = false; // we manage applied_at/reviewed_at explicitly
}