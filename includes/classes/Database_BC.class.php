<?php

declare(strict_types=1);
/**
 *  2Moons
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

class Database_BC extends mysqli
{
    protected $exception;
    private int $queryCount = 0;

    /**
     * Constructor: Set database access data.
     *
     * @return void
     */
    public function __construct()
    {
        // Load database configuration
        if (defined('ROOT_PATH')) {
            require ROOT_PATH . 'includes/config.php';
        } else {
            require __DIR__ . '/../../includes/config.php';
        }

        if (!isset($databaseConfig) || !is_array($databaseConfig)) {
            throw new Exception("Database configuration error: \$databaseConfig is not defined or not an array in includes/config.php");
        }

        foreach (['host', 'user', 'password', 'dbname'] as $key) {
            if (empty($databaseConfig[$key])) {
                throw new Exception("Database configuration error: missing key '{$key}' in includes/config.php");
            }
        }

        if (!isset($databaseConfig['port'])) {
            $databaseConfig['port'] = 3306;
        }

        @parent::__construct(
            $databaseConfig['host'],
            $databaseConfig['user'],
            $databaseConfig['password'],
            $databaseConfig['dbname'],
            $databaseConfig['port']
        );

        if (mysqli_connect_error()) {
            throw new Exception("Connection to database failed: " . mysqli_connect_error());
        }

        parent::set_charset("utf8mb4");
        parent::query("SET SESSION sql_mode = 'STRICT_ALL_TABLES';");
    }

    public function query($resource, $resultmode = NULL): mysqli_result|bool
    {
        if ($result = parent::query($resource)) {
            $this->queryCount++;
            return $result;
        } else {
            throw new Exception("SQL Error: " . $this->error . "<br><br>Query Code: " . $resource);
        }
    }

    public function getFirstRow($resource)
    {
        return $this->uniquequery($resource);
    }

    public function uniquequery($resource)
    {
        $result = $this->query($resource);
        $Return = $result->fetch_array(MYSQLI_ASSOC);
        $result->close();
        return $Return;
    }

    public function getFirstCell($resource)
    {
        return $this->countquery($resource);
    }

    public function countquery($resource)
    {
        $result = $this->query($resource);
        list($Return) = $result->fetch_array(MYSQLI_NUM);
        $result->close();
        return $Return;
    }

    public function fetchquery($resource, $encode = array())
    {
        $result = $this->query($resource);
        $Return = array();

        while ($Data = $result->fetch_array(MYSQLI_ASSOC)) {
            foreach ($Data as $Key => $Store) {
                if (in_array($Key, $encode)) {
                    $Data[$Key] = base64_encode($Store);
                }
            }
            $Return[] = $Data;
        }
        $result->close();
        return $Return;
    }

    public function fetchArray($result)
    {
        return $result->fetch_array(MYSQLI_ASSOC);
    }

    public function fetch_array($result)
    {
        return $this->fetchArray($result);
    }

    public function fetch_num($result)
    {
        return $result->fetch_array(MYSQLI_NUM);
    }

    public function numRows($query)
    {
        return $query->num_rows;
    }

    public function affectedRows()
    {
        return $this->affected_rows;
    }

    public function GetInsertID()
    {
        return $this->insert_id;
    }

    public function escape($string, $flag = false)
    {
        return $this->sql_escape($string, $flag);
    }

    public function sql_escape($string, $flag = false)
    {
        // Ab PHP 8.3 darf nur ein String übergeben werden -> sicherstellen
        if (!is_string($string)) {
            $string = (string) $string;
        }

        return ($flag === false) 
            ? parent::escape_string($string) 
            : addcslashes(parent::escape_string($string), '%_');
    }

    public function str_correction($str)
    {
        return stripcslashes($str);
    }

    public function getVersion()
    {
        return parent::get_client_info();
    }

    public function getServerVersion()
    {
        return $this->server_info;
    }

    public function free_result($resource)
    {
        $resource->close();
        return;
    }

    public function multi_query($resource): bool
    {
        if (parent::multi_query($resource)) {
            do {
                if ($result = parent::store_result()) {
                    $result->free();
                }

                $this->queryCount++;

                if (!parent::more_results()) {
                    break;
                }
            } while (parent::next_result());
        }

        if ($this->errno) {
            throw new Exception("SQL Error: " . $this->error . "<br><br>Query Code: " . $resource);
        }
        return true;
    }

    public function get_sql()
    {
        return $this->queryCount;
    }
}
