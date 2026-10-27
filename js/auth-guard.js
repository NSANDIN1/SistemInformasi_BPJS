/* auth-guard.js - penjaga halaman divisi.
   Dipasang di <head> halaman yang hanya boleh dibuka setelah login:
     <script src="../js/auth-guard.js" data-role="pelayanan"></script>
   data-role = peran yang diizinkan (pelayanan | kepesertaan | kearsipan).
   Sesi disimpan oleh index.html (handleLoginSubmit) di sessionStorage setelah
   username, password, dan peran dinyatakan cocok.

   CATATAN: ini pagar sisi browser untuk menjaga alur navigasi. Untuk keamanan
   sungguhan, validasi harus dilakukan di server (misalnya session PHP). */
(function () {
  var tag = document.currentScript;
  var peran = tag ? tag.getAttribute('data-role') : null;
  var root = new URL('../', tag.src);                 // folder utama website
  var login = new URL('index.html', root);            // halaman login

  function sesiValid() {
    try {
      return sessionStorage.getItem('user_logged_in') === 'true' &&
             sessionStorage.getItem('user_role') === peran;
    } catch (e) { return false; }
  }

  function periksa() {
    if (sesiValid()) { document.documentElement.style.visibility = ''; return; }
    document.documentElement.style.visibility = 'hidden';   // cegah halaman sempat terlihat
    login.search = '?login=' + encodeURIComponent(peran || '');
    location.replace(login.href);
  }

  periksa();
  // Kembali lewat tombol Back (cache browser) juga harus diperiksa ulang
  window.addEventListener('pageshow', function (e) { if (e.persisted) periksa(); });

  // Dipanggil oleh tombol "Keluar"
  window.keluarSistem = function () {
    try {
      sessionStorage.removeItem('user_logged_in');
      sessionStorage.removeItem('user_role');
      sessionStorage.removeItem('user_name');
    } catch (e) {}
    location.href = login.href;
  };
})();
