(function () {
	'use strict'

	const appId = 'supergrok'
	let pollTimer = null
	let pollInterval = 5
	let pollDeadline = 0

	document.addEventListener('DOMContentLoaded', function () {
		const state = loadState()
		bind()
		render(state)
	})

	function loadState() {
		if (window.OCP && OCP.InitialState) {
			return OCP.InitialState.loadState(appId, 'personal')
		}
		return { signedIn: false, email: '', name: '', redirectUri: 'http://127.0.0.1:56121/callback' }
	}

	function bind() {
		on('supergrok-device-start', 'click', function () {
			withPasswordConfirmation(startDevice)
		})
		on('supergrok-device-cancel', 'click', cancelDevice)
		on('supergrok-pkce-open', 'click', function () {
			withPasswordConfirmation(startPkce)
		})
		on('supergrok-pkce-finish', 'click', function () {
			withPasswordConfirmation(exchangePkce)
		})
		on('supergrok-logout', 'click', function () {
			withPasswordConfirmation(logout)
		})
	}

	function render(account) {
		const signedIn = document.getElementById('supergrok-signed-in')
		const signedOut = document.getElementById('supergrok-signed-out')
		const accountEl = document.getElementById('supergrok-account')
		if (!signedIn || !signedOut) {
			return
		}
		if (account && account.signedIn) {
			signedIn.hidden = false
			signedOut.hidden = true
			const who = account.email || account.name || t(appId, 'SuperGrok account')
			accountEl.textContent = t(appId, 'Signed in as {name}').replace('{name}', who)
		} else {
			signedIn.hidden = true
			signedOut.hidden = false
		}
	}

	function startDevice() {
		clearError()
		post('/apps/supergrok/device/start').then(function (data) {
			if (data.status === 'error') {
				showError(data.message || data.reason)
				return
			}
			document.getElementById('supergrok-user-code').textContent = data.user_code || ''
			const link = document.getElementById('supergrok-verify-link')
			const href = data.verification_uri_complete || data.verification_uri || '#'
			link.href = href
			link.textContent = data.verification_uri || href
			document.getElementById('supergrok-device-pending').hidden = false
			document.getElementById('supergrok-device-status').textContent = t(appId, 'Waiting for approval...')
			pollInterval = Math.max(parseInt(data.interval, 10) || 5, 1)
			pollDeadline = Date.now() + (Math.max(parseInt(data.expires_in, 10) || 900, 30) * 1000)
			schedulePoll()
		}).catch(function (err) {
			showError(err.message)
		})
	}

	function schedulePoll() {
		clearTimeout(pollTimer)
		pollTimer = setTimeout(pollDevice, pollInterval * 1000)
	}

	function pollDevice() {
		if (Date.now() >= pollDeadline) {
			cancelDevice()
			showError(t(appId, 'Device code expired'))
			return
		}
		post('/apps/supergrok/device/poll').then(function (data) {
			if (data.signedIn) {
				cancelDevice()
				render(data)
				return
			}
			if (data.status === 'error') {
				cancelDevice()
				showError(data.message || data.reason)
				return
			}
			if (data.status === 'slow_down' && data.interval) {
				pollInterval = parseInt(data.interval, 10) || pollInterval
			}
			document.getElementById('supergrok-device-status').textContent = t(appId, 'Waiting for approval...')
			schedulePoll()
		}).catch(function (err) {
			showError(err.message)
			schedulePoll()
		})
	}

	function cancelDevice() {
		clearTimeout(pollTimer)
		pollTimer = null
		const pending = document.getElementById('supergrok-device-pending')
		if (pending) {
			pending.hidden = true
		}
	}

	function startPkce() {
		clearError()
		post('/apps/supergrok/pkce/start').then(function (data) {
			if (data.status === 'error') {
				showError(data.message || data.reason)
				return
			}
			if (data.authorize_url) {
				window.open(data.authorize_url, '_blank', 'noopener')
			}
		}).catch(function (err) {
			showError(err.message)
		})
	}

	function exchangePkce() {
		clearError()
		const callback = (document.getElementById('supergrok-callback').value || '').trim()
		if (!callback) {
			showError(t(appId, 'Paste the full callback URL, query string, or code'))
			return
		}
		post('/apps/supergrok/pkce/exchange', { callback: callback }).then(function (data) {
			if (data.status === 'error') {
				showError(data.message || data.reason)
				return
			}
			render(data)
		}).catch(function (err) {
			showError(err.message)
		})
	}

	function logout() {
		clearError()
		cancelDevice()
		post('/apps/supergrok/logout').then(function (data) {
			render(data)
		}).catch(function (err) {
			showError(err.message)
		})
	}

	function post(path, body) {
		const headers = {
			requesttoken: window.OC ? OC.requestToken : '',
			'Content-Type': 'application/x-www-form-urlencoded',
		}
		const encoded = new URLSearchParams()
		if (body) {
			Object.keys(body).forEach(function (key) {
				encoded.append(key, body[key])
			})
		}
		return fetch(url(path), {
			method: 'POST',
			headers: headers,
			body: encoded.toString(),
			credentials: 'same-origin',
		}).then(function (response) {
			return response.json().then(function (data) {
				if (!response.ok && data && data.message) {
					throw new Error(data.message)
				}
				if (!response.ok && !data) {
					throw new Error(t(appId, 'Request failed'))
				}
				return data
			})
		})
	}

	function url(path) {
		if (window.OC && OC.generateUrl) {
			return OC.generateUrl(path)
		}
		return path
	}

	function withPasswordConfirmation(fn) {
		if (window.OC && OC.PasswordConfirmation && typeof OC.PasswordConfirmation.requirePasswordConfirmation === 'function') {
			OC.PasswordConfirmation.requirePasswordConfirmation(fn)
			return
		}
		fn()
	}

	function on(id, event, handler) {
		const el = document.getElementById(id)
		if (el) {
			el.addEventListener(event, handler)
		}
	}

	function showError(message) {
		const el = document.getElementById('supergrok-error')
		if (!el) {
			return
		}
		el.hidden = false
		el.textContent = message
	}

	function clearError() {
		const el = document.getElementById('supergrok-error')
		if (el) {
			el.hidden = true
			el.textContent = ''
		}
	}
})()
