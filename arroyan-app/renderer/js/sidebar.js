let currentUser = null;
let _sessionLoaded = false;
async function loadSessionInfo() {
  if (_sessionLoaded) return;
  _sessionLoaded = true;
  const res = await fetch('/auth/session');
  currentUser = await res.json();
  if (!currentUser.loggedIn) { window.location.href = '/auth/login'; return; }

  // Ambil pengaturan fitur
  let activeFeatures = { timbangan2: true, pengiriman: true, keuangan: true, hutang: false, upah: true };
  try {
    const sRes = await fetch('/setup/settings?_t=' + Date.now()); // Prevent cache
    const sData = await sRes.json();
    if (sData.success && sData.data.active_features) {
      const parsed = JSON.parse(sData.data.active_features);
      activeFeatures = Object.assign({}, activeFeatures, parsed);
    }
  } catch (e) { }

  // Render sidebar
  document.getElementById('site-header').innerHTML = renderSidebar(window.activePage || getActivePage(), activeFeatures);

  // Sembunyikan elemen terkait jika fitur dimatikan
  if (activeFeatures.keuangan === false) {
    const kasContainer = document.getElementById('kasContainerT2');
    if (kasContainer) kasContainer.style.display = 'none';
    const saldoKasDisplay = document.getElementById('saldoKasDisplay');
    if (saldoKasDisplay) saldoKasDisplay.style.display = 'none';
  }
}
document.addEventListener('DOMContentLoaded', loadSessionInfo);
window.loadSession = loadSessionInfo;

function getActivePage() {
  const path = window.location.pathname;
  if (path.includes('timbangan/1')) return 'timbangan1';
  if (path.includes('timbangan/2')) return 'timbangan2';
  if (path.includes('transaksi')) return 'transaksi';
  if (path.includes('pengiriman')) return 'pengiriman';
  if (path.includes('keuangan')) return 'keuangan';
  if (path.includes('hutang')) return 'hutang';
  if (path.includes('tkbm')) return 'tkbm';
  if (path.includes('masterdata')) return 'masterdata';
  if (path.includes('setup')) return 'setup';
  if (path.includes('users')) return 'users';
  return '';
}

