<?php

namespace Nada\Link;

use LogicException;
use PDO;

/**
 * Link to Laminas\Db\Adapter\Adapter
 *
 * This class overrides methods with Laminas\Db specific implementations.
 * @internal
 */
class LaminasDb extends AbstractLink
{

    /** {@inheritdoc} */
    public function getDbmsSuffix()
    {
        switch ($this->_link->getDriver()->getDatabasePlatformName()) {
            case 'Mysql':
                return 'Mysql';
            case 'Postgresql':
                return 'Pgsql';
            case 'Sqlite':
                return 'Sqlite';
            default:
                throw new \UnexpectedValueException('Unsupported DBMS type');
        }
    }

    /** {@inheritdoc} */
    public function query($statement, $params)
    {
        $result = $this->_link->query($statement, $params);

        // Don't use toArray() because keys must be turned lowercase
        $rowset = [];
        foreach ($result as $row) {
            $output = [];
            foreach ($row as $column => $value) {
                $output[strtolower($column)] = $value;
            }
            $rowset[] = $output;
        }
        return $rowset;
    }

    /** {@inheritdoc} */
    public function exec($statement, $params)
    {
        return $this->_link->query($statement, $params)->count();
    }

    /** {@inheritdoc} */
    public function quoteValue($value, $datatype)
    {
        return $this->_link->getPlatform()->quoteValue((string)$value);
    }

    /** {@inheritdoc} */
    public function quoteIdentifier($identifier): ?string
    {
        return $this->_link->getPlatform()->quoteIdentifier($identifier);
    }

    public function inTransaction(): bool
    {
        $resource = $this->_link->getDriver()->getConnection()->getResource();
        if ($resource instanceof PDO) {
            return $resource->inTransaction();
        } else {
            throw new LogicException(__METHOD__ . '() only supports PDO drivers');
        }
    }
}
