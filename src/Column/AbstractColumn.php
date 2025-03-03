<?php

namespace Nada\Column;

use Nada\Table\AbstractTable;

/**
 * Abstract column class
 *
 * This is the base class for providing a unified interface to database columns.
 * It is not intended to be instantiated directly, but through one of the
 * \Nada\Table\AbstractTable methods.
 */
abstract class AbstractColumn
{
    /**
     * Constant for INTEGER datatypes. Length is denoted in bits.
     */
    const TYPE_INTEGER = 'integer';

    /**
     * Constant for VARCHAR datatypes. Length is denoted in characters, not bytes.
     */
    const TYPE_VARCHAR = 'varchar';

    /**
     * Constant for TIMESTAMP datatypes (without timezone treatment)
     */
    const TYPE_TIMESTAMP = 'timestamp';

    /**
     * Constant for DATE datatypes
     */
    const TYPE_DATE = 'date';

    /**
     * Constant for BOOL datatypes (not available for all DBMS, emulated if necessary and enabled)
     */
    const TYPE_BOOL = 'bool';

    /**
     * Constant for CLOB datatypes. Length is only available for some DBMS. Don't rely on it.
     */
    const TYPE_CLOB = 'clob';

    /**
     * Constant for BLOB datatypes. Length is only available for some DBMS. Don't rely on it.
     */
    const TYPE_BLOB = 'blob';

    /**
     * Constant for DECIMAL/NUMERIC datatypes. Length consists of precision and scale, like '7,4'.
     */
    const TYPE_DECIMAL = 'decimal';

    /**
     * Constant for FLOAT datatypes. Length denotes precision, typically 24 for single 53 for double.
     */
    const TYPE_FLOAT = 'float';

    /**
     * Default length for integers if no length is explicitly specified
     **/
    const DEFAULT_LENGTH_INTEGER = 32;

    /**
     * Default length for floats if no length is explicitly specified
     **/
    const DEFAULT_LENGTH_FLOAT = 53; // Double precision

    /**
     * Database link
     * @var \Nada\Database\AbstractDatabase
     */
    protected $_database;

    /**
     * Table that this column belongs to
     */
    protected ?AbstractTable $_table;

    /**
     * Column name
     * @var string
     */
    protected $_name;

    /**
     * Column datatype
     * @var string
     */
    protected $_datatype;

    /**
     * Column length expression
     */
    protected ?string $_length = null;

    /**
     * Column NOT NULL constraint
     * @var bool
     */
    protected $_notnull;

    /**
     * Column default expression
     */
    protected ?string $_default = null;

    /**
     * TRUE if this is an autoincrement field
     * @var bool
     */
    protected $_autoIncrement;

    /**
     * Column comment
     */
    protected ?string $_comment = null;

    /**
     * Internal method to set up the object from within a \Nada\Table\AbstractTable object
     *
     * The column data can be of any type and is passed to the _parse*()
     * methods.
     *
     * @param \Nada\Table\AbstractTable $table Table that this column belongs to
     * @param mixed $data Column data
     * @internal
     */
    public function constructFromTable($table, $data)
    {
        $this->_database = $table->getDatabase();
        $this->_table = $table;
        $this->_parseName($data);
        $this->_parseDatatype($data);
        $this->_parseNotnull($data);
        $this->_parseDefault($data);
        $this->_parseAutoIncrement($data);
        $this->_parseComment($data);
    }

    /**
     * Internal method to set up the object from \Nada\Database\AbstractDatabase::createColumn()
     *
     * @param \Nada\Database\AbstractDatabase $database
     * @param string $name
     * @param string $type
     * @param mixed $length
     * @param bool $notnull
     * @param mixed $default
     * @param bool $autoIncrement
     * @internal
     **/
    public function constructNew(
        $database,
        $name,
        $type,
        $length,
        $notnull,
        $default,
        $autoIncrement,
        ?string $comment
    ) {
        $this->_database = $database;
        $this->_name = $name;
        $this->_datatype = $type;
        $this->_length = $length;
        $this->_notnull = $notnull;
        $this->_default = $default;
        $this->_autoIncrement = $autoIncrement;
        $this->_comment = $comment;
    }

    /**
     * Internal method to link this instance to a table object
     *
     * @param \Nada\Table\AbstractTable $table Table object
     * @internal
     **/
    public function setTable($table)
    {
        $this->_table = $table;
    }