function renderSidebar(activePage, features) {
  const role = currentUser?.user_role;
  return `<header class="site-header">
      <div class="header-top">
        <div class="header-brand">
          <div>
            <h1 class="site-title text-lg">WEIGHBRIDGE</h1>
            <p class="site-subtitle text-[10px]">Arroyan Jv Teknik (v1.2.7)</p>
          </div>
          <nav class="header-nav">
            <a class="nav-item ${activePage === 'timbangan1' ? 'active' : ''}" href="/timbangan/1?v=122"><svg class="icon" aria-hidden="true"><use href="#i-1-square-fill"></use></svg> Timbangan Masuk</a>
            ${features.timbangan2 !== false ? `<a class="nav-item ${activePage === 'timbangan2' ? 'active' : ''}" href="/timbangan/2?v=122"><svg class="icon" aria-hidden="true"><use href="#i-2-square-fill"></use></svg> Timbangan Keluar</a>` : ''}
            <a class="nav-item ${activePage === 'transaksi' ? 'active' : ''}" href="/transaksi?v=122"><svg class="icon" aria-hidden="true"><use href="#i-card-list"></use></svg> Transaksi</a>
            ${features.pengiriman !== false ? `<a class="nav-item ${activePage === 'pengiriman' ? 'active' : ''}" href="/pengiriman?v=122"><svg class="icon" aria-hidden="true"><use href="#i-truck"></use></svg> Timbangan Pengiriman</a>` : ''}
            ${role === 'admin' && features.keuangan !== false ? `<a class="nav-item ${activePage === 'keuangan' ? 'active' : ''}" href="/keuangan?v=122"><svg class="icon" aria-hidden="true"><use href="#i-wallet-fill"></use></svg> Keuangan</a>` : ''}
            ${role === 'admin' && features.upah !== false ? `<a class="nav-item ${activePage === 'upah' ? 'active' : ''}" href="/upah?v=122"><svg class="icon" aria-hidden="true"><use href="#i-cash-stack"></use></svg> Manajemen Upah</a>` : ''}
            ${features.hutang === true ? `<a class="nav-item ${activePage === 'hutang' ? 'active' : ''}" href="/hutang?v=122"><svg class="icon" aria-hidden="true"><use href="#i-wallet2"></use></svg> Manajemen Hutang</a>` : ''}
            ${role === 'admin' ? `
            <a class="nav-item ${activePage === 'masterdata' ? 'active' : ''}" data-bs-toggle="collapse" href="#masterdataSubmenu" role="button" aria-expanded="${activePage === 'masterdata' ? 'true' : 'false'}" aria-controls="masterdataSubmenu">
              <svg class="icon" aria-hidden="true"><use href="#i-database-fill"></use></svg> Master Data <svg class="icon w-3 h-3 float-right mt-1" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
            </a>
            <div class="collapse-panel ${activePage === 'masterdata' ? 'show' : ''}" id="masterdataSubmenu">
              <div class="nav-subnav">
                <a class="nav-item nav-sublink ${activePage === 'masterdata' && window.location.search.includes('tab=pabrik') ? 'active text-amber-300' : ''}" href="/masterdata?tab=pabrik&v=122"><svg class="icon" aria-hidden="true"><use href="#i-building"></use></svg> Pabrik (PKS) &amp; Tarif</a>
                <a class="nav-item nav-sublink ${activePage === 'masterdata' && window.location.search.includes('tab=supplier') ? 'active text-amber-300' : ''}" href="/masterdata?tab=supplier&v=122"><svg class="icon" aria-hidden="true"><use href="#i-truck-flatbed"></use></svg> Supplier</a>
                <a class="nav-item nav-sublink ${activePage === 'masterdata' && window.location.search.includes('tab=supir') ? 'active text-amber-300' : ''}" href="/masterdata?tab=supir&v=122"><svg class="icon" aria-hidden="true"><use href="#i-truck"></use></svg> Supir</a>
                <a class="nav-item nav-sublink ${activePage === 'tkbm' ? 'active text-amber-300' : ''}" href="/tkbm?v=122"><svg class="icon" aria-hidden="true"><use href="#i-people"></use></svg> Pekerja TKBM</a>
                <a class="nav-item nav-sublink ${activePage === 'masterdata' && window.location.search.includes('tab=material') ? 'active text-amber-300' : ''}" href="/masterdata?tab=material&v=122"><svg class="icon" aria-hidden="true"><use href="#i-box-seam"></use></svg> Material</a>
                <a class="nav-item nav-sublink ${activePage === 'masterdata' && (!window.location.search || window.location.search.includes('tab=users')) && !window.location.search.includes('tab=pabrik') && !window.location.search.includes('tab=supplier') && !window.location.search.includes('tab=supir') && !window.location.search.includes('tab=material') ? 'active text-amber-300' : ''}" href="/masterdata?tab=users&v=122"><svg class="icon" aria-hidden="true"><use href="#i-people-fill"></use></svg> Pengguna</a>
                <a class="nav-item nav-sublink ${activePage === 'masterdata' && window.location.search.includes('tab=email') ? 'active text-amber-300' : ''}" href="/masterdata?tab=email&v=122"><svg class="icon" aria-hidden="true"><use href="#i-envelope-fill"></use></svg> Konfigurasi Email</a>
              </div>
            </div>

            <a class="nav-item ${activePage.startsWith('setup') ? 'active' : ''}" data-bs-toggle="collapse" href="#setupSubmenu" role="button" aria-expanded="${activePage.startsWith('setup') ? 'true' : 'false'}" aria-controls="setupSubmenu">
              <svg class="icon" aria-hidden="true"><use href="#i-gear-fill"></use></svg> Pengaturan <svg class="icon w-3 h-3 float-right mt-1" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
            </a>
            <div class="collapse-panel ${activePage.startsWith('setup') ? 'show' : ''}" id="setupSubmenu">
              <div class="nav-subnav">
                <a class="nav-item nav-sublink ${activePage === 'setup' && window.location.search.includes('tab=setup_apk') ? 'active text-amber-300' : ''}" href="/setup?tab=setup_apk&v=122"><svg class="icon" aria-hidden="true"><use href="#i-phone"></use></svg> Setup Apk</a>
                <a class="nav-item nav-sublink ${activePage === 'setup' && (!window.location.search || window.location.search.includes('tab=sistem')) && !window.location.search.includes('tab=setup_apk') && !window.location.search.includes('tab=profil') ? 'active text-amber-300' : ''}" href="/setup?tab=sistem&v=122"><svg class="icon" aria-hidden="true"><use href="#i-sliders"></use></svg> Konfigurasi Sistem</a>
                <a class="nav-item nav-sublink ${activePage === 'setup' && window.location.search.includes('tab=profil') ? 'active text-amber-300' : ''}" href="/setup?tab=profil&v=122"><svg class="icon" aria-hidden="true"><use href="#i-palette-fill"></use></svg> Profil & Tampilan</a>
              </div>
            </div>
            ` : ''}
          </nav>
        </div>
        <div class="header-user">
          <span class="user-name w-full text-center truncate"><svg class="icon" aria-hidden="true"><use href="#i-person-circle"></use></svg> ${currentUser?.nama_lengkap || ''}</span>
          <button class="btn-logout w-full mt-2" onclick="doLogout()"><svg class="icon" aria-hidden="true"><use href="#i-box-arrow-right"></use></svg> Logout</button>
        </div>
      </div>
    </header>`;
}

