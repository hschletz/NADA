NADA - Not Another Database Abstraction layer
=============================================

About NADA
----------

NADA is a high level SQL abstraction library for PHP. It complements and
operates on top of a conventional database abstraction layer (currently
supported: [PDO](http://php.net/manual/en/book.pdo.php),
[Zend_Db](http://framework.zend.com) and
[MDB2](http://pear.php.net/package/MDB2)), allowing easy integration into
existing applications.

NADA provides SQL abstraction methods for different database backends (currently
supported: [PostgreSQL](http://postgresql.org) and [MySQL](http://mysql.org))
that are not available in typical database abstraction layers which (with the
notable exception of MDB2) just provide a unified interface to a database
connection. In contrast, NADA supplies a unified interface to some SQL
operations which would otherwise require DBMS-specific workarounds in the code:

- Querying and altering the database structure (tables, columns, datatypes)
- Generating SQL code fragments for non-portable functions
- Setting compatibility flags to force predictable, standards-compliant behavior

NADA's modular and extensible design makes it easy to add support for other DBMS
and database abstraction layers. The files Database.php and Link.php contain
instructions for this.


License
-------

NADA is released under a revised BSD license. You can find the full license in
the LICENSE file in the same directory that contains this file.


Usage
-----

The NADA directory does not need to be in the include path. Once the script
Nada.php is loaded, the factory method will find and include all required files.

Example for PDO:

    require_once 'path/to/NADA/Nada.php';
    $pdo = new PDO($dsn, $user, $password);
    $nada_database = Nada::factory($pdo);

The *factory()* method detects the type of abstraction layer of the passed
connection object and the DBMS type it connects to. It then sets up and returns
an object of an appropriate subclass of *Nada_Database*, which is the main
interface for all subsequent operations.

No extra database connection is initiated - it is up to the application to
connect to the database as usual. NADA can safely reuse any connection. No
changes to the connection object are made unless explicitly requested.

Available classes
-----------------

All classes representing database objects are abstract classes, i.e. a
DBMS-specific subclass is always used. This subclass may contain extra methods
not available in the base class.

- **Nada_Database:** Base class that represents the database.
- **Nada_Table:** Base class that represents a table.
- **Nada_Column:** Base class that represents a column.

An additional base class, *Nada_Link*, provides a unified interface for database
connections. Its subclasses are wrappers around the database connection object
passed to the factory method. This interface is very limited and not intended
for use by the application. It is used internally by NADA for all database
calls. Unless you are developing for NADA itself, forget about this class.


Error handling
--------------

NADA throws an exception whenever an error occurs. If a database call fails and
the connection object is set up to throw exceptions on error, the native
exception is thrown. Otherwise NADA will detect the error and throw its own
exception.


More documentation
------------------


More comprehensive documentation is yet to be written. Until then, refer to the
comments within the NADA source code - all methods are extensively documented
there. You can also use [phpDocumentor 2](http://www.phpdoc.org/) to extract and
compile the class documentation.
