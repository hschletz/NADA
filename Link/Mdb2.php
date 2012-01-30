<?php
/**
 * Link to MDB2 based classes
 *
 * $Id$
 *
 * Copyright (c) 2011,2012 Holger Schletz <holger.schletz@web.de>
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
 * Link to MDB2 based classes
 *
 * This class overrides methods with MDB2-specific implementations.
 * @package NADA
 */
class Nada_Link_Mdb2 extends Nada_Link
{

    /** {@inheritdoc} */
    public function getDbmsSuffix()
    {
        switch ($this->_link->dbsyntax) {
            case 'mysql':
                return 'Mysql';
            case 'pgsql':
                return 'Pgsql';
            default:
                throw new UnexpectedValueException('Unsupported DBMS type');
        }
    }

    /** {@inheritdoc} */
    public function exec($statement, $params)
    {
        $statement = $this->_link->prepare($statement, null, MDB2_PREPARE_MANIP);
        if (PEAR::isError($statement)) {
            throw new RuntimeException($statement->getMessage());
        }
        $result = $statement->execute($params);
        if (PEAR::isError($result)) {
            throw new RuntimeException($result->getMessage());
        }
        return $result;
    }

    /** {@inheritdoc} */
    public function getServerVersion()
    {
        $version = $this->_link->getServerVersion();
        return $version['native'];
    }

}
