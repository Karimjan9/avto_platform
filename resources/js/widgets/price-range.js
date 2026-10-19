import noUiSlider from 'nouislider';
import 'nouislider/dist/nouislider.css';
export function mount(node) {
    const form = node.closest('form'), min = form.elements.price_min, max = form.elements.price_max;
    const limit = Math.max(Number(node.dataset.max), Number(max.value || 0), Number(min.value || 0) + 1);
    noUiSlider.create(node, { start: [Number(min.value || 0), Number(max.value || limit)], connect: true, step: 1, handleAttributes: [{ 'aria-label': 'Eng kam narx' }, { 'aria-label': 'Eng yuqori narx' }], range: { min: 0, max: limit } });
    node.noUiSlider.on('change', values => { min.value = Math.round(Number(values[0])); max.value = Math.round(Number(values[1])); });
    [min, max].forEach(input => input.addEventListener('change', () => node.noUiSlider.set([min.value || null, max.value || null])));
    form.elements.currency.addEventListener('change', () => {
        min.value = ''; max.value = '';
        const upper = form.elements.currency.value === 'USD' ? 200000 : 2000000000;
        node.noUiSlider.updateOptions({ range: { min: 0, max: upper }, start: [0, upper] });
    });
}
