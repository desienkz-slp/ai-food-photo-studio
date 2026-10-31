<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderConfiguration extends Model
{
    protected $fillable = ['type', 'communication_mode', 'name', 'endpoint', 'model', 'api_key'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted'];
    }
}
