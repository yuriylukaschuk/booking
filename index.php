<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Система бронирования отеля</title>
	<link rel="stylesheet" href="style.css">
</head>
<body>
	<div class="container">
		<h1>Система бронирования отеля</h1>
		
		<div class="booking-form">
			<h2 id="form-title">Бронирование номера</h2>
			<form id="bookingForm">
				<input type="hidden" id="bookingId">
				<!-- <input type="hidden" id="csrfToken" name="csrf_token"> -->
				
				<div class="form-group">
					<label for="name">Имя:</label>
					<input type="text" id="name" name="name" required>
					<span class="error" id="nameError"></span>
				</div>
				
				<div class="form-group">
					<label for="phone">Телефон:</label>
					<input type="tel" id="phone" name="phone" required>
					<span class="error" id="phoneError"></span>
				</div>
				
				<div class="form-group">
					<label for="email">E-mail:</label>
					<input type="email" id="email" name="email" required>
					<span class="error" id="emailError"></span>
				</div>
				
				<div class="form-group">
					<label for="date">Дата бронирования:</label>
					<input type="date" id="date" name="date" required>
					<span class="error" id="dateError"></span>
				</div>
				
				<div class="form-actions">
					<button type="submit" id="submitBtn">Забронировать</button>
					<button type="button" id="cancelBtn" style="display: none;">Очистить форму</button>
				</div>
			</form>
		</div>
		
		<div class="bookings-list">
			<h2>Заборнированные номера</h2>
			<div id="loading">Загрузка...</div>
			<table id="bookingsTable" style="display: none;">
				<thead>
					<tr>
						<th>Имя</th>
						<th>Телефон</th>
						<th>Email</th>
						<th>Дата заезда</th>
						<th>Действия</th>
					</tr>
				</thead>
				<tbody id="bookingsTableBody"></tbody>
			</table>
		</div>
	</div>
	
	<script src="script.js"></script>
</body>
</html>
