<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Platform super admin (us). Lives outside every tenant.
 */
#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasApiTokens, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
