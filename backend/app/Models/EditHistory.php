<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A persisted AI photo-edit result, owned by the user who created it. */
class EditHistory extends Model
{
    protected $fillable = ['user_id', 'instruction', 'path', 'mime', 'ext', 'sha256', 'bytes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
