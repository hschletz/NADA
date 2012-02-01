<?php
/**
 * Column class for PostgreSQL
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
 * Column class for PostgreSQL
 *
 * This class overrides methods with PostgreSQL-specific implementations.
 * @package NADA
 */
class Nada_Column_Pgsql extends Nada_Column
{

    /** {@inheritdoc} */
    protected function _parseDatatype($data)
    {
        switch ($data['data_type']) {
            case 'integer':
            case 'smallint':
            case 'bigint':
                $this->_datatype = Nada::DATATYPE_INTEGER;
                $this->_length = $data['numeric_precision'];
                break;
            case 'character varying':
                $this->_datatype = Nada::DATATYPE_VARCHAR;
                $this->_length = $data['character_maximum_length'];
                break;
            case 'timestamp without time zone':
                $this->_datatype = Nada::DATATYPE_TIMESTAMP;
                break;
            case 'text':
                $this->_datatype = Nada::DATATYPE_CLOB;
                break;
            case 'numeric':
                $this->_datatype = Nada::DATATYPE_DECIMAL;
                $this->_length = $data['numeric_precision'] . ',' . $data['numeric_scale'];
                break;
            default:
                throw new UnexpectedValueException('Unknown PostgreSQL Datatype: ' . $data['data_type']);
        }
    }
}
