<?php
/**
 * Index class
 *
 * Copyright (C) 2011-2020 Holger Schletz <holger.schletz@web.de>
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

namespace Nada;

/**
 * Index class
 *
 * Instances should not be constructed directly. Always call \Nada\Table\AbstractTable::getIndexes().
 */
class Index
{
    /**
     * Index name
     * @var string
     */
    protected $_name;

    /**
     * Names of indexed columns
     * @var string[]
     */
    protected $_columns;

    /**
     * Flag for unique index
     * @var bool
     */
    protected $_unique;

    /**
     * Constructor
     *
     * @param string $name Index name
     * @param string[] $columns Column names
     * @param bool $unique Flag for unique index
     */
    public function __construct($name, array $columns, $unique)
    {
        $this->_name = $name;
        $this->_columns = $columns;
        $this->_unique = (bool) $unique;
    }

    /**
     * Get Index name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get names of indexed columnd
     *
     * @return string[]
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * Get unique property
     *
     * @return bool
     */
    public function isUnique()
    {
        return $this->_unique;
    }

    /**
     * Export index as array
     *
     * The keys are 'name', 'unique' and 'columns'.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'name' => $this->_name,
            'unique' => $this->_unique,
            'columns' => $this->_columns,
        );
    }
}
