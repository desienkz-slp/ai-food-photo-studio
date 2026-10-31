<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class Asset extends Model { protected $guarded=[]; public function versions(){return $this->hasMany(AssetVersion::class);} }
