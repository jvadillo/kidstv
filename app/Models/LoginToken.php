<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['purpose', 'email', 'user_id', 'link_hash', 'code_hash', 'attempts', 'ip_address', 'expires_at', 'used_at'])]
class LoginToken extends Model
{
    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_CONFIRM = 'confirm';

    public const PURPOSE_CHANGE_EMAIL = 'change_email';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < config('nokoplay.login_code_max_attempts');
    }
}
