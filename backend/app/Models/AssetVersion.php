<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetVersion extends Model
{
    protected $fillable = ['asset_id', 'original_sha256', 'status', 'label'];

    protected static function booted(): void
    {
        static::updating(function () { throw new \LogicException('Asset versions are immutable'); });
    }

    public function asset() { return $this->belongsTo(Asset::class); }
}
