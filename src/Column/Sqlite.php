<?php

namespace Nada\Column;

use Nada\Table\Sqlite as SqliteTable;
use UnexpectedValueException;
use UnhandledMatchError;

/**
 * Column class for SQLite
 *
 * This class overrides methods with SQLite-specific implementations.
 */
class Sqlite extends AbstractColumn
{
    /** {@inheritdoc} */
    protected function _parseName($data)
    {
        $this->_name = $data['name'];
    }

    /** {@inheritdoc} */
    protected function _parseDatatype($data)
    {
        // SQLite's dynamic typing works different than the static typing of
        // other DBMS. The best we can do here is determining the column's type
        // affinity according to the rules documented at
        // http://www.sqlite.org/datatype3.html#affname
        $type = strtoupper($data['type']);
        if (strpos($type, 'INT') !== false) {
            $this->_datatype = self::TYPE_INTEGER;
        } elseif (preg_match('/(CHAR|CLOB|TEXT).*?(\((\d+)\))?$/', $type, $matches)) {
            // Although SQLite ignores a length specification, it is preserved
            // in the column definition. If a length is given, the type is
            // interpreted as VARCHAR(length), otherwise as CLOB.
            if (isset($matches[3])) {
                $this->_datatype = self::TYPE_VARCHAR;
                $this->_length = $matches[3];
            } else {
                $this->_datatype = self::TYPE_CLOB;
            }
        } elseif (strpos($type, 'BLOB') !== false) {
            $this->_datatype = self::TYPE_BLOB;
        } elseif (preg_match('/(REAL|FLOA|DOUB)/', $type)) {
            $this->_datatype = self::TYPE_FLOAT;
        } else {
            // While type affinity rules would handle any other type as numeric,
            // the type name itself is preserved and can be used for these
            // types.
            try {
                $this->_datatype = match ($type) {
                    'DATE' => self::TYPE_DATE,
                    'DATETIME' => self::TYPE_TIMESTAMP,
                };
            } catch (UnhandledMatchError) {
                throw new UnexpectedValueException('Unrecognized SQLite Datatype: ' . $data['type']);
            }
        }
    }

    /** {@inheritdoc} */
    protected function _parseNotNull($data)
    {
        $this->_notnull = (bool) $data['notnull'];
    }

    /** {@inheritdoc} */
    protected function _parseDefault($data)
    {
        if ($data['dflt_value'] == 'NULL' || $data['dflt_value'] === null) {
            $this->_default = null;
        } elseif (preg_match("/^'(.*)'$/", $data['dflt_value'], $matches)) {
            // Remove surrounding quotes, unescape quotes
            $this->_default = str_replace("''", "'", $matches[1]);
        } else {
            $this->_default = $data['dflt_value'];
        }
    }

    /** {@inheritdoc} */
    protected function _parseAutoIncrement($data)
    {
        if (!$this->_datatype) {
            $this->_parseDatatype($data);
        }
        $this->_autoIncrement = (
            $data['pk'] and
            $data['pkColumnCount'] == 1 and
            $this->_datatype == self::TYPE_INTEGER
        );
    }

    /** {@inheritdoc} */
    protected function _parseComment($data)
    {
        // Columnn comments are not supported by SQLite.
    }

    /** {@inheritdoc} */
    public function getDefinition()
    {
        $sql = $this->_database->getNativeDatatype($this->_datatype, $this->_length);

        if ($this->_notnull) {
            $sql .= ' NOT NULL';
        }

        $sql .= ' DEFAULT ';
        $sql .= $this->_database->prepareValue($this->_default, $this->_datatype);

        // Autoincrement columns are required to be declared primary key. The
        // AUTOINCREMENT keyword is not strictly necessary to auto-generate
        // values in SQLite, but required for values unique over the table's
        // lifetime. This is more consistent with the behavior of other DBMS
        // and may also be expected by applications.
        if ($this->_autoIncrement) {
            $sql .= ' PRIMARY KEY AUTOINCREMENT';
        }

        return $sql;
    }

    /** {@inheritdoc} */
    protected function _setDatatype()
    {
        assert($this->_table instanceof SqliteTable);
        $this->_table->alterColumn($this->_name, 'type', $this->_datatype);
    }

    /** {@inheritdoc} */
    protected function _setDefault()
    {
        assert($this->_table instanceof SqliteTable);
        $this->_table->alterColumn($this->_name, 'default', $this->_default);
    }

    /** {@inheritdoc} */
    protected function _setNotNull()
    {
        assert($this->_table instanceof SqliteTable);
        $this->_table->alterColumn($this->_name, 'notnull', $this->_notnull);
    }

    /** {@inheritdoc} */
    protected function _setComment()
    {
        // Columnn comments are not supported by SQLite.
    }

    /** {@inheritdoc} */
    protected function _isDifferent($oldSpec, $newSpec)
    {
        if (array_key_exists('type', $newSpec)) {
            $oldType = $oldSpec['type'];
            $newType = $newSpec['type'];
            if (
                $oldType == self::TYPE_INTEGER and
                $newType == self::TYPE_INTEGER and
                array_key_exists('length', $newSpec)
            ) {
                // Integers always have length set to NULL.
                $newSpec['length'] = null;
            } elseif (
                $oldType == self::TYPE_INTEGER and
                $newType == self::TYPE_BOOL
            ) {
                // Booleans are detected as INTEGER.
                $newSpec['type'] = self::TYPE_INTEGER;
            } elseif (
                $oldType == self::TYPE_CLOB and
                ($newType == self::TYPE_TIMESTAMP or $newType == self::TYPE_DATE)
            ) {
                // Timestamps and dates are detected as CLOB.
                $newSpec['type'] = self::TYPE_CLOB;
            } elseif (
                $oldType == self::TYPE_FLOAT and
                $newType == self::TYPE_DECIMAL
            ) {
                // Decimals are detected as FLOAT.
                $newSpec['type'] = self::TYPE_FLOAT;
                if (array_key_exists('length', $newSpec)) {
                    $newSpec['length'] = null;
                }
            }
        }
        return parent::_isDifferent($oldSpec, $newSpec);
    }
}
