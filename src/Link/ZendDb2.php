<?php
/**
 * Link to Zend\Db\Adapter\Adapter
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
 */

namespace Nada\Link;

/**
 * Link to Zend\Db\Adapter\Adapter
 *
 * This class overrides methods with Zend\Db specific implementations.
 * @internal
 */
class ZendDb2 extends AbstractLink
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
        $rowset = array();
        foreach ($result as $row) {
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
    public function quoteIdentifier($identifier)
    {
        return $this->_link->getPlatform()->quoteIdentifier($identifier);
    }
}
