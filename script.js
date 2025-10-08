class BookingApp {
	constructor() {
		this.apiUrl = 'api.php';
		this.currentEditingId = null;
		this.csrfToken = null;
		this.init();
	}

	init() {
		this.loadBookings();
		this.setupEventListeners();
		this.setupDateValidation();
	}

	setupEventListeners() {
		document.getElementById('bookingForm').addEventListener('submit', (e) => this.handleSubmit(e));
		document.getElementById('cancelBtn').addEventListener('click', () => this.cancelEdit());
	}

	setupDateValidation() {
		const dateInput = document.getElementById('date');
		const today = new Date().toISOString().split('T')[0];
		const maxDate = new Date();
		maxDate.setDate(maxDate.getDate() + 365);
		const maxDateStr = maxDate.toISOString().split('T')[0];
		
		dateInput.setAttribute('min', today);
		dateInput.setAttribute('max', maxDateStr);
	}

	async handleSubmit(e) {

		const submitBtn = document.getElementById('submitBtn');
		const originalText = submitBtn.textContent;

		e.preventDefault();
		
		if (!this.validateForm()) {
			return;
		}

		const formData = {
			name: document.getElementById('name').value.trim(),
			phone: document.getElementById('phone').value.trim(),
			email: document.getElementById('email').value.trim(),
			date: document.getElementById('date').value,
			// csrf_token: this.csrfToken
		};
		
		submitBtn.disabled = true;
		submitBtn.textContent = 'Сохраняем...';

		try {
			if (this.currentEditingId) {
				formData.id = this.currentEditingId;
				await this.updateBooking(formData);
			} else {
				await this.createBooking(formData);
			}
		} catch (error) {
			this.showMessage(error.message, 'error');
		} finally {
			submitBtn.disabled = false;
			submitBtn.textContent = originalText;
		}
	}

	validateForm() {
		let isValid = true;
		this.clearErrors();

		const name = document.getElementById('name').value.trim();
		const phone = document.getElementById('phone').value.trim();
		const email = document.getElementById('email').value.trim();
		const date = document.getElementById('date').value;

		// Name validation
		if (!/^[a-zA-Zа-яА-Я\s\-]{2,80}$/u.test(name)) {
			this.showError('nameError', 'Имя должно содержать от 2 до 80 символов (только буквы, пробелы, дефисы)');
			isValid = false;
		}

		// Phone validation
		if (!/^(\+[0-9]{10,15}|[0-9\s()+\-]{7,20})$/.test(phone)) {
			this.showError('phoneError', 'Неверный формат телефона. Используйте формат E.164 (+ и 10–15 цифр) или стандартный формат');
			isValid = false;
		}

		// Email validation
		if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
			this.showError('emailError', 'Неверный формат электронной почты');
			isValid = false;
		}

		// Date validation
		if (!date) {
			this.showError('dateError', 'Дату бронирования нужно указать обязательно');
			isValid = false;
		} else {
			const today = new Date();
			today.setHours(0, 0, 0, 0);
			const selectedDate = new Date(date);
			const maxDate = new Date();
			maxDate.setDate(maxDate.getDate() + 365);

			if (selectedDate < today) {
				this.showError('dateError', 'Дата не может быть в прошлом.');
				isValid = false;
			} else if (selectedDate > maxDate) {
				this.showError('dateError', 'Дата не может быть позже, чем 365 дней от сегодняшнего дня.');
				isValid = false;
			}
		}

		return isValid;
	}

	clearErrors() {
		const errorElements = document.querySelectorAll('.error');
		errorElements.forEach(el => el.textContent = '');
	}

	showError(elementId, message) {
		document.getElementById(elementId).textContent = message;
	}

	async createBooking(bookingData) {
		const response = await this.apiCall('create', bookingData);
		
		if (response.success) {
			this.showMessage('Бронирование успешно создано!', 'success');
			this.resetForm();
			this.loadBookings();
		} else {
			throw new Error(response.message || 'Не удалось создать бронирование');
		}
	}

	async updateBooking(bookingData) {
		const response = await this.apiCall('update', bookingData);
		
		if (response.success) {
			this.showMessage('Бронирование успешно обновлено!', 'success');
			this.cancelEdit();
			this.loadBookings();
		} else {
			throw new Error(response.message || 'Не удалось обновить бронирование');
		}
	}

	async deleteBooking(id) {
		if (!confirm('Вы уверены, что хотите удалить это бронирование?')) {
			return;
		}

		try {
			const response = await this.apiCall('delete', { id });
			
			if (response.success) {
				this.showMessage('Бронирование успешно удалено!', 'success');
				this.loadBookings();
			} else {
				throw new Error(response.message || 'Не удалось удалить бронирование.');
			}
		} catch (error) {
			this.showMessage(error.message, 'error');
		}
	}

	editBooking(booking) {
		this.currentEditingId = booking.id;
		
		document.getElementById('bookingId').value = booking.id;
		document.getElementById('name').value = booking.name;
		document.getElementById('phone').value = booking.phone;
		document.getElementById('email').value = booking.email;
		document.getElementById('date').value = booking.date;
		
		document.getElementById('form-title').textContent = 'Редактировать бронирование';
		document.getElementById('submitBtn').textContent = 'Сохранить';
		document.getElementById('cancelBtn').style.display = 'inline-block';
		
		document.getElementById('name').focus();
	}

	cancelEdit() {
		this.currentEditingId = null;
		this.resetForm();
	}

	resetForm() {
		document.getElementById('bookingForm').reset();
		document.getElementById('bookingId').value = '';
		document.getElementById('form-title').textContent = 'Создать новое бронирование';
		document.getElementById('submitBtn').textContent = 'Создать бронирование';
		document.getElementById('cancelBtn').style.display = 'none';
		this.clearErrors();
	}

	async loadBookings() {
		const loading = document.getElementById('loading');
		const table = document.getElementById('bookingsTable');

		loading.style.display = 'block';
		table.style.display = 'none';

		try {
			const response = await this.apiCall('list');
			
			if (response.success) {
				this.renderBookings(response.data);
				loading.style.display = 'none';
				table.style.display = 'table';
			} else {
				throw new Error('Не удалось загрузить бронирования');
			}
		} catch (error) {
			loading.textContent = 'Ни одного номера не забронировано';
			this.showMessage(error.message, 'error');
		}
	}

	renderBookings(bookings) {
		const tbody = document.getElementById('bookingsTableBody');
		
		if (bookings.length === 0) {
			tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Бронирования не найдены</td></tr>';
			return;
		}

		tbody.innerHTML = bookings.map(booking => `
			<tr>
				<td>${this.escapeHtml(booking.name)}</td>
				<td>${this.escapeHtml(booking.phone)}</td>
				<td>${this.escapeHtml(booking.email)}</td>
				<td>${this.formatDate(booking.date)}</td>
				<td>
					<button class="action-btn edit-btn" onclick="app.editBooking(${this.escapeHtml(JSON.stringify(booking))})">
						Edit
					</button>
					<button class="action-btn delete-btn" onclick="app.deleteBooking(${booking.id})">
						Delete
					</button>
				</td>
			</tr>
		`).join('');
	}

	async apiCall(action, data = null) {
		let url = data ? this.apiUrl : `${this.apiUrl}?action=${action}`;
		
		const options = {
			method: data ? 'POST' : 'GET',
			headers: {
				'Content-Type': 'application/json',
			}
		};
		/*
		if (data && this.csrfToken) {
			options.headers['X-CSRF-Token'] = this.csrfToken;
		}
		*/
		if (data) {
			options.body = JSON.stringify(data);
			url = `${this.apiUrl}?action=${action}`;
		}

		const response = await fetch(url, options);
		const result = await response.json();
		/*
		if (result.csrf_token) {
			this.csrfToken = result.csrf_token;
			document.getElementById('csrfToken').value = this.csrfToken;
		}
		*/
		return result;
	}

	showMessage(message, type) {
		const existingMessage = document.querySelector('.success-message, .error-message');
		if (existingMessage) {
			existingMessage.remove();
		}

		const messageDiv = document.createElement('div');
		messageDiv.className = type === 'success' ? 'success-message' : 'error-message';
		messageDiv.textContent = message;

		const container = document.querySelector('.container');
		container.insertBefore(messageDiv, container.firstChild);

		setTimeout(() => {
			messageDiv.remove();
		}, 5000);
	}

	formatDate(dateString) {
		const date = new Date(dateString);
		return date.toLocaleDateString('ru-RU');
	}

	escapeHtml(unsafe) {
		if (typeof unsafe !== 'string') return unsafe;
		return unsafe
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/"/g, "&quot;")
			.replace(/'/g, "&#039;");
	}
}

// Initialize the application
const app = new BookingApp();
