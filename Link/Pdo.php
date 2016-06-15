<?php
/**
 * Link to PDO based classes
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

namespace Nada\Link;

/**
 * Link to PDO based classes
 *
 * This class overrides methods with PDO-specific implementations.
 * @package NADA
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

    /**
     * Throw exception with information from PDO's errorInfo()
     * @param object PDO object that caused the error
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
