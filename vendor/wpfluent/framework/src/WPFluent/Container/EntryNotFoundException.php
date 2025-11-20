<?php

namespace FluentCommunity\Framework\Container;

use Exception;
use FluentCommunity\Framework\Container\Contracts\Psr\NotFoundExceptionInterface;

class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
    //
}
