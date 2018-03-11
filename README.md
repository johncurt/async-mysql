AsyncMySQL - Asynchronous MySQL Query Manager
=============================================

AsyncMySQL helps manage several asynchronous queries with callbacks on 
success or failure. It can be used by itself to speed up concurrent queries
in a single model, or in conjunction with an asynchronous library such
as Amp or Ratchet.

Background
----------

This library uses mysqli::poll to check for completed queries in its
query pool.  Once a query is completed it calls the appropriate callback.
This library requires you have mysqli and mysqlnd installed. Only mysqlnd
supports the mysqli_poll function.

Installation
------------

`composer require johncurt/async-mysql`

Usage
-----

First instantiate a ConnectionManager.  This is the class that connects
the queries to a database (one connection per query) and polls the
connections to see if they are ready to be reaped. Pass all of your
connection data into it.

`$conn = new \JohnCurt\AsyncMySQL\ConnectionManager('127.0.0.1', 'user', 'pass', 'schema', 3306);` 

Then you instantiate a Query for each query that you will be attaching.
Be sure to pass in callbacks (or null if no callback is needed), making
use of the `use` construct to gain access to variables outside the
scope of the callback.

```
$success1 = function(\mysqli_request $request) use (&$request1) { $request1 = $request;};

$failure1 = function(string $error) use (&$error1) { $error1 = $error;};

$query = new \JohnCurt\AsyncMySQL\Query('SELECT * FROM table WHERE 1', $success1, $failure1);
```

Once the query is instantiated, you can send it off for processing:

`$conn->runQuery($query);`

You can add as many as you want.  When you are ready to get some results,
You can either have the ConnectionManager just reap the ones that are
ready or you can make a blocking call to wait for everything to come
back. Regardless, all of the queries are (have been) run in separate
connections, asynchronously.

`$conn->reapAny();`

or

`$conn->reapAll();` (blocking)

You can also pass in a timeout to the `reapAll` method that will cause it to
stop waiting for queries to finish after the prescribed seconds.

Contact
-------
Feel free to reach out if you have any questions! You can find my
personal website at [https://johnfansler.com] or my PHP blog at
[https://engagedPHP.com].  I would love to hear your comments as well
as about any bugs you might find.

License
-------
Copyright 2018 John C. Fansler

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.