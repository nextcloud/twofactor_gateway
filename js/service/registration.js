import $ from 'jquery';

export function startVerification() {
    return new Promise((resolve, reject) => {
        setTimeout(() => {
            resolve();
        }, 400);
    })
}

export function tryVerification() {
    return new Promise((resolve, reject) => {
        setTimeout(() => {
            resolve();
        }, 400);
    })
}
