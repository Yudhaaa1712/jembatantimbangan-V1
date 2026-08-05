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
            <a class="nav-item ${activePage === 'timbangan1' ? 'active' : ''}" href="/timbangan/1?v=122"><i class="bi bi-1-square-fill"></i> Timbangan 1</a>
            ${features.timbangan2 !== false ? `<a class="nav-item ${activePage === 'timbangan2' ? 'active' : ''}" href="/timbangan/2?v=122"><i class="bi bi-2-square-fill"></i> Timbangan 2</a>` : ''}
            <a class="nav-item ${activePage === 'transaksi' ? 'active' : ''}" href="/transaksi?v=122"><i class="bi bi-card-list"></i> Transaksi</a>
            ${features.pengiriman !== false ? `<a class="nav-item ${activePage === 'pengiriman' ? 'active' : ''}" href="/pengiriman?v=122"><i class="bi bi-truck"></i> Pengiriman Pabrik</a>` : ''}
            ${role === 'admin' && features.keuangan !== false ? `<a class="nav-item ${activePage === 'keuangan' ? 'active' : ''}" href="/keuangan?v=122"><i class="bi bi-wallet-fill"></i> Keuangan/Kas</a>` : ''}
            ${role === 'admin' && features.upah !== false ? `<a class="nav-item ${activePage === 'upah' ? 'active' : ''}" href="/upah?v=122"><i class="bi bi-cash-stack"></i> Manajemen Upah</a>` : ''}
            ${features.hutang === true ? `<a class="nav-item ${activePage === 'hutang' ? 'active' : ''}" href="/hutang?v=122"><i class="bi bi-wallet2"></i> Manajemen Hutang</a>` : ''}
            ${role === 'admin' ? `
            <a class="nav-item ${activePage === 'masterdata' ? 'active' : ''}" data-bs-toggle="collapse" href="#masterdataSubmenu" role="button" aria-expanded="${activePage === 'masterdata' ? 'true' : 'false'}" aria-controls="masterdataSubmenu">
              <i class="bi bi-database-fill"></i> Master Data <i class="bi bi-chevron-down float-end mt-1" style="font-size: 12px;"></i>
            </a>
            <div class="collapse ${activePage === 'masterdata' ? 'show' : ''}" id="masterdataSubmenu">
              <div class="ms-3 border-start ps-2 border-secondary">
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=supplier') ? 'active text-warning' : ''}" href="/masterdata?tab=supplier&v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-truck-flatbed"></i> Supplier</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=supir') ? 'active text-warning' : ''}" href="/masterdata?tab=supir&v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-truck"></i> Supir</a>
                <a class="nav-item d-block ${activePage === 'tkbm' ? 'active text-warning' : ''}" href="/tkbm?v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-people"></i> Pekerja TKBM</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=material') ? 'active text-warning' : ''}" href="/masterdata?tab=material&v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-box-seam"></i> Material</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && (!window.location.search || window.location.search.includes('tab=users')) && !window.location.search.includes('tab=supplier') && !window.location.search.includes('tab=supir') && !window.location.search.includes('tab=material') ? 'active text-warning' : ''}" href="/masterdata?tab=users&v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-people-fill"></i> Pengguna</a>
                <a class="nav-item d-block ${activePage === 'masterdata' && window.location.search.includes('tab=email') ? 'active text-warning' : ''}" href="/masterdata?tab=email&v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-envelope-fill"></i> Konfigurasi Email</a>
              </div>
            </div>

            <a class="nav-item ${activePage.startsWith('setup') ? 'active' : ''}" data-bs-toggle="collapse" href="#setupSubmenu" role="button" aria-expanded="${activePage.startsWith('setup') ? 'true' : 'false'}" aria-controls="setupSubmenu">
              <i class="bi bi-gear-fill"></i> Pengaturan <i class="bi bi-chevron-down float-end mt-1" style="font-size: 12px;"></i>
            </a>
            <div class="collapse ${activePage.startsWith('setup') ? 'show' : ''}" id="setupSubmenu">
              <div class="ms-3 border-start ps-2 border-secondary">
                <a class="nav-item d-block ${activePage === 'setup' && window.location.search.includes('tab=setup_apk') ? 'active text-warning' : ''}" href="/setup?tab=setup_apk&v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-phone"></i> Setup Apk</a>
                <a class="nav-item d-block ${activePage === 'setup' && (!window.location.search || window.location.search.includes('tab=sistem')) && !window.location.search.includes('tab=setup_apk') && !window.location.search.includes('tab=profil') ? 'active text-warning' : ''}" href="/setup?tab=sistem&v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-sliders"></i> Konfigurasi Sistem</a>
                <a class="nav-item d-block ${activePage === 'setup' && window.location.search.includes('tab=profil') ? 'active text-warning' : ''}" href="/setup?tab=profil&v=122" style="font-size: 12px; padding: 6px 12px;"><i class="bi bi-palette-fill"></i> Profil & Tampilan</a>
              </div>
            </div>
            ` : ''}
          </nav>
        </div>
        <div class="header-user">
          <span class="user-name w-100 text-center text-truncate"><i class="bi bi-person-circle"></i> ${currentUser?.nama_lengkap || ''}</span>
          <button class="btn-logout w-100 mt-2" onclick="doLogout()"><i class="bi bi-box-arrow-right"></i> Logout</button>
        </div>
      </div>
    </header>`;
}

async function doLogout() {
  await fetch('/auth/logout', { method: 'POST' });
  window.location.href = '/auth/login';
}
