<?php
declare(strict_types=1);

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private string $host = 'localhost';
    private string $dbname = 'project_se';
    private string $username = 'root';
    private string $password = '';

    private function __construct()
    {
        $this->connection = new PDO(
            "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
            $this->username,
            $this->password
        );
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
