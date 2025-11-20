<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\App\Functions\Utility;

/**
 *  Meta Model - DB Model for Meta table
 *
 *  Database Model
 *
 * @package FluentCommunity\App\Models
 *
 * @version 1.0.0
 */
class Meta extends Model
{
    protected $table = 'fcom_meta';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $fillable = [
        'object_type',
        'object_id',
        'meta_key',
        'value'
    ];

    public function setValueAttribute($value)
    {
        $this->attributes['value'] = maybe_serialize($value);
    }

    public function getValueAttribute($value)
    {
        return Utility::safeUnserialize($value);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('object_type', $type);
    }

    public function scopeByMetaKey($query, $key)
    {
        return $query->where('meta_key', $key);
    }

    public function scopeByObjectId($query, $objectId)
    {
        return $query->where('object_id', $objectId);
    }

}
