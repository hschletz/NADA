<?php

namespace Nada\Database;

use Nada\Column\AbstractColumn as Column;

/**
 * Interface class for SQLite
 *
 * This class overrides methods with SQLite-specific implementations.
 */
class Sqlite extends AbstractDatabase
{
    /** {@inheritdoc} */
    public function isSqlite()
    {
        return true;
    }

    /** {@inheritdoc} */
    public function getServerVersion()
    {
        $version = $this->query('SELECT SQLITE_VERSION() AS version');
        return $version[0]['version'];
    }

    /** {@inheritdoc} */
    public function booleanLiteral($value)
    {
        return $value ? '1' : '0';
    }

    /** {@inheritdoc} */
    public function setTimezone($timezone = null)
    {
        // UTC is supported by default. Other values are invalid.
        if ($timezone !== null) {
            throw new \LogicException('Non-default timezone not supported for SQLite');
        }
    }

    /** {@inheritdoc} */
    public function getName()
    {
        // Return full path to the main database file
        $databases = $this->query('PRAGMA DATABASE_LIST');
        foreach ($databases as $database) {
            if ($database['name'] == 'main') {
                return $database['file'];
            }
        }
        throw new \LogicException('No entry found by getName()');
    }

    /** {@inheritdoc} */
    public function getTableNames()
    {
        // Fetch table names from sqlite_master, excluding system tables. The
        // name filter works because SQLite forbids names beginning with
        // "sqlite" for regular tables.
        $names = $this->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );
        // Flatten array
        foreach ($names as &$name) {
            $name = $name['name'];
        }
        return $names;
    }

    /** {@inheritdoc} */
    public function getViewNames()
    {
        $names = $this->query(
            "SELECT name FROM sqlite_master WHERE type='view'"
        );
        // Flatten array
        foreach ($names as &$name) {
            $name = $name['name'];
        }
        return $names;
    }

    /** {@inheritdoc} */
    public function getNativeDatatype($type, $length = null, $cast = false)
    {
        switch ($type) {
            case Column::TYPE_INTEGER:
                return 'INTEGER';
            case Column::TYPE_CLOB:
                return 'TEXT';
            case Column::TYPE_TIMESTAMP:
            case Column::TYPE_DATE:
                if (in_array($type, $this->emulatedDatatypes)) {
                    return 'TEXT';
                } else {
                    throw new \DomainException(strtoupper($type) . ' not supported by SQLite and not emulated');
                }
            case Column::TYPE_BOOL:
                if (in_array($type, $this->emulatedDatatypes)) {
                    return 'INTEGER';
                } else {
                    throw new \DomainException('BOOL not supported by SQLite and not emulated');
                }
            case Column::TYPE_DECIMAL:
                if (in_array($type, $this->emulatedDatatypes)) {
                    return 'REAL';
                } else {
                    throw new \DomainException('DECIMAL not supported by SQLite and not emulated');
                }
            case Column::TYPE_FLOAT:
                return 'REAL';
            default:
                // SQLite ignores $length, but stores it with the column
                // definition where it can later be reconstructed.
                return parent::getNativeDatatype($type, $length, $cast);
        }
    }

    /** {@inheritdoc} */
    protected function _getTablePkDeclaration(array $primaryKey, $autoIncrement)
    {
        // For autoincrement columns, the PK is already specified with the
        // column and must not be set again for the table.
        if ($autoIncrement) {
            return '';
        } else {
            return parent::_getTablePkDeclaration($primaryKey, $autoIncrement);
        }
    }
}
