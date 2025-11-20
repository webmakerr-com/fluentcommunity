<?php

namespace FluentCommunity\App\Models;

class DynamicModel extends Model
{
    public function __construct($attributes = [], $table = null)
    {
        parent::__construct($attributes);
        $this->table = $table;
    }

    protected $guarded = [];
}