    /**
     * Export column data to an associative array
     *
     * @return array Column data: name, type, length, notnull, default, autoincrement, comment
     */
    public function toArray()
    {
        return array(
            'name' => $this->_name,
            'type' => $this->_datatype,
            'length' => $this->_length,
            'notnull' => $this->_notnull,
            'default' => $this->_default,
            'autoincrement' => $this->_autoIncrement,
            'comment' => $this->_comment,
        );
    }

    /**
     * Extract name from column data
     *
     * The default implementation expects an array with information_schema
     * compatible keys.
     * @param mixed $data Column data
     */
    protected function _parseName($data)
    {
        $this->_name = $data['column_name'];
    }

    /**
     * Extract datatype and length from column data
     *
     * Since this part is DBMS-specific, no default implementation exists.
     * Implementations can expect an array with information_schema compatible
     * keys unless fetchColumns() generates something else.
     *
     * @param mixed $data Column data
     * @throws \UnexpectedValueException if datatype is not recognized.
     */
    abstract protected function _parseDatatype($data);

    /**
     * Extract NOT NULL constraint from column data
     *
     * The default implementation expects an array with information_schema
     * compatible keys.
     * @param mixed $data Column data
     */
    protected function _parseNotnull($data)
    {
        switch ($data['is_nullable']) {
            case 'YES':
                $this->_notnull = false;
                break;
            case 'NO':
                $this->_notnull = true;
                break;
            default:
                throw new \UnexpectedValueException('Invalid yes/no type: ' . $data['is_nullable']);
        }
    }

    /**
     * Extract default value from column data
     *
     * The default implementation expects an array with information_schema
     * compatible keys.
     * @param mixed $data Column data
     */
    protected function _parseDefault($data)
    {
        $this->_default = $data['column_default'];
    }

    /**
     * Extract autoincrement property from column data
     *
     * Since this part is DBMS-specific, no default implementation exists.
     * Implementations can expect an array with information_schema compatible
     * keys unless fetchColumns() generates something else.
     *
     * @param mixed $data Column data
     */
    abstract protected function _parseAutoIncrement($data);

    /**
     * Extract comment property from column data
     *
     * Since this part is DBMS-specific, no default implementation exists.
     * Implementations can expect an array with information_schema compatible
     * keys unless fetchColumns() generates something else.
     *
     * @param mixed $data Column data
     */
    abstract protected function _parseComment($data);

    /**
     * Get column name
     * @return string Column name
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get table object which this instance is linked to
     *
     * @return ?AbstractTable Parent table or NULL for objects created via AbstractDatabase::createColumn()
     */
    public function getTable(): ?AbstractTable
    {
        return $this->_table;
    }

    /**
     * Get column datatype
     *
     * The datatype is one of the TYPE_* constants. Depending on the datatype,
     * {@link getLength()} returns additional information.
     * @return string Abstract datatype constant
     */
    public function getDatatype()
    {
        return $this->_datatype;
    }

    /**
     * Get column length
     */
    public function getLength(): ?string
    {
        return $this->_length;
    }

    /**
     * Get NOT NULL constraint
     * @return bool TRUE if the column has a NOT NULL constraint.
     */
    public function getNotnull()
    {
        return $this->_notnull;
    }

    /**
     * Get default value
     */
    public function getDefault(): ?string
    {
        return $this->_default;
    }

    /**
     * Get autoincrement property
     * @return bool TRUE if the column is an autoincrement field.
     */
    public function getAutoIncrement()
    {
        return $this->_autoIncrement;
    }

    /**
     * Get column comment
     **/
    public function getComment(): ?string
    {
        return $this->_comment;
    }

    /**
     * Retrieve SQL fragment that describes the column properties
     *
     * The name is not part of the description. The return value is a DBMS-
     * specific pece of SQL like "VARCHAR(30) NOT NULL DEFAULT 'foo'".
     * @return string
     * @throws \DomainException if Column properties are invalid
     **/
    abstract public function getDefinition();

    /**
     * Change the column's name
     *
     * If this instance is linked to a table, i.e. not created via
     * \Nada\Database\AbstractDatabase::createColumn(), the operation will be
     * performed on the database.
     *
     * @param string $name New name
     * @throws \InvalidArgumentException if name is empty
     **/
    public function setName($name)
    {
        if (strlen($name) == 0) {
            throw new \InvalidArgumentException('Column name must not be empty');
        }
        if ($this->_table) {
            // Call the table method before the property gets updated because the
            // old name is retrieved from $this
            $this->_table->renameColumn($this, $name);
        }
        $this->_name = $name;
    }


