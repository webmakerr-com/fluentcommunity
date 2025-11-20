<?php

namespace FluentCommunity\App\Models;


/**
 *  FluentCRM Contact Model
 *
 *  Database Model
 *
 * @package FluentCommunity\App\Models
 *
 * @version 1.1.0
 */
class Contact extends Model
{
    protected $table = 'fc_subscribers';

    protected $guarded = ['id'];

    protected $fillable = [
        'hash',
        'prefix',
        'first_name',
        'last_name',
        'user_id',
        'company_id',
        'email',
        'status', // pending / subscribed / bounced / unsubscribed; Default: subscriber
        'contact_type', // lead / customer
        'address_line_1',
        'address_line_2',
        'postal_code',
        'city',
        'state',
        'country',
        'phone',
        'timezone',
        'date_of_birth',
        'source',
        'life_time_value',
        'last_activity',
        'total_points',
        'latitude',
        'longitude',
        'ip',
        'created_at',
        'updated_at',
        'avatar'
    ];

}
