import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';
Chart.register(DoughnutController, ArcElement, Tooltip, Legend);
export function mount(canvas) {
    const counts = JSON.parse(canvas.dataset.counts);
    const states = { published: 'Faol', pending: 'Tekshiruvda', draft: 'Qoralama', sold: 'Sotilgan', rejected: 'Rad etilgan', archived: 'Arxiv' };
    new Chart(canvas, { type: 'doughnut', data: { labels: Object.values(states), datasets: [{ data: Object.keys(states).map(status => Number(counts[status] || 0)), backgroundColor: ['#17675c', '#f3c744', '#cbd5e1', '#192327', '#e87979', '#94a3b8'], borderWidth: 0 }] }, options: { responsive: true, aspectRatio: 1.3, animation: matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 300 }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } } });
}
