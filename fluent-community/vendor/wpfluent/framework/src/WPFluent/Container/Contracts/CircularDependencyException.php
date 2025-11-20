<?php

namespace FluentCommunity\Framework\Container\Contracts;

use Exception;
use FluentCommunity\Framework\Container\Contracts\Psr\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
