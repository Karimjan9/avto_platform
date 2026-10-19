import axios from 'axios';
export const api = axios.create({ baseURL: '/api/v1', timeout: 20000, withCredentials: true, withXSRFToken: true, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' } });
export function notify(message) { window.dispatchEvent(new CustomEvent('notice', { detail: message })); }
export function explainError(error) {
    if (error.response?.status === 401 || error.response?.status === 419) return 'Sessiya tugadi. Qayta kirib, amalni takrorlang.';
    if (error.response?.status === 429) return 'So‘rovlar ko‘paydi. Biroz kutib, qayta urinib ko‘ring.';
    if (error.response?.status === 409) return error.response.data.message || 'Ma’lumot o‘zgargan. Sahifani yangilab, qayta urinib ko‘ring.';
    const errors = error.response?.data?.errors;
    if (errors) return Object.values(errors).flat().join(' ');
    return error.response?.data?.message || 'Ulanishda xatolik. Qayta urinib ko‘ring.';
}
