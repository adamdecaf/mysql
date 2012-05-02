<?php
/**
* MySQL Framework
* Adam Shannon
* 2010-03-30
*
* Copyright (c) 2010 Adam Shannon & Decaf Productions
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*/

class MySQL {

  static private $connections = array();
  static private $databases = array();
  static private $fields = array();
  static private $queries = array();
  static private $allowed_tags = "";
  static private $default_limit = 1;
  static private $default_fields = "*";
  static private $blank_string = '/^([\s]+)$/i';
  static private $alphanumeric = "/^([a-z0-9]+)$/i";

  /**
  * Check to see if the value meets certain requirements
  */
  static function check_value($value) {
    return !preg_match(self::$blank_string, $value);
  }

  /**
  * get_next_conn_id()
  * Get a unique connection id.
  */
  static function get_conn_id() {
    return chr(97 + count(self::$connections));
  }

  /**
  * This function creates the allocation for a new connection with the repsective variables.
  */
  static function connect($hostname, $username, $password, $database = "", $conn_id = "", $fields = "", $query = "") {

    // Check the required values if they're not blank
    if (!self::check_value($hostname))
      return 'hostname';

    if (!self::check_value($username))
      return 'username';

    if (!self::check_value($password))
      return 'password';

    // Now check the optional values.
    if (!isset($conn_id) || !self::check_value($conn_id)) {
      $conn_id = self::get_conn_id();
    }
      $conn_id = self::get_conn_id();

    if (!isset($database) || !self::check_value($database)) {
      return 'database';
    } else {
      self::$databases[$conn_id] = $database;
    }

    if (!isset($fields) || !self::check_value($fields)) {
      return 'fields';
    } else if ($fields == null) {
      self::$fields[$conn_id] = self::$default_fields;
    }

    if (!isset($query) || !self::check_value($query)) {
      return 'query';
    } else {
      self::$queries[$conn_id] = $query;
    }

    self::$connections[$conn_id] = mysql_connect($hostname, $username, $password);

    return $conn_id;
  }

  /**
  * Delete the allocations for a connection
  */
  static function delete_connection($conn_id) {
    unset(self::$connections[$conn_id]);
    unset(self::$databases[$conn_id]);
    unset(self::$fields[$conn_id]);
    unset(self::$queries[$conn_id]);
  }

  /**
  * Close a connection id.
  */
  static function close($conn_id) {
    mysql_close(self::$connections[$conn_id]);
    delete_connection($conn_id);
  }

  /**
  * This function will clean a string and optionally stript tags from the string.
  */
  static function clean($value, $tags = true) {

    if ($tags != true)
      $value = strip_tags($value, self::$allowed_tags);

    if (magic_quotes_gpc == true)
      $value = stripslashes($value);

    return mysql_real_escape_string($value);
  }

  /**
  * Build a SQL string from $options.
  */
  static function build($type, $options, $conn_id, $store = false, $store_id = null) {

    $sql = null;
    $n = 0;

    // Validate the required options.
    if (empty($options['fields']))
      $options['fields'] = array( 0 => self::$default_fields );

    if (empty($options['database']))
      $options['database'] = self::$databases[$conn_id];

    if (empty($options['table']))
      return 'table';

    if (empty($options['limit']))
      $options['limit'] = self::$default_limit;

    switch ($type) {

      case 'select':

      $sql = 'SELECT';

      // Build the fields
      foreach ($options['fields'] as $field) {
              if ($n == 0) {
                      $sql .= " (`{$field}`";
              } else {
                      $sql .= ",`{$field}`";
              }

              $n++;
      }

      // Set the db and table
      $sql .= ") FROM `{$options['database']}`.`{$options['table']}`";

      $n = 0;

      // Build there WHERE part
      $sql .= ' WHERE';

              // Start with the equal
              foreach ($options['equal'] as $field => $value) {
                      if ($n == 0) {
                              $sql .= " `{$field}` = \"" . self::clean($value) . '"';
                      } else {
                              $sql .= " AND `{$field}` = \"" . self::clean($value) . '"';
                      }

                      $n++;
              }

              // Start with the nonequal
              foreach ($options['not_equal'] as $field => $value) {
                      if ($n == 0) {
                              $sql .= " `{$field}` != \"" . self::clean($value) . '"';
                      } else {
                              $sql .= " AND `{$field}` != \"" . self::clean($value) . '"';
                      }

                      $n++;
              }

      $n = 0;

      // Include an order by id
      foreach ($options['order_by'] as $field => $type) {
              if ($n == 0) {
                      $sql .= " ORDER BY `{$field}` {$type}";
              }

              $n++;
      }

      // Include the limit
      $sql .= " LIMIT {$options['limit']}";

      break;

      // Build an INSERT statement
      case 'insert':

      // INSERT INTO {database}.{table} ({fields}) VALUES ({values})
      $sql = 'INSERT INTO';

              // Insert the database and table
              $sql .= " `{$options['database']}`.`{$options['table']}`";

              // Build the fields
              foreach ($options['fields'] as $field) {
                      if ($n == 0) {
                              $sql .= " (`{$field}`";
                      } else {
                              $sql .= ",`{$field}`";
                      }

                      $n++;
              }

              $n = 0;
              $sql .= ') VALUES';

              // Build the values
              if (isset($options['values'])) {

                      foreach ($options['values'] as $value) {
                              if ($n == 0) {
                                      $sql .= " ('{$value}'";
                              } else {
                                      $sql .= ",'{$value}'";
                              }

                              $n++;
                      }

              } else {

                      return 'values';

              }

              $sql .= ');';


      break;

      // Build a DELETE statement
      case 'delete':

      break;

      // Build an UPDATE statement
      case 'update':

      break;

      }

    // If we should store it then store it.
    if ($store == true && isset($store_id)) {
    self::$queries[$store_id] = $sql;
    }

    return $sql;

  }
}
