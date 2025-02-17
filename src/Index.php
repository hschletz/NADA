<?php

namespace Nada;

/**
 * Index class
 *
 * Instances should not be constructed directly. Always call \Nada\Table\AbstractTable::getIndexes().
 */
class Index
{
    /**
     * Index name
     * @var string
     */
    protected $_name;

    /**
     * Names of indexed columns
     * @var string[]
     */
    protected $_columns;

    /**
     * Flag for unique index
     * @var bool
     */
    protected $_unique;

    /**
     * Constructor
     *
     * @param string $name Index name
     * @param string[] $columns Column names
     * @param bool $unique Flag for unique index
     */
    public function __construct($name, array $columns, $unique)
    {
        $this->_name = $name;
        $this->_columns = $columns;
        $this->_unique = (bool) $unique;
    }

    /**
     * Get Index name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get names of indexed columnd
     *
     * @return string[]
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * Get unique property
     *
     * @return bool
     */
    public function isUnique()
    {
        return $this->_unique;
    }

    /**
     * Export index as array
     *
     * The keys are 'name', 'unique' and 'columns'.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'name' => $this->_name,
            'unique' => $this->_unique,
            'columns' => $this->_columns,
        );
    }
}
