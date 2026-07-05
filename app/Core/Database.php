<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    protected $config;
    protected $pdo;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function pdo()
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $host = isset($this->config['host']) ? $this->config['host'] : '127.0.0.1';
        $port = isset($this->config['port']) ? (int) $this->config['port'] : 3306;
        $database = isset($this->config['database']) ? $this->config['database'] : '';
        $charset = isset($this->config['charset']) ? $this->config['charset'] : 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ));
        } catch (PDOException $exception) {
            throw new RuntimeException('数据库连接失败，请检查数据库配置。');
        }

        return $this->pdo;
    }

    public function fetchAll($sql, array $params = array())
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function fetch($sql, array $params = array())
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        $result = $statement->fetch();

        return $result ?: null;
    }

    public function execute($sql, array $params = array())
    {
        $statement = $this->pdo()->prepare($sql);

        return $statement->execute($params);
    }

    public function insertGetId($sql, array $params = array())
    {
        $this->execute($sql, $params);

        return (int) $this->pdo()->lastInsertId();
    }

    public function beginTransaction()
    {
        $this->pdo()->beginTransaction();
    }

    public function commit()
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->commit();
        }
    }

    public function rollBack()
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }
}
