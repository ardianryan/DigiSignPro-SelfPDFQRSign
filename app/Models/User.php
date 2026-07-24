<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'position', 'signature_path', 'signature_prefix', 'api_key', 'api_key_created_at'])]
#[Hidden(['password', 'remember_token', 'api_key'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'api_key_created_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function generateApiKey(): string
    {
        return 'digi_'.\Illuminate\Support\Str::random(48);
    }

    public function ensureApiKey(): string
    {
        if (empty($this->api_key)) {
            $this->forceFill([
                'api_key' => static::generateApiKey(),
                'api_key_created_at' => now(),
            ])->save();
        }

        return $this->api_key;
    }
}
