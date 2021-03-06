<?php
/**
 * @file
 * A minimal extension for PHP's PDO class designed to make running SQL
 * statements easier.
 *
 * This is a forked code from lonalore/php-pdo-wrapper-class
 *
 * http://www.imavex.com/php-pdo-wrapper-class/
 * https://github.com/lonalore/php-pdo-wrapper-class/blob/master/db.class.php
 *
 * Project Overview
 *
 * This project provides a minimal extension for PHP's PDO (PHP Data Objects)
 * class designed for ease-of-use and saving development time/effort. This is
 * achieved by providing methods - SELECT, INSERT, UPDATE, DELETE - for quickly
 * building common SQL statements, handling exceptions when SQL errors are
 * produced, and automatically returning results/number of affected rows for
 * the appropriate SQL statement types.
 *
 * System Requirements:
 * - PHP 5
 * - PDO Extension
 * - Appropriate PDO Driver(s) - PDO_SQLITE, PDO_MYSQL, PDO_PGSQL
 * - Only MySQL, SQLite, and PostgreSQL database types are currently supported.
 *
 *
 * This is a forked code from lodev09/php-models
 * Add support for mssql with dns connection
 *
 * System Requirements:
 */

namespace Models;

/**
 * Class DB.
 */

class DB extends \PDO {
    private $_error;
    private $_sql;
    private $_bind;
    private $_error_callback;
    private $_prefix;

    private $_host;
    private $_database;
    private $_username;
    private $_password;
    
    // SCP
    private $_drivers; 
    private $_table; 


