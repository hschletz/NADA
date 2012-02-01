<?php
/**
 * Column class for MySQL
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
 * Column class for MySQL
 *
 * This class overrides methods with MySQL-specific implementations.
 * @package NADA
 */
class Nada_Column_Mysql extends Nada_Column
{

    /** {@inheritdoc} */
    protected function _parseDatatype($data)
    {
        switch ($data['data_type']) {
            case 'int':
                $this->_datatype = Nada::DATATYPE_INTEGER;
                $this->_length = 32;
                break;
            case 'varchar':
                $this->_datatype = Nada::DATATYPE_VARCHAR;
                $this->_length = $data['character_maximum_length'];
                break;
            case 'datetime':
                $this->_datatype = Nada::DATATYPE_TIMESTAMP;
                break;
            case 'longtext':
                $this->_datatype = Nada::DATATYPE_CLOB;
                $this->_length = $data['character_maximum_length'];
                break;
            case 'smallint':
                $this->_datatype = Nada::DATATYPE_INTEGER;
                $this->_length = 16;
                break;
            case 'bigint'://
                $this->_datatype = Nada::DATATYPE_INTEGER;
                $this->_length = 64;
                break;
            case 'decimal'://
                $this->_datatype = Nada::DATATYPE_DECIMAL;
                $this->_length = $data['numeric_precision'] . ',' . $data['numeric_scale'];
                break;
            default:
                throw new UnexpectedValueException('Unknown MySQL Datatype: ' . $data['data_type']);
        }
    }

}