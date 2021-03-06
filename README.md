PHP Models
============================

A PHP library that allows you to easily create and define your models using PDO

## Features
- PDO wrapper
- `FETCH_INTO` made easy
- Model helper

## Installation
```term
$ composer require scopweb/php-models
```

## Usage
### DB.php
The main `\Models\DB` class is a PDO wrapper used to make CRUD much easier. It is a forked code from the [php-pdo-wrapper-class](https://github.com/lonalore/php-pdo-wrapper-class)
```php
// connect to your database. Store the $db instance globally -- you only need to connect to your db ONCE!
// driver add support for 'mssql' with extension sqlsrv [https://www.microsoft.com/en-us/download/details.aspx?id=20098]
$db = new \Models\DB(DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, DB_PORT, DB_DRIVER);
```

Available **CRUD** methods
- `$db->insert($sql, $binds)` or `$db->insert($table, $values)`
- `$db->query($sql, $binds)`
- `$db->query_row($sql, $binds)` (same with `query` but will return single row)
- `$db->update($sql, $binds)` or `$db->update('table', $values)`
- `$db->delete($sql, $binds)` or `$db->delete('table', $filters)`

The default style is `PDO::FETCH_OBJ`

Example:
```php
$users = $db->query("SELECT * FROM users WHERE active = 1 AND username = :username", array('username' => 'lodev09'));
var_dump($users);
```

### Model.php
The `\Models\Model` class is a parent class that can be inherited to a **Model** class. Inheriting this class allows you to automatically map the result "row" into your model class (table). This class basically uses the `PDO::FETC_INTO` style and made it easier for you. Here are the steps to link your table into a class:

1. Initiate the `\Models\DB` instance (see above)
```php
$db = new \Models\DB(DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, DB_PORT, DB_DRIVER);
\Models\Model::set_db($db);
```

2. Create your model class. For example, a `User.php` class
```php
namespace Models;

class User extends Model {
    public function get_name() {
        return $this->name;
    }
}
```
3. Register your table to your custom class
```php
// somewhere in your init.php
\Models\User::register('users');
```

Now, you can directly get the `User` instance from a query. Example:
```php
$user = \Models\User::query_row("SELECT id, name FROM users WHERE id = 1 AND active = 1");
// you can call the get_name() method now
if ($user) {
    $name = $user->get_name();
    echo 'His name is '.$name;
}
```

## Feedback
All bugs, feature requests, pull requests, feedback, etc., are welcome. Visit my site at [www.lodev09.com](http://www.lodev09.com "www.lodev09.com") or email me at [lodev09@gmail.com](mailto:lodev09@gmail.com)

## Credits
&copy; 2018 - Coded by Jovanni Lo / [@lodev09](http://twitter.com/lodev09)

## License
Released under the [MIT License](http://opensource.org/licenses/MIT).
See [LICENSE](LICENSE) file.
