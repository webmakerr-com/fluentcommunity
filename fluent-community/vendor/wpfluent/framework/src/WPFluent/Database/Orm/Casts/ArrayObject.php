<?php

namespace FluentCommunity\Framework\Database\Orm\Casts;

use JsonSerializable;
USE FluentCommunity\Framework\Support\Helper;
use FluentCommunity\Framework\Support\ArrayableInterface;
use ArrayObject as BaseArrayObject;

class ArrayObject extends BaseArrayObject implements ArrayableInterface, JsonSerializable
{
    /**
     * Get a collection containing the underlying array.
     *
     * @return \FluentCommunity\Framework\Support\Collection
     */
    public function collect()
    {
        return Helper::collect($this->getArrayCopy());
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getArrayCopy();
    }

    /**
     * Get the array that should be JSON serialized.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }
}
