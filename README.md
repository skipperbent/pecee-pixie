# pecee/pixie: Advanced lightweight querybuilder

A lightweight, expressive, framework agnostic query builder for PHP it can also be referred as a Database Abstraction Layer.
Pixie supports MySQL, SQLite and PostgreSQL will handle all your query sanitization, table prefixing among many other things, with a unified API.

The syntax is similar to Laravel's query builder "Eloquent", but with less overhead.

This library is stable, maintained and are used by many sites, including:

- [Holla.dk](https://holla.dk)
- [Dscuz.com](https://dscuz.com)
- [NinjaImg.com](https://ninjaimg.com)
- [BookAndBegin.com](https://bookandbegin.com)

**Requirements:**
- PHP version 5.5 or higher is required.

#### Feedback and development

If you are missing a feature, experience problems or have ideas or feedback that you want us to hear, please feel free to create an issue.

###### Issues guidelines

- Please be as detailed as possible in the description when creating a new issue. This will help others to more easily understand- and solve your issue.
For example: if you are experiencing issues, you should provide the necessary steps to reproduce the error within your description.

- We love to hear out any ideas or feedback to the library.

[Create a new issue here](https://github.com/skipperbent/pecee-pixie/issues)

###### Contribution development guidelines

- Please try to follow the PSR-2 codestyle guidelines.

- Please create your pull requests to the development base that matches the version number you want to change.
For example when pushing changes to version 3, the pull request should use the `v3-development` base/branch.

- Create detailed descriptions for your commits, as these will be used in the changelog for new releases.

- When changing existing functionality, please ensure that the unit-tests working.

- When adding new stuff, please remember to add new unit-tests for the functionality.

#### Credits

This project is based on the original [Pixie project by usmanhalalit](https://github.com/usmanhalalit/pixie) but has some extra features like:

- Easier sub-queries.
- Custom prefix/aliases for tables (prefix.`table`).
- Support for not defining table.
- Better handling of `Raw` objects in `where` statements.
- Performance optimisations.
- Tons of bug fixes.
- Much more...

**Including all the original features like:**

- Query Events
- Nested Criteria
- Sub Queries
- Nested Queries
- Multiple Database Connections.

Most importantly this project is used on many live-sites and maintained.

#### Versions prior to 3.x

Older versions prior to 3.x are available [https://github.com/skipperbent/pixie](https://github.com/skipperbent/pixie).

#### Note

`AliasFacade` used for calling the database-connection as a fixed constant has been removed to increase performance.
If this feature is required in your setup we encourage you to implement your own solution.

## Example
```php
// Make sure you have Composer's autoload file included
require 'vendor/autoload.php';

// Create a connection, once only.
$config = array(
            'driver'    => 'mysql', // Db driver
            'host'      => 'localhost',
            'database'  => 'your-database',
            'username'  => 'root',
            'password'  => 'your-password',
            'charset'   => 'utf8', // Optional
            'collation' => 'utf8_unicode_ci', // Optional
            'prefix'    => 'cb_', // Table prefix, optional
            'options'   => array( // PDO constructor options, optional
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_EMULATE_PREPARES => false,
            ),
        );

$queryBuilder = (new \Pecee\Pixie\Connection('mysql', $config))->getQueryBuilder();
```

**Simple Query:**

The query below returns the row where id = 3, null if no rows.
```PHP
$row = $queryBuilder->table('my_table')->find(3);
```

**Full Queries:**

```PHP
$query = $queryBuilder->table('my_table')->where('name', '=', 'Sana');

// Get result
$query->get();
```

**Query Events:**

After the code below, every time a select query occurs on `users` table, it will add this where criteria, so banned users don't get access.

```PHP
$queryBuilder->registerEvent('before-select', 'users', function(QueryBuilderHandler $qb)
{
    $qb->where('status', '!=', 'banned');
});
```


There are many advanced options which are documented below. Sold? Let's install.

## Installation

Pixie uses [Composer](http://getcomposer.org/doc/00-intro.md#installation-nix) to make things easy.

Learn to use composer and add this to require section (in your composer.json):

```
composer install pecee/pixie
```

## Full Usage API

### Table of Contents

 - [Connection](#connection)
    - [Multiple Connection](#alias)
    - [SQLite and PostgreSQL Config Sample](#sqlite-and-postgresql-config-sample)
 - [Query](#query)
 - [**Select**](#select)
    - [Table prefix](#table-alias)
    - [Get Easily](#get-easily)
    - [Multiple Selects](#multiple-selects)
    - [Select Distinct](#select-distinct)
    - [Get All](#get-all)
    - [Get First Row](#get-first-row)
    - [Get Rows Count](#get-rows-count)
    - [Selects With Sub-Queries](#select-with-sub-queries)
 - [**Where**](#where)
    - [Where In](#where-in)
    - [Where Between](#where-between)
    - [Where Null](#where-null)
    - [Grouped Where](#grouped-where)
 - [Group By and Order By](#group-by-and-order-by)
 - [Having](#having)
 - [Limit and Offset](#limit-and-offset)
 - [Join](#join)
    - [Multiple Join Criteria](#multiple-join-criteria)
 - [Raw Query](#raw-query)
    - [Raw Expressions](#raw-expressions)
 - [**Insert**](#insert)
    - [Batch Insert](#batch-insert)
    - [Insert with ON DUPLICATE KEY statement](#insert-with-on-duplicate-key-statement)
 - [**Update**](#update)
 - [**Delete**](#delete)
 - [Transactions](#transactions)
 - [Get Built Query](#get-built-query)
 - [Sub Queries and Nested Queries](#sub-queries-and-nested-queries)
 - [Get PDO Instance](#get-pdo-instance)
 - [Fetch results as objects of specified class](#fetch-results-as-objects-of-specified-class)
 - [Query Events](#query-events)
    - [Available Events](#available-events)
    - [Registering Events](#registering-events)
    - [Removing Events](#removing-events)
    - [Some Use Cases](#some-use-cases)
    - [Notes](#notes)

___

## Connection
Pixie supports three database drivers, MySQL, SQLite and PostgreSQL.
You can specify the driver during connection and the associated configuration when creating a new connection. You can also create multiple connections, but you can use alias for only one connection at a time.;

```php
// Make sure you have Composer's autoload file included
require 'vendor/autoload.php';

$config = [
    'driver'    => 'mysql', // Db driver
    'host'      => 'localhost',
    'database'  => 'your-database',
    'username'  => 'root',
    'password'  => 'your-password',
    'charset'   => 'utf8', // Optional
    'collation' => 'utf8_unicode_ci', // Optional
    'prefix'    => 'cb_', // Table prefix, optional
];

// Creates new connection
$connection = new \Pecee\Pixie\Connection('mysql', $config);

// Get the query-builder object
$queryBuilder = $connection->getQueryBuilder();

// Run query
$person = $queryBuilder
            ->table('persons')
            ->where('name', '=', 'Bobby')
            ->first();
```

`$connection` here is optional, if not given it will always associate itself to the first connection, but it can be useful when you have multiple database connections.

### SQLite and PostgreSQL Config Sample

The example below is for use with sqlite-databases.

```php
$queryBuilder = new \Pecee\Pixie\Connection('sqlite', [
    'driver'   => 'sqlite',
    'database' => 'your-file.sqlite',
    'prefix'   => 'cb_',
]);
```

The example below is for pgsql databases.

```php
$queryBuilder = new \Pecee\Pixie\Connection('pgsql', [
    'driver'   => 'pgsql',
    'host'     => 'localhost',
    'database' => 'your-database',
    'username' => 'postgres',
    'password' => 'your-password',
    'charset'  => 'utf8',
    'prefix'   => 'cb_',
    'schema'   => 'public',
]);
```

## Query

It is recommend to use `table()` method before every query, except raw `query()`.
To select from multiple tables just pass an array.

However this is not required.

```php
$queryBuilder->table(array('mytable1', 'mytable2'));
```

### Table alias

You can easily set the table alias by using

```php
$queryBuilder
    ->table(['table1' => 'foo1'])
    ->join('table2', 'table2.person_id', '=', 'foo1.id');
```

You can change the alias anytime by using

```php
$queryBuilder->alias($table, $alias);
```

Output:

```sql
SELECT *
FROM `table1` AS foo1
INNER JOIN `cb_table2` ON `cb_table2`.`person_id` = `cb_foo1`.`id`
```

### Get Easily

The query below returns the (first) row where id = 3, null if no rows.

```php
$row = $queryBuilder
            ->table('my_table')
            ->find(3);
```

Access your row like, `echo $row->name`. If your field name is not `id` then pass the field name as second parameter `$queryBuilder->table('my_table')->find(3, 'person_id');`.

The query below returns the all rows where name = 'Sana', null if no rows.

```php
$result = $queryBuilder
            ->table('my_table')
            ->findAll('name', 'Sana');
```


### Select

#### Select single field

```php
$queryBuilder->table('my_table')->select('*');
```

#### Select multiple fields

```php
$queryBuilder->table('my_table')->select(array('field1', 'field2'));
```

#### Multiple selects

```php
$queryBuilder
    ->select(
        [
            'mytable.myfield1',
            'mytable.myfield2',
            'another_table.myfield3'
        ]
    );
```

Using select method multiple times `select('a')->select('b')` will also select `a` and `b`. Can be useful if you want to do conditional selects (within a PHP `if`).


#### Select distinct

```php
$queryBuilder->selectDistinct(array('mytable.myfield1', 'mytable.myfield2'));
```


#### Get all

Returns an array.

```php
$results = $queryBuilder
                ->table('my_table')
                ->where('name', '=', 'Sana')
                ->get();
```

You can loop through it like:

```php
foreach ($results as $row) {
    echo $row->name;
}
```

#### Get First Row

```php
$row = $queryBuilder
            ->table('my_table')
            ->where('name', '=', 'Sana')
            ->first();
```
Returns the first row, or null if there is no record. Using this method you can also make sure if a record exists. Access these like `echo $row->name`.


#### Get Rows Count

```php
$queryBuilder
    ->table('my_table')
    ->where('name', '=', 'Sana')
    ->count();
```

#### Select With Sub-Queries

```php
// Creates the first sub-query

$subQuery1 =
    $queryBuilder
        ->table('mail')
        ->select(
            $queryBuilder->raw('COUNT(*)')
        );

// Create the second sub-query

$subQuery2 =
    $queryBuilder
        ->table('event_message')
        ->select(
            $queryBuilder->raw('COUNT(*)')
        );

// Executes the query which uses the subqueries as fields
$count =
    $queryBuilder
        ->select(
            $queryBuilder->subQuery($subQuery1, 'row1'),
            $queryBuilder->subQuery($subQuery2, 'row2')
        )
        ->first();
```

The example above will output a SQL-query like this:

```sql
SELECT
  (SELECT COUNT(*)
   FROM `cb_mail`) AS row1,

  (SELECT COUNT(*)
   FROM `cb_event_message`) AS row2
LIMIT 1
```

You can also easily create a subjquery within the `where` statement:

```php
$queryBuilder->where($queryBuilder->subQuery($subQuery), '!=', 'value');
```

### Where

Basic syntax is `(fieldname, operator, value)`, if you give two parameters then `=` operator is assumed. So `where('name', 'usman')` and `where('name', '=', 'usman')` is the same.

```php
$queryBuilder
    ->table('my_table')
    ->where('name', '=', 'usman')
    ->whereNot('age', '>', 25)
    ->orWhere('type', '=', 'admin')
    ->orWhereNot('description', 'LIKE', '%query%');
```


#### Where In

```php
$queryBuilder
    ->table('my_table')
    ->whereIn('name', array('usman', 'sana'))
    ->orWhereIn('name', array('heera', 'dalim'));

$queryBuilder
    ->table('my_table')
    ->whereNotIn('name', array('heera', 'dalim'))
    ->orWhereNotIn('name', array('usman', 'sana'));
```

#### Where Between

```php
$queryBuilder
    ->table('my_table')
    ->whereBetween('id', 10, 100)
    ->orWhereBetween('status', 5, 8);
```

#### Where Null

```php
$queryBuilder
    ->table('my_table')
    ->whereNull('modified')
    ->orWhereNull('field2')
    ->whereNotNull('field3')
    ->orWhereNotNull('field4');
```

#### Grouped Where

Sometimes queries get complex, where you need grouped criteria, for example `WHERE age = 10 and (name like '%usman%' or description LIKE '%usman%')`.

Pixie allows you to do so, you can nest as many closures as you need, like below.

```php
$queryBuilder
    ->table('my_table')
    ->where('my_table.age', 10)
    ->where(function(QueryBuilderHandler $qb) {
        $qb->where('name', 'LIKE', '%pecee%');

        // You can provide a closure on these wheres too, to nest further.
        $qb->orWhere('description', 'LIKE', '%usman%');
    });
```

### Group By and Order By

```php
$query = $queryBuilder
            ->table('my_table')
            ->groupBy('age')
            ->orderBy('created_at', 'ASC');
```

#### Multiple Group By

```php
$queryBuilder
    ->groupBy(array('mytable.myfield1', 'mytable.myfield2', 'another_table.myfield3'));
    ->orderBy(array('mytable.myfield1', 'mytable.myfield2', 'another_table.myfield3'));
```

Using `groupBy()` or `orderBy()` methods multiple times `groupBy('a')->groupBy('b')` will also group by first `a` and than `b`. Can be useful if you want to do conditional grouping (within a PHP `if`). Same applies to `orderBy()`.

### Having

```php
$queryBuilder
    ->having('total_count', '>', 2)
    ->orHaving('type', '=', 'admin');
```

### Limit and Offset

```php
$queryBuilder
    ->limit(30);
    ->offset(10);
```

### Join

```php
$queryBuilder
    ->table('my_table')
    ->join('another_table', 'another_table.person_id', '=', 'my_table.id')
```

Available methods,

 - join() or innerJoin
 - leftJoin()
 - rightJoin()

If you need `FULL OUTER` join or any other join, just pass it as 5th parameter of `join` method.

```php
$queryBuilder
    ->join('another_table', 'another_table.person_id', '=', 'my_table.id', 'FULL OUTER')
```

#### Multiple Join Criteria

If you need more than one criterion to join a table then pass a closure as second parameter.

```php
$queryBuilder
    ->join('another_table', function($table)
    {
        $table->on('another_table.person_id', '=', 'my_table.id');
        $table->on('another_table.person_id2', '=', 'my_table.id2');
        $table->orOn('another_table.age', '>', $queryBuilder->raw(1));
    })
```

### Raw Query

You can always perform raw queries, if needed.

```php
$query = $queryBuilder->query('SELECT * FROM persons WHERE age = 12');

$kids = $query->get();
```

You can also pass custom bindings

```php
$queryBuilder
    ->query('SELECT * FROM persons WHERE age = ? AND name = ?', array(10, 'usman'));
```

#### Raw Expressions

When you wrap an expression with `raw()` method, Pixie doesn't try to sanitize these.

```php
$queryBuilder
    ->table('my_table')
    ->select($queryBuilder->raw('count(cb_my_table.id) as tot'))
    ->where('value', '=', 'Ifrah')
    ->where($queryBuilder->raw('DATE(?)', 'now'))
```


___

**NOTE:** Queries that run through `query()` method are not sanitized until you pass all values through bindings. Queries that run through `raw()` method are not sanitized either, you have to do it yourself. And of course these don't add table prefix too, but you can use the `addTablePrefix()` method.

### Insert

```php
$data = [
    'name' => 'Sana',
    'description' => 'Blah'
];

$insertId = $queryBuilder
                ->table('my_table')
                ->insert($data);
```

`insert()` method returns the insert id.

#### Batch Insert

```php
$data = [
    array(
        'name'        => 'Sana',
        'description' => 'Blah'
    ),
    array(
        'name'        => 'Usman',
        'description' => 'Blah'
    ),
];

$insertIds = $queryBuilder
                ->table('my_table')
                ->insert($data);
```

In case of batch insert, it will return an array of insert ids.

#### Insert with ON DUPLICATE KEY statement

```php
$data = [
    'name'    => 'Sana',
    'counter' => 1
];

$dataUpdate = [
    'name'    => 'Sana',
    'counter' => 2
];

$insertId =
    $queryBuilder
        ->table('my_table')
        ->onDuplicateKeyUpdate($dataUpdate)
        ->insert($data);
```

### Update

```php
$data = [
    'name'        => 'Sana',
    'description' => 'Blah'
];

$queryBuilder
    ->table('my_table')
    ->where('id', 5)
    ->update($data);
```

Will update the name field to Sana and description field to Blah where id = 5.

### Delete

```php
$queryBuilder
    ->table('my_table')
    ->where('id', '>', 5)
    ->delete();
```

Will delete all the rows where id is greater than 5.

### Transactions

Pixie has the ability to run database "transactions", in which all database
changes are not saved until committed. That way, if something goes wrong or
differently then you intend, the database changes are not saved and no changes
are made.

Here's a basic transaction:

```php
$queryBuilder->transaction(function (QueryBuilderHandler $qb) {
    $qb
        ->table('my_table')
        ->insert(array(
            'name' => 'Test',
            'url' => 'example.com'
        );

    $qb
        ->table('my_table')
        ->insert(array(
            'name' => 'Test2',
            'url' => 'example.com'
        ));
});
```

If this were to cause any errors (such as a duplicate name or some other such
error), neither data set would show up in the database. If not, the changes would
be successfully saved.

If you wish to manually commit or rollback your changes, you can use the
`commit()` and `rollback()` methods accordingly:

```php
$queryBuilder
    ->transaction(function (qb)
        {
            $queryBuilder
                ->table('my_table')
                ->insert($data);

            // Commit changes (data will be saved)

            $queryBuilder->commit();

            // Rollback changes (data would be rejected)
            $queryBuilder->rollback();
        }
    );
```

### Get Built Query

Sometimes you may need to get the query string, it's possible.

```php
$queryHandler =
    $queryBuilder
        ->table('my_table')
        ->where('id', '=', 3)
        ->getQuery();
```

`getQuery()` will return a `QueryBuilderHandler` object.

You can use this to get the SQL, bindings or raw SQL.

```php
$queryHandler->getSql();
```

Calling `getSql()` will return the SQL-query without any processing.

```sql
SELECT * FROM my_table where `id` = ?
```

You can easily get any bindings on the query by calling the `getBindings()`method.

**Example:**

```php
$queryHandler->getBindings();
```

You can also get the raw SQL-query directly by calling the `getRawSql()` method.

**Example:**

```php
$queryHandler->getRawSql();
```

Calling the `getRawSql()` method will return a query including bindings like this.

```sql
SELECT * FROM my_table where `id` = 3
```

### Sub Queries and Nested Queries

Rarely but you may need to do sub queries or nested queries. Pixie is powerful enough to do this for you. You can create different query objects and use the `$queryBuilder->subQuery()` method.

```php
$subQuery =
    $queryBuilder
        ->table('person_details')
        ->select('details')
        ->where('person_id', '=', 3);


$query =
    $queryBuilder
        ->table('my_table')
        ->select('my_table.*')
        ->select(
            $queryBuilder->subQuery($subQuery, 'table_alias1')
        );

$nestedQuery =
    $queryBuilder
        ->table(
            $queryBuilder->subQuery($query, 'table_alias2')
        )
        ->select('*');

// Execute query
$result = $nestedQuery->get();
```

This will produce a query like this:

```sql
SELECT *
FROM
  (SELECT `cb_my_table`.*,

     (SELECT `details`
      FROM `cb_person_details`
      WHERE `person_id` = 3) AS table_alias1
   FROM `cb_my_table`) AS table_alias2
```

**NOTE:**

Pixie doesn't use bindings for sub queries and nested queries. It quotes values with PDO's `quote()` method.

### Get PDO Instance

If you need to get the PDO instance you can do so.

```php
$queryBuilder->getConnection()->getPdoInstance();
```

### Fetch results as objects of specified class

Simply call `asObject` query's method.

```php
$queryBuilder
    ->table('my_table')
    ->asObject('SomeClass', array('ctor', 'args'))
    ->first();
```

Furthermore, you may fine-tune fetching mode by calling `setFetchMode` method.

```php
$queryBuilder
    ->table('my_table')
    ->setFetchMode(PDO::FETCH_COLUMN|PDO::FETCH_UNIQUE)
    ->get();
```

### Query Events

Pixie comes with powerful query events to supercharge your application. These events are like database triggers, you can perform some actions when an event occurs, for example you can hook `after-delete` event of a table and delete related data from another table.

#### Available Events

 - after-*
 - before-*
 - before-select
 - after-select
 - before-insert
 - after-insert
 - before-update
 - after-update
 - before-delete
 - after-delete

#### Registering Events

```php
$queryBuilder->registerEvent('before-select', 'users', function(QueryBuilderHandler $qb)
{
    $qb->where('status', '!=', 'banned');
});
```
Now every time a select query occurs on `users` table, it will add this where criteria, so banned users don't get access.

The syntax is `registerEvent('event type', 'table name', action in a closure)`.

If you want the event to be performed when **any table is being queried**, provide `':any'` as table name.

**Other examples:**

After inserting data into `my_table`, details will be inserted into another table

```php
$queryBuilder->registerEvent('after-insert', 'my_table', function(QueryBuilderHandler $qb, $insertId)
{
    $qb
        ->table('person_details')->insert(array(
        'person_id' => $insertId,
        'details' => 'Meh',
        'age' => 5
    ));
});
```

Whenever data is inserted into `person_details` table, set the timestamp field `created_at`, so we don't have to specify it everywhere:

```php
$queryBuilder->registerEvent('after-insert', 'person_details', function(QueryBuilderHandler $qb, $insertId)
{
    $qb
        ->table('person_details')
        ->where('id', $insertId)
        ->update([
            'created_at' => date('Y-m-d H:i:s')
        ]);
});
```

After deleting from `my_table` delete the relations:

```php
$queryBuilder->registerEvent('after-delete', 'my_table', function(QueryBuilderHandler $qb, $queryObject)
{
    $bindings = $queryObject->getBindings();
    $qb
        ->table('person_details')
        ->where('person_id', $binding[0])
        ->delete();
});
```

Pixie passes the current instance of query builder as first parameter of your closure so you can build queries with this object, you can do anything like usual query builder (`QB`).

If something other than `null` is returned from the `before-*` query handler, the value will be result of execution and DB will not be actually queried (and thus, corresponding `after-*` handler will not be called ether).

Only on `after-*` events you get three parameters: **first** is the query builder, **third** is the execution time as float and **the second** varies:

 - On `after-select` you get the `results` obtained from `select`.
 - On `after-insert` you get the insert id (or array of ids in case of batch insert)
 - On `after-delete` you get the [query object](#get-built-query) (same as what you get from `getQuery()`), from it you can get SQL and Bindings.
 - On `after-update` you get the [query object](#get-built-query) like `after-delete`.

#### Removing Events

```php
$queryBuilder->removeEvent('event-name', 'table-name');
```

#### Some Use Cases

Here are some cases where Query Events can be extremely helpful:

 - Restrict banned users.
 - Get only `deleted = 0` records.
 - Implement caching of all queries.
 - Trigger user notification after every entry.
 - Delete relationship data after a delete query.
 - Insert relationship data after an insert query.
 - Keep records of modification after each update query.
 - Add/edit created_at and updated _at data after each entry.

#### Notes
 - Query Events are set as per connection basis so multiple database connection don't create any problem, and creating new query builder instance preserves your events.
 - Query Events go recursively, for example after inserting into `table_a` your event inserts into `table_b`, now you can have another event registered with `table_b` which inserts into `table_c`.
 - Of course Query Events don't work with raw queries.

___
If you find any typo then please edit and send a pull request.

&copy; 2016 [Muhammad Usman](http://usman.it/), [Muhammad Usman]

&copy; 2016 Simon Sessingø - [Pecee.dk](http://pecee.dk/).

## Licence

Licensed under the MIT licence.

### The MIT License (MIT)

Copyright (c) 2016 Simon Sessingø / simple-php-router

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.