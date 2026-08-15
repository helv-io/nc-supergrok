(function () {
	'use strict'

	const appId = 'supergrok'

	document.addEventListener('DOMContentLoaded', function () {
		const state = loadState()
		fill(state)
		const save = document.getElementById('supergrok-admin-save')
		if (save) {
			save.addEventListener('click', persist)
		}
	})

	function loadState() {
		if (window.OCP && OCP.InitialState) {
			return OCP.InitialState.loadState(appId, 'admin')
		}
		return {}
	}

	function fill(state) {
		setValue('supergrok-chat-model', state.chat_model || '')
		setValue('supergrok-image-model', state.image_model || '')
		setValue('supergrok-voice-model', state.voice_model || '')
		setValue('supergrok-tts-voice', state.tts_voice || '')
		setChecked('supergrok-enable-text', state.enable_text !== false)
		setChecked('supergrok-enable-image', state.enable_image !== false)
		setChecked('supergrok-enable-stt', state.enable_stt !== false)
		setChecked('supergrok-enable-tts', state.enable_tts !== false)
	}

	function persist() {
		const body = new URLSearchParams({
			chat_model: valueOf('supergrok-chat-model'),
			image_model: valueOf('supergrok-image-model'),
			voice_model: valueOf('supergrok-voice-model'),
			tts_voice: valueOf('supergrok-tts-voice'),
			enable_text: checked('supergrok-enable-text') ? '1' : '0',
			enable_image: checked('supergrok-enable-image') ? '1' : '0',
			enable_stt: checked('supergrok-enable-stt') ? '1' : '0',
			enable_tts: checked('supergrok-enable-tts') ? '1' : '0',
		})
		const path = window.OC && OC.generateUrl ? OC.generateUrl('/apps/supergrok/admin-config') : '/apps/supergrok/admin-config'
		fetch(path, {
			method: 'PUT',
			headers: {
				requesttoken: window.OC ? OC.requestToken : '',
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: body.toString(),
			credentials: 'same-origin',
		}).then(function (response) {
			return response.json()
		}).then(function (data) {
			const status = document.getElementById('supergrok-admin-status')
			if (status) {
				status.hidden = false
				status.textContent = t(appId, 'Saved. Disable or enable providers, then reload if the Assistant list looks stale.')
			}
			fill(data)
		}).catch(function () {
			const status = document.getElementById('supergrok-admin-status')
			if (status) {
				status.hidden = false
				status.textContent = t(appId, 'Could not save settings')
			}
		})
	}

	function setValue(id, value) {
		const el = document.getElementById(id)
		if (el) {
			el.value = value
		}
	}

	function setChecked(id, value) {
		const el = document.getElementById(id)
		if (el) {
			el.checked = !!value
		}
	}

	function valueOf(id) {
		const el = document.getElementById(id)
		return el ? el.value : ''
	}

	function checked(id) {
		const el = document.getElementById(id)
		return !!(el && el.checked)
	}
})()
