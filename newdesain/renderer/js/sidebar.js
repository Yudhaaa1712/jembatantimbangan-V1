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
            <h1 class="site-title" style="font-size: 18px;">WEIGHBRIDGE</h1>
            <p class="site-subtitle" style="font-size: 10px;">Arroyan Jv Teknik (v1.2.7)</p>
          </div>
          <nav class="header-nav">
            <a class="nav-item ${activePage === 'timbangan1' ? 'active' : ''}" href="/timbangan/1?v=122">Timbangan 1</a>
            ${features.timbangan2 !== false ? `<a class="nav-item ${activePage === 'timbangan2' ? 'active' : ''}" href="/timbangan/2?v=122">Timbangan 2</a>` : ''}
            <a class="nav-item ${activePage === 'transaksi' ? 'active' : ''}" href="/transaksi?v=122">Transaksi</a>
            ${features.pengiriman !== false ? `<a class="nav-item ${activePage === 'pengiriman' ? 'active' : ''}" href="/pengiriman?v=122">Pengiriman Pabrik</a>` : ''}
            ${role === 'admin' && features.keuangan !== false ? `<a class="nav-item ${activePage === 'keuangan' ? 'active' : ''}" href="/keuangan?v=122">Keuangan/Kas</a>` : ''}
            ${role === 'admin' && features.upah !== false ? `<a class="nav-item ${activePage === 'upah' ? 'active' : ''}" href="/upah?v=122">Manajemen Upah</a>` : ''}
            ${features.hutang === true ? `<a class="nav-item ${activePage === 'hutang' ? 'active' : ''}" href="/hutang?v=122">Manajemen Hutang</a>` : ''}
            ${role === 'admin' ? `
            <a class="nav-item ${activePage === 'masterdata' || activePage === 'tkbm' ? 'active' : ''}" href="/masterdata?v=122">
              Master Data
            </a>
            <div class="collapse ${activePage === 'masterdata' || activePage === 'tkbm' ? 'show' : ''}" id="masterdataSubmenu">
              <div class="ms-3 border-start ps-2 border-dark" style="border-left-width: 2px !important;">
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=supplier') ? 'active text-warning' : ''}" href="/masterdata?tab=supplier&v=122" style="font-size: 12px; padding: 6px 12px;">Supplier</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=kendaraan') ? 'active text-warning' : ''}" href="/masterdata?tab=kendaraan&v=122" style="font-size: 12px; padding: 6px 12px;">Data Tonase Ramp</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=supir') ? 'active text-warning' : ''}" href="/masterdata?tab=supir&v=122" style="font-size: 12px; padding: 6px 12px;">Supir</a>
                <a class="nav-item d-block ${activePage === 'tkbm' ? 'active text-warning' : ''}" href="/tkbm?v=122" style="font-size: 12px; padding: 6px 12px;">Pekerja TKBM</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=material') ? 'active text-warning' : ''}" href="/masterdata?tab=material&v=122" style="font-size: 12px; padding: 6px 12px;">Material</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && (!window.location.search || window.location.search.includes('tab=users')) && !window.location.search.includes('tab=supplier') && !window.location.search.includes('tab=kendaraan') && !window.location.search.includes('tab=supir') && !window.location.search.includes('tab=material') ? 'active text-warning' : ''}" href="/masterdata?tab=users&v=122" style="font-size: 12px; padding: 6px 12px;">Pengguna</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=email') ? 'active text-warning' : ''}" href="/masterdata?tab=email&v=122" style="font-size: 12px; padding: 6px 12px;">Konfigurasi Email</a>
              </div>
            </div>

            <a class="nav-item ${activePage.startsWith('setup') ? 'active' : ''}" href="/setup?v=122">
              Pengaturan
            </a>
            <div class="collapse ${activePage.startsWith('setup') ? 'show' : ''}" id="setupSubmenu">
              <div class="ms-3 border-start ps-2 border-dark" style="border-left-width: 2px !important;">
                <a class="nav-item d-block ${activePage === 'setup' && window.location.search.includes('tab=setup_apk') ? 'active text-warning' : ''}" href="/setup?tab=setup_apk&v=122" style="font-size: 12px; padding: 6px 12px;">Setup Apk</a>
                <a class="nav-item d-block ${activePage === 'setup' && (!window.location.search || window.location.search.includes('tab=sistem')) && !window.location.search.includes('tab=setup_apk') && !window.location.search.includes('tab=profil') ? 'active text-warning' : ''}" href="/setup?tab=sistem&v=122" style="font-size: 12px; padding: 6px 12px;">Konfigurasi Sistem</a>
                <a class="nav-item d-block ${activePage === 'setup' && window.location.search.includes('tab=profil') ? 'active text-warning' : ''}" href="/setup?tab=profil&v=122" style="font-size: 12px; padding: 6px 12px;">Profil & Tampilan</a>
              </div>
            </div>
            ` : ''}
          </nav>
        </div>
        <div class="header-user">
          <span class="user-name w-100 text-center text-truncate">${currentUser?.nama_lengkap || ''}</span>
          <button class="btn-logout w-100 mt-2" onclick="doLogout()">Logout</button>
        </div>
      </div>
    </header>`;
}

async function doLogout() {
  await fetch('/auth/logout', { method: 'POST' });
  window.location.href = '/auth/login';
}