    /**
     * Set the column's datatype and/or length
     *
     * If this instance is linked to a table, i.e. not created via
     * \Nada\Database\AbstractDatabase::createColumn(), the operation will be
     * performed on the database.
     *
     * Note that the database operation may fail if the column contains data that cannot be cast
     * to the new datatype.
     * @param string $datatype New datatype
     * @param string $length New length (default: none)
     **/
    public function setDatatype($datatype, $length = null)
    {
        $this->_datatype = $datatype;
        $this->_length = $length;
        if ($this->_table) {
            $this->_setDatatype();
        }
    }

    /**
     * DBMS-specific implementation for setting a column's datatype
     **/
    abstract protected function _setDatatype();

    /**
     * Set/remove a NOT NULL constraint
     *
     * If this instance is linked to a table, i.e. not created via
     * \Nada\Database\AbstractDatabase::createColumn(), the operation will be
     * performed on the database.
     *
     * @param bool $notNull
     */
    public function setNotNull($notNull)
    {
        if ($this->_notnull != $notNull) {
            $this->_notnull = (bool) $notNull;
            if ($this->_table) {
                $this->_setNotNull();
            }
        }
    }

    /**
     * DBMS-specific implementation for setting a column's NOT NULL constraint
     **/
    abstract protected function _setNotNull();

    /**
     * Set/remove default value
     *
     * If this instance is linked to a table, i.e. not created via
     * \Nada\Database\AbstractDatabase::createColumn(), the operation will be
     * performed on the database.
     *
     * @param mixed $default
     */
    public function setDefault($default)
    {
        // Since SQL types cannot be completely mapped to PHP types, a loose
        // comparision is required, but changes to/from NULL must be taken into
        // account.
        if (
            $this->_default === null and $default !== null or
            $this->_default !== null and $default === null or
            $this->_default != $default
        ) {
            $this->_default = $default;
            if ($this->_table) {
                $this->_setDefault();
            }
        }
    }

    /**
     * DBMS-specific implementation for setting a column's default value
     **/
    protected function _setDefault()
    {
        assert($this->_table instanceof AbstractTable);
        if ($this->_default === null) {
            $this->_table->alter(
                sprintf('ALTER COLUMN %s DROP DEFAULT', $this->_database->prepareIdentifier($this->_name))
            );
        } else {
            $this->_table->alter(
                sprintf(
                    'ALTER COLUMN %s SET DEFAULT %s',
                    $this->_database->prepareIdentifier($this->_name),
                    $this->_database->prepareValue($this->_default, $this->_datatype)
                )
            );
        }
    }

    /**
     * Set Column comment
     *
     * If this instance is linked to a table, i.e. not created via
     * \Nada\Database\AbstractDatabase::createColumn(), the operation will be
     * performed on the database.
     *
     * @param ?string $comment Comment (use NULL to remove comment)
     **/
    public function setComment(?string $comment)
    {
        if ($this->_comment != $comment) {
            $this->_comment = $comment;
            if ($this->_table) {
                $this->_setComment();
            }
        }
    }

    /**
     * DBMS-specific implementation for setting a column comment
     **/
    abstract protected function _setComment();

    /**
     * Compare with given specification
     *
     * @param mixed[] $newSpec Specification to compare with. Same keys as toArray() output.
     * @param string[] $keys Compare only given attributes (default: compare all)
     * @return bool
     */
    public function isDifferent(array $newSpec, ?array $keys = null)
    {
        $oldSpec = $this->toArray();
        if ($keys) {
            $keys = array_flip($keys);
            $oldSpec = array_intersect_key($oldSpec, $keys);
            $newSpec = array_intersect_key($newSpec, $keys);
        }
        return $this->_isDifferent($oldSpec, $newSpec);
    }

    /**
     * Compare specifications
     *
     * DBMS may require extending this method with tweaks for emulated types.
     *
     * @param mixed[] $oldSpec
     * @param mixed[] $newSpec
     **/
    protected function _isDifferent($oldSpec, $newSpec)
    {
        return $newSpec != $oldSpec;
    }
}
