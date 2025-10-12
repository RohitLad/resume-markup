<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasUuids;
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['user_id', 'data'];
    protected $casts = ['data'=>'array'];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);

    }
}
