// Быстрая настройка перед публикацией.
const CONFIG = {
  ozonUrl: 'https://ozon.ru/s/tulskie-retsepty', // вставьте ссылку на карточку/магазин Ozon
  phone: '',    // например: +7 ...
  email: ''     // например: info@...
};

const FLAVORS = {
  apple: 'Густая яблочная начинка, мягкое медовое тесто и ароматные специи.',
  strawberry: 'Сочная клубничная начинка с мягким ягодным вкусом.',
  orange: 'Яркая цитрусовая начинка с ароматным апельсиновым акцентом.',
  currant: 'Насыщенная смородиновая начинка с выразительной ягодной кислинкой.',
  cherry: 'Вишнёвая начинка с глубоким ягодным вкусом и приятной кислинкой.',
  lemon: 'Свежая лимонная начинка с заметным цитрусовым ароматом.',
  lingonberry: 'Брусничная начинка — яркая, ягодная и чуть терпкая.',
  plum: 'Мягкая сливовая начинка с насыщенным фруктовым вкусом.',
  raspberry: 'Ароматная малиновая начинка с узнаваемым ягодным характером.'
};

const header = document.querySelector('.site-header');
window.addEventListener('scroll', () => header.classList.toggle('scrolled', window.scrollY > 80), {passive:true});

const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('.nav');
toggle?.addEventListener('click', () => {
  const open = nav.classList.toggle('open');
  toggle.setAttribute('aria-expanded', String(open));
});
nav?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {nav.classList.remove('open'); toggle?.setAttribute('aria-expanded','false');}));

// Ассортимент: фильтр по массе/детской серии.
document.querySelectorAll('.weight-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.weight-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const filter = btn.dataset.filter;
    document.querySelectorAll('.product-card').forEach(card => { card.hidden = card.dataset.weight !== filter; });
  });
});

// Вкусы: фотографии разлома статичны, меняется только описание начинки.
document.querySelectorAll('.flavor-chip').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.flavor-chip').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('flavor-description').textContent = FLAVORS[btn.dataset.flavor] || '';
  });
});

function showToast(text){
  const toast = document.getElementById('toast');
  toast.textContent = text;
  toast.classList.add('show');
  clearTimeout(showToast.timer);
  showToast.timer = setTimeout(() => toast.classList.remove('show'), 2800);
}

// Подставляем Ozon-ссылку, когда она появится.
document.querySelectorAll('.js-ozon').forEach(link => {
  if(CONFIG.ozonUrl && CONFIG.ozonUrl !== '#') {
    link.href = CONFIG.ozonUrl;
  } else if(!link.getAttribute('href') || link.getAttribute('href') === '#') {
    link.addEventListener('click', e => {e.preventDefault(); showToast('Добавьте ссылку Ozon в CONFIG в файле script.js');});
  }
});

if(CONFIG.phone) document.querySelector('[data-contact="phone"]').textContent = CONFIG.phone;
if(CONFIG.email) document.querySelector('[data-contact="email"]').textContent = CONFIG.email;

document.getElementById('partner-form')?.addEventListener('submit', e => {
  e.preventDefault();
  showToast('Форма готова по дизайну. Перед публикацией подключим отправку заявок.');
});

document.getElementById('year').textContent = new Date().getFullYear();

// Плавное появление секций.
const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if(entry.isIntersecting){ entry.target.classList.add('visible'); observer.unobserve(entry.target); }
  });
}, {threshold:.08});
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));


// =========================================================
// V2.36 — Яндекс Карта точек продаж в Туле
// 1 и 2 — самостоятельные точки, не объединяются.
// =========================================================
const RETAIL_STORES = [
  {id:'1', lat:54.196280, lon:37.619284},
  {id:'2', lat:54.196302, lon:37.620052},
  {id:'3', lat:54.199452, lon:37.615846},
  {id:'4', lat:54.212480, lon:37.609629},
  {id:'5', lat:54.225490, lon:37.627970},
  {id:'6', lat:54.163279, lon:37.588097},
  {id:'7', lat:54.200415, lon:37.645041},
  {id:'8', lat:54.209409, lon:37.691971}
];

const YANDEX_PT = RETAIL_STORES
  .map(s => `${s.lon.toFixed(6)},${s.lat.toFixed(6)},pm2rdl${s.id}`)
  .join('~');

function yandexRetailMapUrl(lon=37.636000, lat=54.197000, zoom=12){
  const params = new URLSearchParams({
    ll:`${lon.toFixed(6)},${lat.toFixed(6)}`,
    z:String(zoom),
    l:'map',
    pt:YANDEX_PT
  });
  return `https://yandex.ru/map-widget/v1/?${params.toString()}`;
}

function focusStoreOnYandex(id){
  const iframe = document.getElementById('retail-map');
  const store = RETAIL_STORES.find(s => s.id === String(id));
  if(!iframe || !store) return;
  iframe.src = yandexRetailMapUrl(store.lon, store.lat, 17);
}

document.querySelectorAll('.store-item').forEach(item => {
  item.addEventListener('click', e => {
    if(e.target.closest('a')) return;
    focusStoreOnYandex(item.dataset.store);
  });
  item.addEventListener('keydown', e => {
    if(e.key === 'Enter' || e.key === ' '){
      e.preventDefault();
      focusStoreOnYandex(item.dataset.store);
    }
  });
});
