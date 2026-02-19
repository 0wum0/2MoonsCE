<?php

declare(strict_types=1);

/**
 *  SmartMoons (ehemals 2Moons)
 *   by Jan-Otto Kröpke 2009-2016
 *
 * Modernized for PHP 8.3 compatibility
 *
 * @package SmartMoons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @author Updated by 0wum0
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 3.2.7
 * @link https://github.com/0wum0/SmartMoons
 */

class Database
{
    protected ?PDO $dbHandle = null;
    protected array $dbTableNames = [];
    protected string|false $lastInsertId = false;
    protected int|false $rowCount = false;
    protected int $queryCounter = 0;
    protected static ?Database $instance = null;

    public static function get(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getDbTableNames(): array
    {
        return $this->dbTableNames;
    }

    private function __clone() {}

    protected function __construct()
    {
        // Initialize $databaseConfig to prevent undefined variable errors
        $databaseConfig = [];
        
        // Config laden - check if file exists first
        $configPath = 'includes/config.php';
        if (!file_exists($configPath)) {
            throw new Exception("Database configuration file not found: $configPath. Please copy includes/config.sample.php to includes/config.php and configure your database settings.");
        }
        
        require $configPath;

        // Ensure $databaseConfig is an array
        if (!is_array($databaseConfig) || empty($databaseConfig)) {
            throw new Exception("Database configuration error: \$databaseConfig is not properly defined in includes/config.php. It must be an array with host, user, password, and dbname keys.");
        }

        // Validate required database credentials
        if (empty($databaseConfig['host'])) {
            throw new Exception("Database configuration error: 'host' is missing or empty. Please check includes/config.php");
        }
        if (empty($databaseConfig['user'])) {
            throw new Exception("Database configuration error: 'user' is missing or empty. Please check includes/config.php");
        }
        if (!isset($databaseConfig['password'])) {
            throw new Exception("Database configuration error: 'password' is missing. Please check includes/config.php");
        }
        if (empty($databaseConfig['dbname'])) {
            throw new Exception("Database configuration error: 'dbname' is missing or empty. Please check includes/config.php");
        }

        // Set default port if not specified
        if (!isset($databaseConfig['port'])) {
            $databaseConfig['port'] = 3306;
        }

        // Set default prefix if not specified
        if (!isset($databaseConfig['prefix'])) {
            $databaseConfig['prefix'] = '';
        }

        // DSN erstellen
        $dsn = sprintf(
            "mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4",
            $databaseConfig['host'],
            $databaseConfig['port'],
            $databaseConfig['dbname']
        );

        try {
            $db = new PDO(
                $dsn,
                $databaseConfig['user'],
                $databaseConfig['password'],
                [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ]
            );
            
            // Set SQL mode to STRICT_ALL_TABLES for better data integrity
            $db->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES';");
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }

        $this->dbHandle = $db;

        // Tabellen laden
        $dbTableNames = [];
        include_once 'includes/dbtables.php';

        foreach ($dbTableNames as $key => $name) {
            $this->dbTableNames['keys'][]  = '%%' . $key . '%%';
            $this->dbTableNames['names'][] = $name;
        }
    }

    public function disconnect(): void
    {
        $this->dbHandle = null;
    }

    public function getHandle(): ?PDO
    {
        return $this->dbHandle;
    }

    public function lastInsertId(): string|false
    {
        return $this->lastInsertId;
    }

    public function rowCount(): int|false
    {
        return $this->rowCount;
    }

    protected function _query(string $qry, array $params, string $type): PDOStatement|bool
    {
        if (!in_array($type, ["insert", "select", "update", "delete", "replace"])) {
            throw new Exception("Unsupported Query Type");
        }

        $this->lastInsertId = false;
        $this->rowCount     = false;

        $qry = str_replace($this->dbTableNames['keys'], $this->dbTableNames['names'], $qry);

        $stmt = $this->dbHandle->prepare($qry);

        if (isset($params[':limit']) || isset($params[':offset'])) {
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, (int)$value, PDO::PARAM_INT);
            }
        }

        try {
            $success = (count($params) !== 0 && !isset($params[':limit']) && !isset($params[':offset']))
                ? $stmt->execute($params)
                : $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception(
                $e->getMessage() . "<br>\r\n<br>\r\nQuery-Code:" .
                str_replace(array_keys($params), array_values($params), $qry)
            );
        }

        $this->queryCounter++;

        if (!$success) {
            return false;
        }

        if ($type === "insert") {
            $this->lastInsertId = $this->dbHandle->lastInsertId();
        }
        $this->rowCount = $stmt->rowCount();

        return ($type === "select") ? $stmt : true;
    }

    protected function getQueryType(string $qry): string
    {
        if (!preg_match('!^(\S+)!', $qry, $match)) {
            throw new Exception("Invalid query $qry!");
        }
        return strtolower($match[1]);
    }

    public function delete(string $qry, array $params = []): bool
    {
        return $this->_query($qry, $params, "delete");
    }

    public function replace(string $qry, array $params = []): bool
    {
        return $this->_query($qry, $params, "replace");
    }

    public function update(string $qry, array $params = []): bool
    {
        return $this->_query($qry, $params, "update");
    }

    public function insert(string $qry, array $params = []): bool
    {
        return $this->_query($qry, $params, "insert");
    }

    public function select(string $qry, array $params = []): array
    {
        $stmt = $this->_query($qry, $params, "select");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectSingle(string $qry, array $params = [], string|false $field = false): mixed
    {
        $stmt = $this->_query($qry, $params, "select");
        $res  = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($field === false || is_null($res)) ? $res : $res[$field];
    }

    public function lists(string $table, string $column, ?string $key = null): array
    {
        $selects = implode(', ', is_null($key) ? [$column] : [$column, $key]);

        $qry  = "SELECT {$selects} FROM %%{$table}%%;";
        $stmt = $this->_query($qry, [], 'select');

        $results = [];
        if (is_null($key)) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $row[$column];
            }
        } else {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row[$key]] = $row[$column];
            }
        }
        return $results;
    }

    public function query(string $qry): void
    {
        $this->lastInsertId = false;
        $this->rowCount     = false;
        $this->rowCount     = $this->dbHandle->exec($qry);
        $this->queryCounter++;
    }

    public function nativeQuery($qry)
    {
        $this->lastInsertId = false;
        $this->rowCount     = false;

        $qry = str_replace($this->dbTableNames['keys'], $this->dbTableNames['names'], $qry);
        $stmt = $this->dbHandle->query($qry);

        $this->rowCount = $stmt->rowCount();
        $this->queryCounter++;

        return in_array($this->getQueryType($qry), ['select', 'show'])
            ? $stmt->fetchAll(PDO::FETCH_ASSOC)
            : true;
    }

    public function getQueryCounter(): int
    {
        return $this->queryCounter;
    }

    public static function formatDate($time): string
    {
        return date('Y-m-d H:i:s', $time);
    }

    public function quote($str): string
    {
        return $this->dbHandle->quote($str);
    }
}
