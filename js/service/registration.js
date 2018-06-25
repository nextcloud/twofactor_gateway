import $ from 'jquery';
import {nc_fetch_json} from 'nextcloud_fetch';

export function getState () {
	let url = OC.generateUrl('/apps/twofactor_gateway/settings/verification')

	return nc_fetch_json(url).then(function (resp) {
		if (resp.ok) {
			return resp.json();
		}
		throw resp;
	})
}

export function startVerification (identifier) {
	let url = OC.generateUrl('/apps/twofactor_gateway/settings/verification/start')

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

export function tryVerification (code) {
	let url = OC.generateUrl('/apps/twofactor_gateway/settings/verification/finish')

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

export function disable () {
	let url = OC.generateUrl('/apps/twofactor_gateway/settings/verification')

	return nc_fetch_json(url, {
		method: 'DELETE'
	}).then(function (resp) {
		if (resp.ok) {
			return resp.json();
		}
		throw resp;
	})
}
