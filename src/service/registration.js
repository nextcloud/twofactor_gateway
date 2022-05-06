import {nc_fetch_json} from 'nextcloud_fetch';

export function getState (gateway) {
	let url = OC.generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification', {
		gateway: gateway
	})

	return nc_fetch_json(url).then(function (resp) {
		if (resp.ok) {
			return resp.json().then(json => {
				json.isAvailable = true
				return json
			})
		}
		if (resp.status === 503) {
			console.info(gateway + ' gateway is not available')
			return {
				isAvailable: false
			}
		}
		throw resp
	})
}

export function startVerification (gateway, identifier) {
	let url = OC.generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification/start', {
		gateway: gateway
	})

	return nc_fetch_json(url, {
		method: 'POST',
		body: JSON.stringify({
			identifier: identifier
		})
	}).then(function (resp) {
		if (resp.ok) {
			return resp.json();
		}
		throw resp;
	})
}

export function tryVerification (gateway, code) {
	let url = OC.generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification/finish', {
		gateway: gateway
	})

	return nc_fetch_json(url, {
		method: 'POST',
		body: JSON.stringify({
			verificationCode: code
		})
	}).then(function (resp) {
		if (resp.ok) {
			return resp.json();
		}
		throw resp;
	})
}

export function disable (gateway) {
	let url = OC.generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification', {
		gateway: gateway
	})

	return nc_fetch_json(url, {
		method: 'DELETE'
	}).then(function (resp) {
		if (resp.ok) {
			return resp.json();
		}
		throw resp;
	})
}
