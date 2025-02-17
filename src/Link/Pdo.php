<?php

namespace Nada\Link;

/**
 * Link to PDO based classes
 *
 * This class overrides methods with PDO-specific implementations.
 * @internal
 */
class Pdo extends AbstractLink
{

    /** {@inheritdoc} */
    public function getDbmsSuffix()
    {
        switch ($this->_link->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            case 'mysql':
                return 'Mysql';
            case 'pgsql':
                return 'Pgsql';
            case 'sqlite':
                return 'Sqlite';
            default:
                throw new \UnexpectedValueException('Unsupported DBMS type');
        }
    }

    /** {@inheritdoc} */
    public function query($statement, $params)
    {
        $statement = $this->_link->prepare($statement);
        if ($statement === false) {
            $this->_throw($this->_link);
        }

        if ($statement->execute($params) === false) {
            $this->_throw($statement);
        }

        // Don't use fetchAll() because keys must be turned lowercase
        $rowset = array();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
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
        $statement = $this->_link->prepare($statement);
        if ($statement === false) {
            $this->_throw($this->_link);
        }

        if ($statement->execute($params) === false) {
            $this->_throw($statement);
        }

        return $statement->rowCount();
    }

    /** {@inheritdoc} */
    public function quoteValue($value, $datatype)
    {
        return $this->_link->quote((string)$value, \PDO::PARAM_STR);
    }

    public function inTransaction(): bool
    {
        return $this->_link->inTransaction();
    }

    /**
     * Throw exception with information from PDO's errorInfo()
     * @param object $object PDO object that caused the error
     * @throws \RuntimeException
     */
    protected function _throw($object)
    {
        $error = $object->errorInfo();
        throw new \RuntimeException(
            sprintf(
                'PDO operation returned SQLSTATE %s: Error %s (%s)',
                $error[0],
                $error[1],
                $error[2]
            )
        );
    }
}
