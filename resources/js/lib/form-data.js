const unsafe = new Set(['__proto__', 'constructor', 'prototype']);
export const get = (object, path) => path.split('.').reduce((value, key) => value?.[key], object);
export function set(object, path, value) {
    const keys = path.split('.');
    if (keys.some(key => unsafe.has(key))) throw new Error('Invalid field path');
    let target = object;
    keys.slice(0, -1).forEach(key => { target[key] ??= {}; target = target[key]; });
    target[keys.at(-1)] = value;
}
export function scalar(value, type) {
    if (type === 'boolean') return Boolean(value);
    if (value === '' || value === undefined || value === null) return null;
    return ['number', 'integer'].includes(type) ? Number(value) : value;
}
export function schemaParams(state) {
    const map = { section: 'section', condition: 'condition', offer_type: 'offer_type', rental_period: 'rental_period', fuel_type: 'details.fuel_type', part_category: 'details.category', part_unit: 'details.unit', moto_type: 'details.type', vehicle_variant_id: 'vehicle_variant_id', part_product_id: 'part_product_id' };
    return Object.fromEntries(Object.entries(map).map(([key, path]) => [key, get(state, path)]).filter(([, value]) => value !== null && value !== undefined && value !== ''));
}
