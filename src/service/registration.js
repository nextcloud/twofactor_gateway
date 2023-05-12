import { nc_fetch_json } from 'nextcloud_fetch'

/**
 *
 * @param gateway
 */
export function getState(gateway) {
	const url = OC.generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification', {
		gateway,
	})

	return nc_fetch_json(url).then(function(resp) {
		if (resp.ok) {
			return resp.json().then(json => {
				json.isAvailable = true
				return json
			})
		}
		if (resp.status === 503) {
			console.info(gateway + ' gateway is not available')
			return {
				isAvailable: false,
			}
		}
		throw resp
	})
}

/**
 *
 * @param gateway
 * @param identifier
 */
export function startVerification(gateway, identifier) {
	const url = OC.generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification/start', {
		gateway,
	})

	return nc_fetch_json(url, {
		method: 'POST',
		body: JSON.stringify({
			identifier,
		}),
	}).then(function(resp) {
		if (resp.ok) {
			return resp.json()
		}
		throw resp
	})
}

/**
 *
 * @param gateway
 * @param code
 */
export function tryVerification(gateway, code) {
	const url = OC.generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification/finish', {
		gateway,
	})

	return nc_fetch_json(url, {
		method: 'POST',
		body: JSON.stringify({
			verificationCode: code,
		}),
	}).then(function(resp) {
		if (resp.ok) {
			return resp.json()
		}
		throw resp
	})
}

/**
 *
 * @param gateway
 */
export function disable(gateway) {
	const url = OC.generateUrl('/apps/twofactor_gateway/settings/{gateway}/verification', {
		gateway,
	})

	return nc_fetch_json(url, {
		method: 'DELETE',
	}).then(function(resp) {
		if (resp.ok) {
			return resp.json()
		}
		throw resp
	})
}
