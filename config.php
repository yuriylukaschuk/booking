<?php
class Config {
	const DB_HOST = 'localhost';
	const DB_NAME = 'j07532941_1037';
	const DB_USER = 'j07532941_1037';
	const DB_PASS = '3fs3ed3wsw';
	const DB_CHARSET = 'utf8mb4';
}

class Database {
	private $pdo;
	
	public function __construct() {
		$dsn = "mysql:host=" . Config::DB_HOST . ";dbname=" . Config::DB_NAME . ";charset=" . Config::DB_CHARSET;
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		];
		
		try {
			$this->pdo = new PDO($dsn, Config::DB_USER, Config::DB_PASS, $options);
		} catch (PDOException $e) {
			throw new PDOException($e->getMessage(), (int)$e->getCode());
		}
	}
	
	public function getConnection() {
		return $this->pdo;
	}
}

// CSRF Protection
class CSRFProtection {
	public static function startSession() {
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
	}
	
	public static function generateToken() {
		self::startSession();
		
		if (empty($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}
		
		return $_SESSION['csrf_token'];
	}
	
	public static function validateToken($token) {
		self::startSession();
		
		if (empty($_SESSION['csrf_token']) || empty($token)) {
			return false;
		}
		
		return hash_equals($_SESSION['csrf_token'], $token);
	}
	
	public static function getToken() {
		return self::generateToken();
	}
}
?>
