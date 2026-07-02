import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Respect subdirectory installs (e.g. http://localhost/openfno): API calls
// like axios.get('/api/v1/...') must resolve under the app's base URL.
const appBase = document
    .querySelector('meta[name="app-base"]')
    ?.getAttribute('content');
if (appBase) {
    window.axios.defaults.baseURL = appBase;
}
