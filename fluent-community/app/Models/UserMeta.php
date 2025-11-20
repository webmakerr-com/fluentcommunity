<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\Framework\Database\Orm\Model;

class UserMeta extends Model
{
    protected $table = 'usermeta';

    protected $primaryKey = 'umeta_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'meta_key',
        'meta_value'
    ];

    /**
     * Get the user that owns the meta.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }

    /**
     * Scope query to get meta by key
     */
    public function scopeByKey($query, $key)
    {
        return $query->where('meta_key', $key);
    }

    /**
     * Scope query to get meta by user ID
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
