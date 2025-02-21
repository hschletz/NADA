<?php

namespace Nada;

use Laminas\Db\Adapter\Adapter;
use Nada\Database\AbstractDatabase;
use PDO;

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
 * Alternatively, you can instantiate and invoke the factory:
 *
 *     $pdo = new \PDO($dsn, $user, $password);
 *     $factory = new \Nada\Factory();
 *     $database = $factory($pdo);
 *
 * This is useful if you want to inject the factory as a dependency of another
 * class:
 * 
 *     class MyClass
 *     {
 *         public funcion __construct(\Nada\Factory $factory, \PDO $pdo)
 *         {
 *              $database = $factory($pdo);
 *              ...
 *         }
 *     }
 *
 * The result is a \Nada\Database\AbstractDatabase derived object which is aware
 * of the database link it was created from and the DBMS type it connects to.
 * All further interaction starts with this object.
 */
class Factory
{
    // @phpstan-ignore class.notFound (Adapter class is optional)
    public function __invoke(PDO | Adapter $link): AbstractDatabase
    {
        return static::getDatabase($link);
    }

    /**
     * Factory method to create database interface
     *
     * See class description for usage example.
     * @param mixed $link Database link
     * @throws \InvalidArgumentException if no supported DBAL is detected
     */
    static function getDatabase($link): AbstractDatabase
    {
        // Determine the database abstraction layer
        if ($link instanceof \PDO) {
            $class = 'Pdo';
        } elseif ($link instanceof Adapter) { // @phpstan-ignore class.notFound (Adapter class is optional)
            $class = 'LaminasDb';
        } else {
            throw new \InvalidArgumentException('Unsupported link type');
        }

        // Create matching link object
        $class = "Nada\Link\\$class";
        $link = new $class($link);

        // Create and return matching database object
        /** @var class-string<AbstractDatabase> */
        $class = 'Nada\Database\\' . $link->getDbmsSuffix();
        return new $class($link);
    }
}
