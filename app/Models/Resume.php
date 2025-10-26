<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resume extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['user_id', 'job_title', 'job_description', 'content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);

    }
}
