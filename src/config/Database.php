<?php
/**
 * src/config/Database.php
 * Central PDO-based database connection (singleton)
 * Usage:
 *   $db = Database::getInstance();
 *   $pdo = $db->getConnection();
 *   $rows = $db->fetchAll('SELECT * FROM productos WHERE nombre LIKE ?', ["%pan%"]); 
 */
class Database {
    private static $instance = null;
    /** @var \PDO */
    private $pdo = null;
    private $config = [];

    private function __construct(array $config = []) {
        $this->config = $this->resolveConfig($config);
        $this->connect();
    }

    private function resolveConfig(array $cfg = []) {
        // Priority: passed config -> env vars -> src/config/db.php (array $DB_CONFIG) -> defaults
        $c = [];
        if (!empty($cfg)) $c = $cfg;

        // env vars
        $envMap = ['DB_HOST'=>'host','DB_USER'=>'user','DB_PASS'=>'pass','DB_NAME'=>'dbname','DB_PORT'=>'port','DB_CHARSET'=>'charset','DB_DRIVER'=>'driver'];
        foreach ($envMap as $k => $v) {
            $val = getenv($k);
            if ($val !== false && !isset($c[$v])) $c[$v] = $val;
        }

        // try legacy config file if exists
        $legacy = __DIR__ . '/db.php';
        $legacy2 = __DIR__ . '/../../config/db.php';
        if (file_exists($legacy2)) {
            try {
                $maybe = include $legacy2;
                if (is_array($maybe) && !empty($maybe)) { $c = array_merge($maybe, $c); }
            } catch (Exception $e) {
                error_log('[Database] include legacy config failed: ' . $e->getMessage());
            }
        }

        // defaults
        $defaults = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'user' => 'root',
            'pass' => '',
            'dbname' => 'inventory',
            'port' => 3306,
            'charset' => 'utf8mb4'
        ];

        return array_replace($defaults, $c);
    }

    private function connect() {
        if ($this->pdo) return;
        $drv = $this->config['driver'];
        if ($drv === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $this->config['host'], $this->config['port'], $this->config['dbname'], $this->config['charset']);
        } else {
            // default to mysql style DSN for common use
            $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $drv, $this->config['host'], $this->config['port'], $this->config['dbname'], $this->config['charset']);
        }

        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], $opts);
        } catch (PDOException $e) {
            error_log('[Database] Connection failed: ' . $e->getMessage());
            // rethrow to let callers handle or die depending on env
            throw $e;
        }
    }

    public static function getInstance(array $config = []) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /** @return \PDO */
    public function getConnection() {
        return $this->pdo;
    }

    public function fetchAll($sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function fetch($sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function execute($sql, array $params = []) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollBack() { return $this->pdo->rollBack(); }

    // Prevent cloning
    private function __clone() {}
    // Magic wakeup must be public to satisfy PHP's requirements when present
    public function __wakeup() {}
}

// EOF