async function doLogout() {
  await fetch('/auth/logout', { method: 'POST' });
  window.location.href = '/auth/login';
}

// ─── GLOBAL RUPIAH FORMATTER HELPER ──────────────────────────────────────────

function formatRupiah(angka, prefix) {
  if (angka === null || angka === undefined || angka === '') return '';
  let number_string = angka.toString().replace(/[^,\d]/g, ''),
    split = number_string.split(','),
    sisa = split[0].length % 3,
    rupiah = split[0].substr(0, sisa),
    ribuan = split[0].substr(sisa).match(/\d{3}/gi);

  if (ribuan) {
    let separator = sisa ? '.' : '';
    rupiah += separator + ribuan.join('.');
  }

  rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
  return prefix == undefined ? rupiah : (rupiah ? 'Rp ' + rupiah : '');
}

function parseRupiah(val) {
  if (val === null || val === undefined || val === '') return 0;
  if (typeof val === 'number') return val;
  const clean = val.toString().replace(/Rp/gi, '').replace(/\./g, '').replace(',', '.').trim();
  return parseFloat(clean) || 0;
}

window.formatRupiah = formatRupiah;
window.parseRupiah = parseRupiah;
window.getRawNumber = parseRupiah;

// Auto-attach Rupiah formatting to input fields
document.addEventListener('input', function (e) {
  if (e.target && (e.target.classList.contains('format-rupiah') || e.target.getAttribute('data-rupiah') === 'true')) {
    let cursorPosition = e.target.selectionStart;
    let oldLength = e.target.value.length;

    let rawVal = e.target.value;
    let hasPrefix = e.target.getAttribute('data-prefix') === 'true';
    let formatted = formatRupiah(rawVal, hasPrefix ? 'Rp ' : undefined);

    e.target.value = formatted;

    let newLength = formatted.length;
    cursorPosition = cursorPosition + (newLength - oldLength);
    try {
      e.target.setSelectionRange(cursorPosition, cursorPosition);
    } catch (err) { }
  }
});
