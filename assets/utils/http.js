/**
 * @param {RequestInfo} url
 * @param {RequestInit} options
 * @returns {Promise<Response>}
 */
export async function fetch(url = '', options = {}) {
    if ('object' === typeof url && null !== url) {
        options = url;
        url = options.url;
    }

    options = { ...options };
    options.credentials = options.credentials || 'same-origin';
    options.redirect = options.redirect || 'error';
    options.headers = {
        ...options.headers,
        'X-Requested-With': 'XMLHttpRequest',
    };

    return window.fetch(url, options);
}

export async function ok(response) {
    if (!response.ok) {
        const e = new Error(response.statusText);
        e.response = response;

        throw e;
    }

    return response;
}

/**
 * Throws the response if not ok, otherwise, call .json()
 * @param {Response} response
 * @return {Promise<any>}
 */
export function ThrowResponseIfNotOk(response) {
    if (!response.ok) {
        throw response;
    }
    return response.json();
}
