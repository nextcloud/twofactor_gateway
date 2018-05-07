import $ from 'jquery';
import fetch from 'nextcloud_fetch';

export function getState() {
    let url = OC.generateUrl('/apps/twofactor_sms/settings/verification')

    return fetch(url, {
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    }).then(function (resp) {
        if (resp.ok) {
            return resp.json();
        }
        throw resp;
    })
}

export function startVerification() {
    let url = OC.generateUrl('/apps/twofactor_sms/settings/verification/start')

    return fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    }).then(function (resp) {
        if (resp.ok) {
            return resp.json();
        }
        throw resp;
    })
}

export function tryVerification(code) {
    let url = OC.generateUrl('/apps/twofactor_sms/settings/verification/finish')

    return fetch(url, {
        method: 'POST',
        body: JSON.stringify({
            verificationCode: code
        }),
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    }).then(function (resp) {
        if (resp.ok) {
            return resp.json();
        }
        throw resp;
    })
}
