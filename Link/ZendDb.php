<?php
/**
 * Link to Zend_Db_Adapter based classes
 *
 * Copyright (C) 2011-2016 Holger Schletz <holger.schletz@web.de>
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package NADA
 */
/**
 * Link to Zend_Db_Adapter based classes
 *
 * This class overrides methods with ZendDb-specific implementations.
 * @package NADA
 * @internal
 */
class Nada_Link_ZendDb extends Nada_Link
{

    /** {@inheritdoc} */
    public function getDbmsSuffix()
    {
        if ($this->_link instanceof Zend_Db_Adapter_Pdo_Mysql) {
            return 'Mysql';
        } elseif ($this->_link instanceof Zend_Db_Adapter_Mysqli) {
            return 'Mysql';
        } elseif ($this->_link instanceof Zend_Db_Adapter_Pdo_Pgsql) {
            return 'Pgsql';
        } elseif ($this->_link instanceof Zend_Db_Adapter_Pdo_Sqlite) {
            return 'Sqlite';
        } else {
            throw new UnexpectedValueException('Unsupported DBMS type');
        }
    }

    /** {@inheritdoc} */
    public function query($statement, $params)
    {
        $statement = $this->_link->query($statement, $params);

        // Don't use fetchAll() because keys must be turned lowercase
        $rowset = array();
        while ($row = $statement->fetch(Zend_Db::FETCH_ASSOC)) {
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
        return $this->_link->query($statement, $params)->rowCount();
    }

    /** {@inheritdoc} */
    public function quoteValue($value, $datatype)
    {
        return $this->_link->quote((string)$value);
    }

    /** {@inheritdoc} */
    public function quoteIdentifier($identifier)
    {
        return $this->_link->quoteIdentifier($identifier);
    }
}
