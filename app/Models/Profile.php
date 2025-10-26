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

    protected $fillable = ['user_id', 'data', 'knowledgebase', 'knowledgebase_updated_at'];

    protected $casts = [
        'data' => 'array',
        'knowledgebase' => 'array',
        'knowledgebase_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);

    }
}
