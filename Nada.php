<?php
/**
 * Factory class to create a NADA interface from a database link
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
 * Factory class to create a NADA interface from a database link
 *
 * This is the method to get an interface to NADA's functionality. Connect to
 * the database as usual via PDO/Zend_Db/MDB2 and pass the database link to the
 * factory() method. Example for PDO:
 *
 *     require_once 'path/to/NADA/Nada.php'; // No need to have this in include_path
 *     $pdo = new PDO($dsn, $user, $password);
 *     $nada = Nada::factory($pdo);
 *
 * The result is a {@link Nada_Dbms} derived object which is aware of the
 * database link it was created from and the DBMS type it connects to. All
 * further interaction goes through this object.
 * @package NADA
 * @api
 */
class Nada
{
    /**
     * Factory method to create NADA interface
     *
     * See class description for usage example.
     * @param mixed $link Database link
     * @return Nada_Dbms NADA interface
     * @throws InvalidArgumentException if no supported DBAL is detected
     */
    static function factory($link)
    {
        // Determine the database abstraction layer
        if ($link instanceof PDO) {
            $class = 'Pdo';
        } elseif ($link instanceof Zend_Db_Adapter_Abstract) {
            $class = 'ZendDb';
        } elseif ($link instanceof MDB2_Driver_Common) {
            $class = 'Mdb2';
        } else {
            throw new InvalidArgumentException('Unsupported link type');
        }

        // Create matching Nada_Link object
        self::_requireOnce('Link.php');
        self::_requireOnce("Link/$class.php");
        $class = "Nada_Link_$class";
        $link = new $class($link);

        // Load matching classes
        $class = $link->getDbmsSuffix();
        self::_requireOnce('Dbms.php');
        self::_requireOnce("Dbms/$class.php");
        self::_requireOnce('Table.php');
        self::_requireOnce("Table/$class.php");

        // Create and return matching Nada_Dbms object
        $class = "Nada_Dbms_$class";
        return new $class($link);
    }

    /**
     * Wrapper for require_once that prepends this file's absolute path
     * @param $path Relative path to PHP script to include
     */
    protected static function _requireOnce($path)
    {
        require_once dirname(__FILE__) . '/' . $path;
    }
}