    /**
    * Class constructor.
    *
    * @param string $dsn
    *  More information can be found on how to set the dsn parameter by following
    *  the links provided below.
    *
    *  - MySQL - http://us3.php.net/manual/en/ref.pdo-mysql.connection.php
    *  - SQLite - http://us3.php.net/manual/en/ref.pdo-sqlite.connection.php
    *  - PostreSQL - http://us3.php.net/manual/en/ref.pdo-pgsql.connection.php
    *  - MSSQL - https://www.php.net/manual/es/ref.pdo-sqlsrv.php
    *
    * @param string $user
    *  Username for database connection.
    *
    * @param string $passwd
    *  Password for database connection.
    *
    * @param string $prefix
    *  Prefix for database tables.
    */
    public function __construct($host = '', $database = '', $username = '', $password = '', $port = 3306, $driver = 'mysql', $pdo_options = null) {
        $options = [
            \PDO::ATTR_PERSISTENT => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ];
        
        if ($pdo_options) {
            if (!is_array($pdo_options)) {
                throw new \PDOException('PDO options should be an array');
            }

            $options = array_merge($options, $pdo_options);
        }

        $this->_host = $host;
        $this->_database = $database;
        $this->_username = $username;
        $this->_password = $password;
               
        //SCP
        $this->_drivers  = $driver;
        
        //SCP ADD MSSQL DRIVER CASE
        try {
            switch ($driver) {
                case 'mysql':
                    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8";
                    break;
                case 'mssql': 
                    //$dsn = "sqlsrv:Server=$host;Database=$database;charset=utf8";
                    $dsn ="odbc:Driver={SQL Server};Server=$host;Database=$database;charset=utf8";                   
                    break;     
            }

            parent::__construct($dsn, $username, $password, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public function sql_is($sql, $what) {
        $what = is_array($what) ? $what : [$what];
        return preg_match('/^\s*('.implode('|', $what).')\s+/i', $sql) ? true : false;
    }

    /**
    * INSERT statement.
    *
    * @param string $table
    *  Table name or INSERT statement
    *
    * @param array $info
    *  Associative array with field names and values
    *
    * @return array|bool|int
    *  If no SQL errors are produced, this method will return with the the last
    *  inserted ID. Otherwise 0.
    */
    public function insert($sql = '', $info = []) {
        if ($this->sql_is($sql, 'insert')) {
            return $this->run($sql, $info);
        } else {
            $table = $this->_prefix.$sql;
               
            //SCP
            $this->_table = $table;  //SCP save the table name  
            
            $fields = $this->get_fields($table, array_keys($info));
            
            //SCP
            if( $this->_drivers == 'mssql' ){
                 $sql = "INSERT INTO $table ([".implode("], [", $fields)."]) ";
            }else{
                 $sql = "INSERT INTO $table (`".implode("`, `", $fields)."`) ";
            }
            
            //$sql = "INSERT INTO $table (`".implode("`, `", $fields)."`) ";
            $sql .= "VALUES (:".implode(", :", $fields).");";

            $bind = [];
            foreach ($fields as $field) {
                $bind[":$field"] = $info[$field];
            }

            return $this->run($sql, $bind);
        }
    }

    /**
     * Allias of run
    */
    public function query($sql, $bind = null, $args = null, $style = null) {
        return $this->run($sql, $bind, $args, $style);
    }

    /**
    * UPDATE statement.
    *
    * @param string $sql
    *  Table name.
    *
    * @param array $info
    *  Associated array with fields and their values.
    *
    * @param string $where
    *  WHERE conditions.
    *
    * @param mixed $bind
    *  Bind parameters as string or array.
    *
    * @return array|bool|int
    *  If no SQL errors are produced, this method will return the number of rows
    *  affected by the UPDATE statement.
    */
    public function update($sql_or_table = '', $info = [], $where = '', $bind = '') {
        if ($this->sql_is($sql_or_table, 'update')) {
            return $this->run($sql_or_table, $info);
        } else if (is_array($info)) {
            $sql_or_table = $this->_prefix . $sql_or_table;
            
            //SCP
            $this->_table = $sql_or_table;  //SCP save the table name 
            
            $fields = $this->get_fields($sql_or_table, array_keys($info));
            $fieldSize = sizeof($fields);
            $sql = "UPDATE $sql_or_table SET ";
            
            //SCP
            if( $this->_drivers == 'mssql'){ 
                for ($f = 0; $f < $fieldSize; ++$f) {
                    if ($f > 0) {
                        $sql .= ', ';
                    }
                    $sql .= '`'.$fields[$f].'` = :update_'.$fields[$f];
                }
            }else{
                for ($f = 0; $f < $fieldSize; ++$f) {
                    if ($f > 0) {
                        $sql .= ', ';
                    }
                    $sql .= '['.$fields[$f].'] = :update_'.$fields[$f];
                }
            }
            
            /*
            for ($f = 0; $f < $fieldSize; ++$f) {
                if ($f > 0) {
                    $sql .= ', ';
                }
                $sql .= '`'.$fields[$f].'` = :update_'.$fields[$f];
            }
            */

            if ($where) {
                $sql .= " WHERE $where;";
            }

            $bind = $this->cleanup($bind);
            foreach ($fields as $field) {
                $bind[":update_$field"] = $info[$field];
            }

            return $this->run($sql, $bind);
        } else {
            $this->_error = 'Invalid update parameters';
            $this->debug();
            return false;
        }
    }

    /**
    * DELETE statement.
    *
    * @param string $sql
    *  Table name or DELETE statement
    *
    * @param string $info
    *  Where conditions or bind information
    *
    * @param mixed $bind
    *  Bind parameters as string or array.
    *
    * @return array
    *  If no SQL errors are produced, this method will return the number of rows
    *  affected by the DELETE statement.
    */
    public function delete($sql_or_table = '', $info = '', $bind = '') {
        if ($this->sql_is($sql_or_table, 'delete')) {
            return $this->run($sql_or_table, $info);
        } else {
            $sql = $this->_prefix.$sql_or_table;
            $sql = "DELETE FROM $sql WHERE $info;";
            return $this->run($sql, $bind);
        }
    }

    /**
     * Fetch a single row
     *
     * @param string $sql
     *  Query string
     *
     * @param mixed $bind
     *  Bind parameters as string or array
     *
     * @param mixed $args
     *  Additional arguments for fetch
     *
     * @param long $style
     *  Fetch style
     *
     * @return mixed
     * If no SQL errors are procuded, this method will return the object. Otherwise returns false.
     */
    public function query_row($sql, $bind = null, $args = null, $style = null) {
        $this->_sql = trim($sql);
        $this->_bind = $this->cleanup($bind);
        $this->_error = '';

        try {
            $pdostmt = $this->prepare($this->_sql);

            if ($pdostmt->execute($this->_bind) !== false) {
                if (isset($args)) {
                    $pdostmt->setFetchMode($style ? : \PDO::FETCH_CLASS, $args);
                } else {
                    $pdostmt->setFetchMode($style ? : \PDO::FETCH_OBJ);
                }

                return $pdostmt->fetch();
            }
        } catch (\PDOException $e) {
            $this->_error = $e->getMessage();
            $this->debug();
            return false;
        }

        return false;
    }

    /**
    * This method is used to run free-form SQL statements
    *
    * @param string $sql
    *  SQL query.
    *
    * @param mixed $bind
    *  Bind parameters as string or array.
    *
    * @param mixed $style
    *  Fetch style
    *
    * @param mixed $args
    *  Additional arguments
    *
    * @return array|bool|int
    *  If no SQL errors are produced, this method will return the number of
    *  affected rows for DELETE and UPDATE statements, the last inserted ID for
    *  INSERT statement, or an associate array of results for SELECT, DESCRIBE,
    *  and PRAGMA statements. Otherwise false.
    */
    public function run($sql = '', $bind = null, $args = null, $style = null) {
        $this->_sql = trim($sql);
        $this->_bind = $this->cleanup($bind);
        $this->_error = '';

        try {
            $pdostmt = $this->prepare($this->_sql);

            if ($pdostmt->execute($this->_bind) !== false) {
                if ($this->sql_is($this->_sql, ['delete', 'update'])) {
                    return $pdostmt->rowCount();
                } elseif ($this->sql_is($this->_sql, 'insert')) {
                    
                    //SCP alternative to lastInsertId
                    return ( $this->_drivers == 'mssql' )? self::lastId($this->_table) : $this->lastInsertId(); 
                    
                    return $this->lastInsertId();
                } else {
                    if (isset($args)) {
                        return $pdostmt->fetchAll($style ? : \PDO::FETCH_CLASS, $args);
                    } else {
                        return $pdostmt->fetchAll($style ? : \PDO::FETCH_OBJ);
                    }
                }
            }
        } catch (\PDOException $e) {
            $this->_error = $e->getMessage();
            $this->debug();
            return false;
        }

        return false;
    }
    
    
    /**
    * The alternative last id in mssql driver ( SCP )
    *
    * @param string $table
    *  name of table.
    *
    *
    * @return int
    *  last id
    */
    public function lastId( $table ) {

        $sql  = "SELECT IDENT_CURRENT('$table') as lastid";
        $result = self::query_row( $sql );
        return $result->lastid;
    }

    /**
    * When a SQL error occurs, this project will send an error message to a
    * callback function specified through the on_error method.
    * The callback function's name should be supplied as a string without
    * parenthesis.
    *
    * If no SQL errors are produced, this method will return an associative
    * array of results.
    *
    * @param $error_callback
    *  Callback function.
    */
    public function on_error($error_callback) {
        if (is_string($error_callback)) {
            // Variable functions for won't work with language constructs such as echo
            // and print, so these are replaced with print_r.
            if (in_array(strtolower($error_callback), ['echo', 'print'])) {
                $error_callback = 'print_r';
            }

            if (function_exists($error_callback)) {
                $this->_error_callback = $error_callback;
            }
        } else {
            $this->_error_callback = $error_callback;
        }
    }

    public function get_query() {
        $info = $this->get_info();
        $query = $info['statement'];

        if (!empty($info['bind'])) {
            foreach ($info['bind'] as $field => $value) {
                $query = str_replace(':'.$field, $this->quote($value), $query);
            }
        }

        return $query;
    }

    public function get_info() {
        $info = [];

        if (!empty($this->_sql)) {
            $info['statement'] = $this->_sql;
        }

        if (!empty($this->_bind)) {
            $info['bind'] = $this->_bind;
        }

        return $info;
    }

    /**
    * Debug.
    */
    private function debug() {
        if (!empty($this->_error_callback)) {
            $error = ['Error' => $this->_error];

            if (!empty($this->_sql)) {
                $error['SQL Statement'] = $this->_sql;
            }

            if (!empty($this->_bind)) {
                $error['Bind Parameters'] = trim(print_r($this->_bind, true));
            }

            $backtrace = debug_backtrace();
            if (!empty($backtrace)) {
                $backtraces = [];
                foreach ($backtrace as $info) {
                    if (isset($info['file']) && $info['file'] != __FILE__) {
                        $backtraces[] = $info['file'] . ' at line ' . $info['line'];
                    }
                }

                if ($backtraces) {
                    $error['Backtrace'] = implode(PHP_EOL, $backtraces);
                }
            }

            $msg = 'SQL Error' . PHP_EOL . str_repeat('-', 50);
            foreach ($error as $key => $val) {
                $msg .= PHP_EOL . PHP_EOL . $key . ':' . PHP_EOL . $val;
            }

            $func = $this->_error_callback;
            $func($msg);
        }
    }

    /**
     * Map a datatype based upon the given driver
     *
     * @return string
     * Returns the equevalent php type
    */
    private function _map_type($driver_type) {
        $map = [];
        $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
        switch ($driver) {
            case 'mysql':
                $map = [
                    'int' => ['smallint', 'mediumint', 'int', 'bigint'],
                    'bool' => ['tinyint'],
                    'float' => ['float', 'double', 'decimal'],
                    'datetime' => ['datetime', 'date']
                ];

                break;
            case 'sqlite':
                $map = [
                    'int' => ['integer'],
                    'float' => ['real']
                ];
        }

        foreach ($map as $type => $driver_types) {
            if (in_array(strtolower($driver_type), $driver_types)) {
                return $type;
                break;
            }
        }

        return 'string';
    }

    /**
     * Get table info
     * @param  string $table
     * The table name
     *
     * @return array
     * Returns array of field information about the table
     */
    public function get_table_info($table) {
        $table = $this->_prefix . $table;
        $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $fields = [];
        
        // SCP
        if( $this->_drivers == 'mssql' ){
           
            $sql = "SELECT DISTINCT
                sys.columns.name AS name,
                sys.types.name AS type,
                (   SELECT 
                        COUNT(column_name)
                    FROM 
                        INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE 
                    WHERE
                        TABLE_NAME = sys.tables.name AND
                        CONSTRAINT_NAME =
                            (   SELECT
                                constraint_name
                                FROM 
                                    INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                                WHERE
                                    TABLE_NAME = sys.tables.name AND                    
                                    constraint_type = 'PRIMARY KEY' AND
                                    COLUMN_NAME = sys.columns.name
                            )
                ) AS pk
            FROM 
                sys.columns, sys.types, sys.tables 
            WHERE
                sys.tables.object_id = sys.columns.object_id AND
                sys.types.system_type_id = sys.columns.system_type_id AND
                sys.types.user_type_id = sys.columns.user_type_id AND
                sys.tables.name = '$table'";


            if ($data = $this->run($sql)) {
                foreach ($data as $column_info) {
                    $field_info = [
                        'type' => $this->_map_type($column_info->type),
                        'primary' => !empty($column_info->pk)
                    ];

                    $fields[$column_info->name] = $field_info;
                }
            }

        // END SCP */    
        }else{


            if ($driver == 'sqlite') {
                $sql = "PRAGMA table_info('$table');";
            } else {
                $sql = "SELECT column_name AS name, data_type AS type, IF(column_key = 'PRI', 1, 0) AS pk FROM information_schema.columns ";
                $sql .= "WHERE table_name = '$table' AND table_schema = '$this->_database';";
            }

            if ($data = $this->run($sql)) {
                foreach ($data as $column_info) {
                    $field_info = [
                        'type' => $this->_map_type($column_info->type),
                        'primary' => !empty($column_info->pk)
                    ];

                    $fields[$column_info->name] = $field_info;
                }
            }

        } 

        return $fields;
    }

    /**
    * Return table fields.
    *
    * @param string $table
    *  Table name.
    *
    * @param array $return_fields
    * If provided, return valid fields from this array
    *
    * @return array
    */
    public function get_fields($table = '', $return_fields = []) {
        $info = $this->get_table_info($table);
        if ($info) {
            $fields = array_keys($info);
            return $return_fields ? array_values(array_intersect($fields, $return_fields)) : $fields;
        }

        return false;
    }

    /**
    * Cleanup parameters.
    *
    * @param mixed $bind
    *  Bind parameters as string/array.
    *
    * @return array
    *  Bind parameters as array.
    */
     private function cleanup($bind = '') {
        if (!is_array($bind)) {
            if (!empty($bind)) {
                $bind = [$bind];
            } else {
                $bind = [];
            }
        }

        return $bind;
    }
}
