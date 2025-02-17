<?php

namespace Nada\Database;

use Nada\Column\AbstractColumn as Column;

/**
 * Interface class for PostgreSQL
 *
 * This class overrides methods with PostgreSQL-specific implementations.
 */
class Pgsql extends AbstractDatabase
{

    /** {@inheritdoc} */
    protected $_tableSchema = 'public';

    /** {@inheritdoc} */
    public function isPgsql()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function getServerVersion()
    {
        $version = $this->query('SELECT VERSION() AS version');
        $version = $version[0]['version'];

        // Extract second part from version string ("PostgreSQL x.y.z on ...")
        $startpos = strpos($version, ' ') + 1;
        $endpos = strpos($version, ' ', $startpos);
        return substr($version, $startpos, $endpos - $startpos);
    }

    /** {@inheritdoc} */
    public function iLike()
    {
        return ' ILIKE ';
    }

    /** {@inheritdoc} */
    public function setTimezone($timezone = null)
    {
        if ($timezone === null) {
            $timezone = 'UTC';
        }
        $this->exec('SET timezone TO ' . $this->prepareValue($timezone, Column::TYPE_VARCHAR));
    }

    /** {@inheritdoc} */
    public function convertTimestampColumns()
    {
        $columns = $this->query(
            'SELECT table_name, column_name FROM information_schema.columns ' .
                'WHERE datetime_precision != 0 AND table_schema = ? AND data_type IN(?, ?)',
            array($this->_tableSchema, 'timestamp with time zone', 'timestamp without time zone')
        );
        foreach ($columns as $column) {
            $this->getTable($column['table_name'])
                ->getColumn($column['column_name'])
                ->setDatatype(Column::TYPE_TIMESTAMP);
        }
        return count($columns);
    }

    /** {@inheritdoc} */
    public function setStrictMode()
    {
        // Force standard compliant escaping of single quotes ('', not \')
        $this->exec('SET backslash_quote TO off');
        // Treat backslashes literally (not as escape character)
        $this->exec('SET standard_conforming_strings TO on');
        // Keep special semantics of NULL, i.e. 'expr = NULL' always evaluates to FALSE
        $this->exec('SET transform_null_equals TO off');
        // Don't implicitly add missing columns to FROM clause (no longer supported with 9.0)
        if (version_compare($this->getServerVersion(), '9.0', '<')) {
            $this->exec('SET add_missing_from TO off');
        }
    }

    /** {@inheritdoc} */
    public function getNativeDatatype($type, $length = null, $cast = false)
    {
        switch ($type) {
            case Column::TYPE_TIMESTAMP:
                return 'TIMESTAMP(0)';
            case Column::TYPE_CLOB:
                return 'TEXT';
            case Column::TYPE_BLOB:
                return 'BYTEA';
            default:
                return parent::getNativeDatatype($type, $length, $cast);
        }
    }

    /** {@inheritdoc} */
    public function createTable($name, array $columns, $primaryKey = null)
    {
        $table = parent::createTable($name, $columns, $primaryKey);

        // CREATE TABLE does not set comments. Add them manually.
        foreach ($columns as $column) {
            if (is_array($column)) {
                $column = $this->createColumnFromArray($column);
            }
            $comment = $column->getComment();
            if ($comment) {
                $column->setTable($table);
                $column->setComment(null); // Cached value is invalid at this stage, reset it
                $column->setComment($comment);
            }
        }

        // Refresh cached table object
        $this->clearCache($name);
        return $this->getTable($name);
    }
}
