<?php

namespace FluentCommunity\Framework\Database\Concerns;

use FluentCommunity\Framework\Support\Collection;

trait ExplainsQueries
{
    /**
     * Explains the query.
     *
     * @return \FluentCommunity\Framework\Support\Collection
     */
    public function explain()
    {
        $sql = $this->toSql();

        $bindings = $this->getBindings();

        $explanation = $this->getConnection()->select('EXPLAIN '.$sql, $bindings);

        return new Collection($explanation);
    }
}
