<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	exit(0);
}

require_once 'config.php';

$db = new Database();
$pdo = $db->getConnection();

$action = $_GET['action'] ?? '';

// Генерация CSRF токена для GET запросов
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
	$csrfToken = CSRFProtection::getToken();
}

// Валидация CSRF для POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'list') {
	$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
	
	if (!$csrfToken) {
		$input = json_decode(file_get_contents('php://input'), true);
		$csrfToken = $input['csrf_token'] ?? null;
	}
	
	if (!CSRFProtection::validateToken($csrfToken)) {
		http_response_code(403);
		echo json_encode([
			'success' => false,
			'message' => 'CSRF token validation failed'
		]);
		exit;
	}
}

function validateDate($date) {
	$today = new DateTime();
	$today->setTime(0, 0, 0);
	
	$inputDate = DateTime::createFromFormat('Y-m-d', $date);
	if (!$inputDate) {
		return false;
	}
	
	$minDate = clone $today;
	$maxDate = clone $today;
	$maxDate->modify('+365 days');
	
	return $inputDate >= $today && $inputDate <= $maxDate;
}

function validateName($name) {
	return preg_match('/^[a-zA-Zа-яА-Я\s\-]{2,80}$/u', $name);
}

function validatePhone($phone) {
	return preg_match('/^(\+[0-9]{10,15}|[0-9\s()+\-]{7,20})$/', $phone);
}

function validateEmail($email) {
	return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sendResponse($statusCode, $data = null, $message = '') {
	http_response_code($statusCode);
	$response = ['success' => $statusCode >= 200 && $statusCode < 300];
	if ($message) $response['message'] = $message;
	if ($data !== null) $response['data'] = $data;
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($GLOBALS['csrfToken'])) {
		$response['csrf_token'] = $GLOBALS['csrfToken'];
	}
	echo json_encode($response);
	exit;
}

try {
	switch ($action) {
		case 'create':
			$input = json_decode(file_get_contents('php://input'), true);

			if (!isset($input['name'], $input['phone'], $input['email'], $input['date'])) {
				sendResponse(400, null, 'Отсутствуют обязательные поля');
			}
			
			if (!validateName($input['name'])) {
				sendResponse(400, null, 'Неверный формат имени');
			}
			
			if (!validatePhone($input['phone'])) {
				sendResponse(400, null, 'Неверный формат телефона');
			}
			
			if (!validateEmail($input['email'])) {
				sendResponse(400, null, 'Неверный формат электронной почты');
			}
			
			if (!validateDate($input['date'])) {
				sendResponse(400, null, 'Неверная дата');
			}

			// sleep(5);
			
			$pdo->beginTransaction();
			try {
				$stmt = $pdo->prepare("SELECT id FROM bookings WHERE date = ?");
				$stmt->execute([$input['date']]);
				
				if ($stmt->fetch()) {
					$pdo->rollBack();
					sendResponse(409, null, 'Дата уже забронирована');
				}
				
				$stmt = $pdo->prepare("INSERT INTO bookings (name, phone, email, date) VALUES (?, ?, ?, ?)");
				$stmt->execute([$input['name'], $input['phone'], $input['email'], $input['date']]);
				
				$bookingId = $pdo->lastInsertId();
				
				$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
				$stmt->execute([$bookingId]);
				$booking = $stmt->fetch();
				
				$pdo->commit();
				sendResponse(201, $booking, 'Бронирование успешно создано');
				
			} catch (PDOException $e) {
				$pdo->rollBack();
				
				if ($e->getCode() == '23000') {
					sendResponse(409, null, 'Дата уже забронирована');
				}
				throw $e;
			}
			break;
			
		case 'list':
			$stmt = $pdo->query("SELECT * FROM bookings ORDER BY date DESC");
			$bookings = $stmt->fetchAll();
			$GLOBALS['csrfToken'] = CSRFProtection::getToken();
			sendResponse(200, $bookings);
			break;
			
		case 'update':
			$input = json_decode(file_get_contents('php://input'), true);
			
			// sleep(5);

			if (!isset($input['id'], $input['name'], $input['phone'], $input['email'], $input['date'])) {
				sendResponse(400, null, 'Отсутствуют обязательные поля');
			}
			
			if (!validateName($input['name'])) {
				sendResponse(400, null, 'Неверный формат имени');
			}
			
			if (!validatePhone($input['phone'])) {
				sendResponse(400, null, 'Неверный формат телефона');
			}
			
			if (!validateEmail($input['email'])) {
				sendResponse(400, null, 'Неверный формат электронной почты');
			}
			
			if (!validateDate($input['date'])) {
				sendResponse(400, null, 'Неверная дата');
			}
			
			$pdo->beginTransaction();
			
			try {
				$stmt = $pdo->prepare("SELECT id FROM bookings WHERE date = ? AND id != ?");
				$stmt->execute([$input['date'], $input['id']]);
				
				if ($stmt->fetch()) {
					$pdo->rollBack();
					sendResponse(409, null, 'Дата уже забронирована');
				}
				
				$stmt = $pdo->prepare("UPDATE bookings SET name = ?, phone = ?, email = ?, date = ? WHERE id = ?");
				$stmt->execute([$input['name'], $input['phone'], $input['email'], $input['date'], $input['id']]);
				
				if ($stmt->rowCount() === 0) {
					$pdo->rollBack();
					sendResponse(404, null, 'Бронирование не найдено');
				}
				
				$stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
				$stmt->execute([$input['id']]);
				$booking = $stmt->fetch();
				
				$pdo->commit();
				sendResponse(200, $booking, 'Бронирование успешно обновлено');
				
			} catch (PDOException $e) {
				$pdo->rollBack();
				
				if ($e->getCode() == '23000') {
					sendResponse(409, null, 'Дата уже забронирована');
				}
				throw $e;
			}
			break;
			
		case 'delete':
			$input = json_decode(file_get_contents('php://input'), true);

			// sleep(5);

			if (!isset($input['id'])) {
				sendResponse(400, null, 'Отсутствует идентификатор бронирования');
			}
			
			$stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
			$stmt->execute([$input['id']]);
			
			if ($stmt->rowCount() === 0) {
				sendResponse(404, null, 'Бронирование не найдено');
			}
			
			sendResponse(200, null, 'Бронирование успешно удалено');
			break;
			
		default:
			sendResponse(400, null, 'Недопустимое действие');
	}
} catch (Exception $e) {
	sendResponse(500, null, 'Внутренняя ошибка сервера');
}
?>
