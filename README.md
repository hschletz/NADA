NADA - Not Another Database Abstraction layer
=============================================

About NADA
----------

NADA is a high level SQL abstraction library for PHP. It complements and
operates on top of a conventional database abstraction layer (currently
supported: [PDO](http://php.net/manual/en/book.pdo.php),
[Zend_Db (Zend Framework 1.x)](http://framework.zend.com),
[Zend\Db (Zend Framework 2.x)](http://framework.zend.com) and
[MDB2](http://pear.php.net/package/MDB2)), allowing easy integration into
existing applications.

NADA provides SQL abstraction methods for different database backends (currently
supported: [PostgreSQL](http://postgresql.org), [MySQL](http://mysql.org) and
[SQLite](http://sqlite.org/)) that are not available in typical database
abstraction layers which often just provide a unified interface to a database
connection. In contrast, NADA supplies a unified interface to some SQL
operations which would otherwise require DBMS-specific workarounds in the code:

- Querying and altering the database structure (tables, columns, datatypes,
  views)
- Generating SQL code fragments for non-portable functions
- Setting compatibility flags to force predictable, standards-compliant behavior

Some database abstraction layers already offer some of that high level
functionality, but that is tightly coupled to their database connection method.
To make use of it, the entire application has to be built around a particular
library (which may or may not suit the needs of the project) or a second
database connection has to be established just for the higher level operations
(which would be inefficient and somewhat messy because 2 different APIs are
involved). NADA provides a clean and lightweight solution by operating on top
of an existing database connection object of any supported type.

NADA's modular and extensible design makes it easy to add support for other DBMS
and database abstraction layers. The files src/Database/AbstractDatabase.php and
src/Link/AbstractLink.php contain instructions for this.


License
-------

NADA is released under a revised BSD license. You can find the full license in
the LICENSE file in the same directory that contains this file.


Installation
------------

NADA can be installed via composer. Just add it to your project's composer.json:

    {
        "require": {
            "hschletz/nada": "*"
        }
    }

The Nada class will be available via the composer-generated autoloader. If you
don't install via composer, your code has to implement it's own
[PSR-4](http://www.php-fig.org/psr/psr-4/) compliant autoloader or load classes
manually.


Usage
-----

Example for PDO:

    $link = new \PDO($dsn, $user, $password);
    $database = \Nada\Factory::getDatabase($link);

The *getDatabase()* method detects the type of abstraction layer of the passed
connection object and the DBMS type it connects to. It then sets up and returns
an object of an appropriate subclass of *Nada\Database\AbstractDatabase*, which
is the main interface for all subsequent operations.

No extra database connection is initiated - it is up to the application to
connect to the database as usual. NADA can safely reuse any connection. No
changes to the connection object are made unless explicitly requested.


Available classes
-----------------

Databases, tables and columns are represented by DBMS-specific subclasses of
abstract classes:

- Nada\Database\AbstractDatabase
- Nada\Table\AbstractTable
- Nada\Column\AbsractColumn

These subclasses may contain extra methods not available in the base class. The
methods to retrieve these objects always return objects of the proper subclass.

Indexes are represented by *Nada\Index* which does not have a subclass because
it does not have any DBMS-specific functionality.

An additional base class, *Nada\Link\AbstractLink*, provides a unified interface
for database connections. Its subclasses are wrappers around the database
connection object passed to the factory method. This interface is very limited
and not intended for use by the application. It is used internally by NADA for
all database calls. Unless you are developing for NADA itself, forget about this
class.


Error handling
--------------

NADA throws an exception whenever an error occurs. If a database call fails and
the connection object is set up to throw exceptions on error, the native
exception is thrown. Otherwise NADA will detect the error and throw its own
exception.


Caveats
-------

SQLite does not support altering and dropping columns and primary keys directly.
Instead, NADA's methods re-create the table with the altered structure. Data and
primary keys are preserved, but other attributes (constraints etc.) are not.
This applies to all columns of the same table.


Migrating from older versions
-----------------------------

There have been significant changes to the code which require updates to
applications that used NADA versions from before June 2016:

- The code no longer takes care of class loading. Use composer (recommended) or
  take care of class loading by yourself.
- All classes have been renamed and moved to a namespace hierarchy.
- The factory class and method have changed, see the usage example above.
- The datatype constants have been renamed and moved to
  Nada\Column\AbstractColumn.


More documentation
------------------


More comprehensive documentation is yet to be written. Until then, refer to the
comments within the NADA source code - all methods are extensively documented
there. You can also use tools like [ApiGen](http://apigen.org) or
[phpDocumentor](http://www.phpdoc.org/) to extract and compile the class
documentation.
