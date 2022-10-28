<?php
/**
 * Factory class to create a NADA interface from a database link
 *
 * Copyright (C) 2011-2022 Holger Schletz <holger.schletz@web.de>
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
 * Factory class to create a NADA interface from a database link
 *
 * This is the method to get an interface to NADA's functionality. Connect to
 * the database as usual and pass the database link to the getDatabase() method.
 * Example for PDO:
 *
 *     $pdo = new \PDO($dsn, $user, $password);
 *     $database = \Nada\Factory::getDatabase($pdo);
 *
 * The result is a \Nada\Database\AbstractDatabase derived object which is aware
 * of the database link it was created from and the DBMS type it connects to.
 * All further interaction starts with this object.
 */
class Factory
{
    /**
     * Factory method to create database interface
     *
     * See class description for usage example.
     * @param mixed $link Database link
     * @return \Nada\Database\AbstractDatabase NADA interface
     * @throws \InvalidArgumentException if no supported DBAL is detected
     */
    static function getDatabase($link)
    {
        // Determine the database abstraction layer
        if ($link instanceof \PDO) {
            $class = 'Pdo';
        } elseif ($link instanceof \Laminas\Db\Adapter\Adapter) {
            $class = 'LaminasDb';
        } else {
            throw new \InvalidArgumentException('Unsupported link type');
        }

        // Create matching link object
        $class = "Nada\Link\\$class";
        $link = new $class($link);

        // Create and return matching database object
        $class = 'Nada\Database\\' . $link->getDbmsSuffix();
        return new $class($link);
    }
}
