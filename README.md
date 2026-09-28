# SPLaSK Score untuk Joomla

Pakej Joomla yang menggabungkan komponen pengurusan administrator, modul dashboard, dan plugin automasi untuk memaparkan markah penilaian serta tarikh kemaskini terakhir dari sistem SPLaSK (Sistem Pemantauan Laman Web dan Perkhidmatan Dalam Talian).

## Fungsi Utama

- Paparan markah penilaian SPLaSK melalui API rasmi.
- Komponen `com_splaskscore` di menu **Components > SPLaSK Score** dengan empat halaman: **Analitik**, **Rekod Kutipan**, **Tetapan** dan **About**.
- Satu token dan satu dashboard terurus bagi setiap laman Joomla.
- Modul `mod_splaskscore` kekal sebagai widget pada Home Dashboard administrator.
- Token tersimpan tidak dihantar dalam HTML awal. Selepas Save, medan memaparkan penanda bertopeng; nilai sebenar hanya diambil melalui permintaan pelayan ber-ACL apabila ikon mata ditekan dan dibuang semula daripada halaman apabila disembunyikan.
- Rekod Kutipan membezakan status berjaya, gagal dan dilangkau serta menyediakan ujian segera, countdown, amaran scheduler lewat dan eksport CSV.
- Paparan `Tarikh Semakan` dengan timestamp penuh untuk audit operasi.
- Paparan `Semakan Seterusnya` sebagai hari dan tarikh sahaja, dikira daripada `Tarikh Semakan + 1 hari`.
- Jam telemetry masa nyata berasaskan timezone Joomla pada dashboard pentadbir.
- Tiada tracking pelawat – hanya integrasi API dan sejarah analitik operasi pentadbir.
- Menyokong semakan kemaskini automatik melalui GitHub (update server).

## Cara Pasang

1. Muat turun `pkg_splaskscore_v1.9.5.zip` dari tab [Releases](https://github.com/hazatmda/mod_splaskscore/releases) selepas versi tersebut diterbitkan. Untuk calon ujian tempatan, gunakan satu-satunya ZIP pengguna di dalam folder `dist/release`.
2. Pasang di Joomla: **Extensions > Manage > Install**.
3. Buka **Components > SPLaSK Score > Tetapan**, masukkan satu token SPLaSK untuk laman Joomla tersebut.
4. Dalam halaman yang sama, aktifkan **Paparan Dashboard** untuk memaparkan widget pada Dashboard administrator atau nyahaktifkannya untuk menyembunyikan widget.
5. Semak tetapan automasi analitik jika mahu kutipan sejarah berjalan melalui Joomla Scheduled Tasks. Tiada konfigurasi atau penciptaan modul secara manual diperlukan.

## Komponen Administrator dan Tetapan

Komponen ialah satu-satunya pusat pengurusan SPLaSK Score:

- Halaman **Analitik** memaparkan KPI operasi, carta trend 30 hari, sejarah berhalaman, status kesihatan, catatan dan eksport CSV daripada rekod tersimpan laman tersebut.
- Halaman **Rekod Kutipan** memaparkan status tugas, sebab dan countdown pelaksanaan seterusnya, amaran runner lewat, serta jejak audit kutipan automatik atau manual yang berjaya, gagal atau dilangkau. Pentadbir juga boleh menjalankan ujian kutipan segera dan mengeksport rekod bertapis sebagai CSV.
- Halaman **Tetapan** mengandungi token tunggal, suis Paparan Dashboard, automasi, retention, pagination, label dan penjenamaan. Token tersimpan tidak dipraisi dalam HTML; ikon mata mendapatkannya hanya atas permintaan pentadbir yang dibenarkan. Menyembunyikan token membuang nilainya daripada halaman, manakala Save tanpa nilai pengganti mengekalkan token sedia ada.
- Halaman **About** selepas Tetapan memaparkan versi semasa, pemilik, organisasi, keserasian, lesen, pautan repositori dan ringkasan seni bina.
- Semua halaman komponen dilindungi oleh ACL `core.manage`. Super User boleh membuka **Options** dari mana-mana halaman untuk menetapkan akses komponen; penyimpanan tetapan dan pendedahan token turut memerlukan kebenaran sunting/status bagi renderer modul terurus.
- Installer mencipta satu renderer modul dalaman secara automatik. Renderer itu tidak memerlukan konfigurasi melalui Module Manager.
- Jenis SPLaSK Score disembunyikan daripada skrin **Add Module**; URL tambah secara terus dan cubaan mencipta instance kedua turut disekat di peringkat pelayan.
- Jika naik taraf menemui instance lama yang berganda, satu instance utama dikekalkan dan instance tambahan dinyahterbitkan tanpa memadam jadual sejarahnya.
- Menyahaktifkan **Paparan Dashboard** hanya menyembunyikan widget. Kutipan analitik automatik kekal dikawal oleh tetapan automasi yang berasingan.

## Automasi Analitik & Joomla Scheduled Tasks

Halaman **Tetapan** komponen ialah panel kawalan utama untuk automasi analitik. Selepas pemasangan atau simpanan tetapan, SPLaSK Score akan cuba memasang/mengaktifkan plugin Scheduler, mencipta tugas Joomla Scheduled Tasks yang diperlukan, dan menyelaraskan status aktif, masa kutipan harian, sela percubaan semula bagi kutipan gagal, retention days, serta had rekod sejarah.

Halaman Joomla **Scheduled Tasks** hanya menjadi paparan status dan tempat menjalankan **Run Test** bagi tugas SPLaSK Score. Tugas terurus itu tidak boleh disunting, dinyahaktifkan, dipadam atau dicipta semula melalui Scheduler Manager; klik pada tajuknya akan membawa pentadbir ke **Components > SPLaSK Score > Tetapan**. Tugas SPLaSK Score juga disembunyikan daripada senarai jenis tugas baharu supaya semua konfigurasi kekal mempunyai satu sumber yang jelas.

Pada Joomla 5.2 dan lebih baharu, kutipan **Harian** menggunakan peraturan cron Joomla yang mengikuti zon masa laman dalam Global Configuration. Contohnya, `Masa Kutipan = 06:00` dengan zon masa `Asia/Kuala_Lumpur` bermaksud 6:00 pagi waktu Malaysia. Joomla menyimpan masa pelaksanaan seterusnya dalam UTC dan mengira jadual berikutnya menggunakan zon masa laman. Jika hosting memuatkan pustaka `CronExpression` lama/bertindih yang tidak serasi dengan panggilan satu argumen Joomla, modul mengesan keadaan itu dan menggunakan peraturan harian UTC yang telah ditukar daripada masa tempatan supaya simpanan tetapan tidak gagal.

Tugas automatik menggunakan dasar **satu kutipan berjaya sehari**. Sebelum memanggil API, tugas menyemak sama ada kutipan berjaya sudah direkod pada hari tempatan Joomla yang sama. Jika sudah berjaya, percubaan lain dihentikan sehingga `Masa Kutipan` pada hari berikutnya. Jika kutipan gagal, tugas dijadualkan semula mengikut **Sela percubaan semula kutipan gagal**; contohnya nilai `500` akan mencuba semula setiap 500 minit selagi masa retry masih pada hari yang sama. Apabila sela itu melintasi tengah malam, kitaran baharu bermula pada masa kutipan harian yang ditetapkan.

Nilai cooldown turut dipaparkan dalam bentuk mudah baca, contohnya `500 minit = 8 jam 20 minit`. Rekod Kutipan menunjukkan sama ada masa seterusnya ialah retry kegagalan atau kitaran harian baharu. Jika `next_execution` terlewat lebih 15 minit tanpa task sedang berjalan, komponen memaparkan amaran supaya runner Joomla Scheduled Tasks atau cron hosting diperiksa.

Panel **Status Scheduler** pada halaman Tetapan memaparkan zon masa Joomla, masa kutipan harian seterusnya, pelaksanaan terakhir/seterusnya dan amaran bagi tugas hilang, tidak aktif, belum pernah berjalan, lewat atau gagal berulang kali. Panel itu turut menyediakan arahan cron hosting berasaskan lokasi Joomla sebenar untuk disalin. Runner cron disyorkan setiap 5 minit; sela ini hanya menentukan bila Joomla memeriksa tugas, manakala cooldown menentukan bila API dicuba semula selepas kegagalan.

Selepas memasang pembetulan zon masa ini, simpan semula halaman Tetapan untuk menukar tugas harian sedia ada kepada peraturan baharu. Simpan semula Tetapan juga selepas menukar zon masa Joomla supaya masa pelaksanaan seterusnya dikira semula dengan segera. Joomla 5.0/5.1 mentafsir peraturan cron dalam UTC; naik taraf kepada Joomla 5.2 atau lebih baharu diperlukan untuk tingkah laku zon masa ini.

**Had pustaka Joomla:** Ujian dengan Joomla 5.4.0 dan `cron-expression` 3.4.0 mendapati jadual boleh melangkau hari peralihan daylight saving musim bunga (contoh `Europe/Berlin`). Zon masa Malaysia tidak menggunakan daylight saving. Pengendalian peralihan ini bergantung pada pustaka cron Joomla.

**Nota operasi penting:** Kutipan analitik automatik bergantung pada Joomla Scheduled Tasks yang aktif dalam persekitaran hosting. Gunakan arahan cron yang dipaparkan dalam panel Status Scheduler (disyorkan setiap 5 minit) atau mekanisme runner setara daripada hosting. Pada server Linux dengan SSH, buka `crontab -e`, tampal baris yang dijana oleh komponen, simpan dan semak semula panel Status Scheduler. Tetapan ini hanya perlu dibuat sekali. Tanpa runner aktif, tugas boleh wujud dan aktif tetapi tidak akan dilaksanakan sehingga scheduler Joomla diproses.

## Tingkah Laku Singleton

SPLaSK Score menggunakan satu token, satu renderer dashboard dalaman dan satu tugas Joomla Scheduled Tasks bagi setiap laman Joomla. Komponen mencipta dan mengurus renderer tersebut secara automatik. Pendekatan ini mengelakkan token atau jadual scheduler bercanggah antara beberapa instance dan menjadikan halaman Tetapan satu-satunya sumber konfigurasi.

## Kemaskini Automatik

Modul ini menyokong Joomla Update Server.

Fail `updates.xml` dan `mod_splaskscore_update.xml` menyediakan metadata kemaskini, versi, dan URL muat turun pakej release yang disemak oleh Joomla.

Untuk calon release semasa, metadata kemaskini menunjuk kepada tag `v1.9.5` dan pakej `mod_splaskscore_v1.9.5.zip`. Installer modul tersebut turut membawa komponen dan kedua-dua plugin sokongan; pakej lengkap `pkg_splaskscore_v1.9.5.zip` disediakan untuk ujian pemasangan penuh.

## Skop Analitik Kekal (analytics_scope)

Laman menyimpan satu kunci skop analitik (`analytics_scope`, UUID 32 aksara) pada renderer dalaman. Kunci ini dijana secara automatik pada penggunaan pertama dan menjadi penanda sejarah yang **kekal**, jadi menukar token SPLaSK, menyunting gred, atau naik taraf pakej tidak lagi memisahkan sejarah lama daripada dashboard.

- Lajur `token_hash` dalam jadual sejarah dan kesihatan kini menyimpan kunci skop ini.
- Baris lama yang masih menyimpan hash token SHA-256 (64 aksara) akan **diadopsi secara automatik** ke dalam skop apabila dashboard analitik dibuka kali pertama selepas naik taraf. Tiada data hilang dan tiada migrasi manual diperlukan.
- Modal Sejarah & Analitik akan memaklumkan berapa banyak rekod lama yang telah dipautkan.
- Ingin memulakan buku sejarah baharu (contoh berpindah ke laman SPLaSK yang lain)? Padam nilai `analytics_scope` daripada parameter modul; kunci baharu akan dijana dan sejarah lama kekal di bawah kunci lama dalam pangkalan data.
- Modal Sejarah & Analitik membaca data daripada jadual `#__splaskscore_history` dan `#__splaskscore_health` sahaja. Kedua-duanya ditapis pada `module_id` bersama kunci skop, jadi tukar token tidak lagi menghasilkan dashboard kosong.
- **Semakan kebenaran (ACL):** membaca modal Sejarah & Analitik dan menyimpan snapshot dari dashboard tidak lagi bergantung pada pengetahuan token; ia memerlukan **Super User**, **`core.manage` pada `com_modules`**, atau **`core.edit` pada instance modul itu**. Pengguna tanpa kebenaran ini akan menerima mesej penafian dan bukan data.
- **Token SPLaSK dibaca daripada parameter modul (pangkalan data)** untuk semua tindakan pelayan — kutipan cron, butang Refresh, penulisan sejarah, dan penyimpanan catatan. Token yang dihantar oleh browser hanya diterima sebagai sandaran lama jika modul belum mempunyai token tersimpan, jadi tindakan pelayan tidak lagi bergantung pada halaman yang sudah lapuk.
- **Dashboard adalah pembaca sahaja:** ia memaparkan snapshot terakhir yang disimpan dalam pangkalan data (oleh cron atau butang Refresh). Tiada panggilan API dari browser, dan atribut `data-splask-token` tidak lagi wujud dalam HTML — token kekal di pihak pelayan sahaja. SPLaSK menerbitkan markah sekali sehari, jadi paparan ini sentiasa sepadan dengan jejak audit.
- **Jaminan tiada pendua di peringkat pangkalan data:** jadual sejarah mempunyai kolum terjana `history_day` bersama kunci unik `uniq_splaskscore_history_day` (`module_id`, `token_hash`, `history_day`). Pangkalan data sendiri akan menolak snapshot kedua bagi skop dan hari yang sama — perlindungan tidak lagi bergantung pada kod PHP sahaja.
- Log kesihatan dipangkas secara automatik (lalai 90 hari; boleh ubah melalui `Pengekalan Log Kesihatan`). Snapshot sejarah tidak terjejas.
- Nota `Rekod pendua diabaikan.` direkod sekali sahaja bagi setiap skop dan hari supaya log operasi kekal bersih.

## Workflow Wajib Sebelum PR / Release

Ujian integrasi zon masa boleh dijalankan terhadap direktori sumber Joomla 5.2+ yang mempunyai dependensi Composer. Ujian ini menggunakan kelas Scheduler dan pustaka cron sebenar dengan perkhidmatan aplikasi/pangkalan data diasingkan, tanpa mengakses konfigurasi atau pangkalan data laman:

```bash
php scripts/test_scheduler_timezone.php /path/to/joomla
```

Sebelum membuka sebarang PR atau menerbitkan release, jalankan simulasi installer Joomla dan validasi setempat:

```bash
python3 scripts/pre_pr_validation.py --release-tag v1.9.5
```

Semakan ini adalah disiplin wajib projek dan merangkumi:

- Simulasi pembinaan ZIP dalaman di `dist/internal/`.
- Pembinaan satu pakej pemasangan pengguna sahaja di `dist/release/pkg_splaskscore_v<version>.zip`.
- Pemeriksaan kandungan ZIP yang dijana.
- Pengesahan pembungkusan direktori `sql` apabila dideklarasikan dalam manifest.
- Pengesahan fail SQL install/uninstall wujud dan tidak kosong dalam ZIP.
- Sinkronisasi versi manifest modul, package manifest, plugin manifest, helper engine constant, dan update-server metadata.
- Penjajaran tag release `v<version>` dengan metadata manifest/update-server.
- Pengesahan URL muat turun dan nama pakej `mod_splaskscore_v<version>.zip`.
- Lint PHP untuk semua fail PHP modul dan plugin.
- Semakan sanity CSS untuk struktur, selector dashboard/analytics, dan mod gelap.
- Validasi sintaks JS apabila fail JS wujud.
- Ujian regresi skop sejarah/token (`scripts/test_history_scope.php`) yang mengunci tingkah laku normalisasi token dan format kunci skop.
- Ujian regresi kod status HTTP API (`scripts/test_api_http_errors.php`) untuk respons berjaya, status tidak diketahui, ralat 403/404/503, dan JSON tidak sah.
- Ujian regresi respons AJAX browser (`scripts/test_ajax_response_handling.js`) untuk JSON sah, HTML daripada HTTP 503, dan respons bukan JSON.
- Ujian regresi notifikasi dashboard (`scripts/test_dashboard_notifications.js`) untuk kandungan teks selamat, penggantian toast, aksesibiliti, animasi keluar dan auto-dismiss.
- Ujian UI komponen (`scripts/test_component_admin_ui.js`) untuk pengambilan token atas permintaan, pembuangan token selepas disembunyikan, pengekalan token ketika Save, penukaran sela retry, penyediaan countdown dan salinan arahan cron.
- Ujian integrasi zon masa scheduler dijalankan apabila `JOOMLA_SOURCE_ROOT` ditetapkan kepada direktori sumber Joomla 5.2+ (dilangkau jika tidak ditetapkan).
- Semakan konsistensi rendering analytics/dashboard, format ketepatan markah, tingkah laku dark/light appearance, `Semakan Seterusnya` date-only, `Tarikh Semakan` timestamp, jam Joomla live, dan timestamp analitik.

Jika semakan gagal, betulkan isu sebelum PR dibuat supaya masalah packaging, metadata, UI, dan release dikesan lebih awal.

### Verifikasi pada Joomla sebenar

Selepas memasang pakej pada laman Joomla ujian, jalankan verifier baca-sahaja mengikut senario. Skrip ini membaca fail dan pangkalan data Joomla tanpa mengubahnya:

```bash
php scripts/test_joomla_integration.php /path/to/joomla installed 1.9.5
php scripts/test_joomla_integration.php /path/to/joomla upgrade 1.9.5
php scripts/test_joomla_integration.php /path/to/joomla failed
php scripts/test_joomla_integration.php /path/to/joomla success
php scripts/test_joomla_integration.php /path/to/joomla skipped
php scripts/test_joomla_integration.php /path/to/joomla uninstalled
```

Fasa `failed`, `success` dan `skipped` dijalankan selepas mencetuskan senario tersebut melalui **Uji Kutipan Sekarang** atau runner Scheduled Tasks. Verifier memastikan pemasangan tunggal, plugin aktif, jadual DB, versi, pengekalan sejarah ketika naik taraf, status audit dan masa task seterusnya.

## Changelog

### v1.9.5 (Calon release, 28 September 2026) — Pembetulan mesej paparan token

- Membetulkan fungsi pembersihan mesej supaya klik ikon mata yang berjaya tidak lagi memaparkan ralat palsu.
- Membersihkan mesej ralat sekali lagi selepas respons token berjaya diterima.
- Menjadikan inisialisasi kawalan token idempoten supaya skrip berganda tidak memasang lebih daripada satu pengendali klik.
- Menambah ujian regresi bagi keadaan token dipaparkan tanpa mesej ralat.

### v1.9.4 (Calon release, 28 September 2026) — Paparan token AJAX tanpa refresh

- Memuatkan aset JavaScript pentadbir secara terus dengan URL berversi pada halaman Tetapan dan Rekod Kutipan, tanpa bergantung sepenuhnya pada pendaftaran Web Asset Manager.
- Memastikan klik ikon mata mengambil, memaparkan dan menyembunyikan token melalui AJAX tanpa memuat semula halaman.
- Mengekalkan aliran POST pelayan sebagai fallback selamat hanya apabila JavaScript tidak tersedia.
- Menambah cache-busting mengikut versi supaya browser tidak terus menggunakan skrip pentadbir lama selepas naik taraf.

### v1.9.3 (Calon release, 28 September 2026) — Butang token dengan fallback pelayan

- Menjadikan ikon mata sebagai butang POST sebenar dengan perlindungan CSRF dan ACL, supaya paparan token tetap berfungsi melalui muat semula Joomla walaupun JavaScript pentadbir gagal dimuatkan.
- Menambah aliran pelayan sekali guna: klik pertama memaparkan token, manakala klik seterusnya memuat semula halaman tanpa token.
- Memulakan kawalan JavaScript serta-merta apabila dokumen sudah siap, di samping laluan biasa `DOMContentLoaded`.
- Mengekalkan pengalaman AJAX tanpa muat semula apabila JavaScript tersedia dan menghalang penghantaran borang sandaran dalam keadaan itu.

### v1.9.2 (Calon release, 28 September 2026) — Paparan token teks yang konsisten

- Menggunakan medan teks baca-sahaja yang berasingan untuk memaparkan token tersimpan, supaya browser atau gaya medan kata laluan Joomla tidak boleh terus menutup aksara token.
- Mengosongkan nilai token yang dipaparkan dan membuang medan teks daripada pandangan apabila ikon mata ditekan semula atau borang dihantar.
- Mengukuhkan permintaan token dengan pengepala AJAX/JSON serta pengendalian respons JSON yang jelas.
- Menambah ujian regresi yang memastikan token sebenar masuk ke medan teks baca-sahaja, bukan ke medan kata laluan.

### v1.9.1 (Calon release, 28 September 2026) — Paparan token dan ACL

- Menghapuskan ikon mata kedua yang dijana oleh susun atur `PasswordField` Joomla dan menggunakan satu kawalan paparan token milik komponen.
- Menjana penanda token `••••••••••••••••` terus dalam HTML selepas Save tanpa mendedahkan nilai token sebenar.
- Mengekalkan pengambilan token atas permintaan, pembuangan token daripada halaman apabila disembunyikan dan pengekalan token lama apabila medan tidak diubah.
- Menambah pemeriksaan `core.manage` secara eksplisit pada semua halaman komponen dan endpoint Tetapan, di samping ACL modul yang diperlukan untuk menyimpan tetapan atau mendedahkan token.
- Memaparkan butang Options ACL pada setiap halaman komponen kepada pengguna yang mempunyai `core.admin` bagi `com_splaskscore`.
- Menambah nota pemasangan cron sekali sahaja melalui `crontab -e` bagi server Linux yang diurus melalui SSH.

### v1.9.0 (Calon release, 26 September 2026) — Komponen pengurusan administrator

- Menambah komponen administrator `com_splaskscore` dengan menu Analitik, Rekod Kutipan, Tetapan dan About.
- Menambah halaman Analitik yang menggunakan semula skop, KPI, carta, sejarah, kesihatan, catatan dan eksport CSV tanpa menduplikasi data.
- Menambah halaman Rekod Kutipan sebelum Tetapan untuk memantau status tugas dan kutipan automatik/manual yang berjaya atau gagal.
- Memulihkan maklumat About lama sebagai halaman komponen selepas Tetapan dengan versi yang dibaca terus daripada enjin.
- Mengekalkan `mod_splaskscore` sebagai widget Home Dashboard administrator.
- Menetapkan seni bina singleton: satu token, satu renderer dashboard dan satu sumber tetapan bagi setiap laman Joomla.
- Memindahkan kawalan Paparan Dashboard ke halaman Tetapan; suis itu menetapkan atau mengosongkan posisi `cpanel` tanpa menghentikan scheduler.
- Menyembunyikan SPLaSK Score daripada Add Module, mengalihkan suntingan Module Manager ke Tetapan komponen dan menyekat instance kedua di peringkat pelayan.
- Membolehkan semua parameter diurus hanya daripada komponen tanpa memindahkan token, sejarah atau skop analitik.
- Menyelaraskan Joomla Scheduler selepas tetapan disimpan melalui komponen.
- Menetapkan satu kutipan automatik berjaya bagi setiap hari tempatan Joomla; selepas berjaya, tugas menunggu hari berikutnya tanpa memanggil API semula.
- Menukar cooldown kepada sela percubaan semula bagi kutipan gagal pada hari yang sama dan memaparkan masa retry melalui Rekod Kutipan.
- Melindungi token dengan medan bertopeng dan ikon mata sahaja; token tidak dihantar dalam HTML awal, hanya diambil daripada endpoint ber-ACL apabila diminta, dibuang semula apabila disembunyikan, dan medan kosong ketika Save mengekalkan nilai sedia ada.
- Menambah panel Status Scheduler dalam Tetapan dengan zon masa Joomla, masa harian seterusnya, pelaksanaan terakhir/seterusnya, pemeriksaan kesihatan serta arahan cron hosting yang boleh disalin.
- Menambah status `Dilangkau`, sebab pelaksanaan seterusnya, countdown, amaran scheduler lewat, ujian kutipan segera dan eksport CSV yang dilindungi daripada formula injection.
- Menambah paparan cooldown mudah baca serta gate regresi UI komponen.
- Mengasingkan ZIP dalaman ke `dist/internal` dan menyediakan hanya pakej penuh untuk pengguna di `dist/release`.
- Menambah verifier baca-sahaja untuk senario pemasangan, naik taraf, gagal, berjaya, dilangkau dan uninstall pada instance Joomla sebenar.
- Menambah komponen ke dalam installer modul dan pakej Joomla lengkap.
- Tiada perubahan dibuat kepada logik markah, API, sejarah atau paparan dashboard sedia ada selain integrasi komponen.

### v1.8.8 (18 September 2026) — Notifikasi toast premium dalam modal

- Menggantikan `window.alert` dan mesej global Joomla dengan toast khusus di bahagian atas modal analitik.
- Menambah tema hijau untuk kejayaan dan merah/crimson untuk ralat, bersama blur latar, bayang lembut, bentuk pill dan animasi masuk/keluar.
- Toast menggunakan kandungan teks selamat, atribut aksesibiliti mengikut jenis, menggantikan notifikasi lama dan hilang secara automatik selepas lima saat.
- Menambah ujian regresi lifecycle notifikasi serta pengawal JavaScript/CSS dalam gate release.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.8.8`.

### v1.8.7 (18 September 2026) — Ralat AJAX yang jelas dan popup kejayaan

- Menghentikan respons HTTP bukan-2xx sebelum badan HTML pelayan cuba diparse sebagai JSON, termasuk ralat 403, 404, 500 dan 503.
- Menukar respons HTML atau respons bukan JSON berstatus 200 kepada mesej yang jelas mengenai kemungkinan gangguan firewall atau pelayan.
- Menambah popup kejayaan selepas Segar Semula menerima payload markah SPLaSK yang sah.
- Menambah ujian regresi JavaScript bagi respons JSON sah, HTTP 503 dengan HTML, dan HTML bukan JSON.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.8.7`.

### v1.8.6 (18 September 2026) — Cache buster aset dashboard

- Menambah versi enjin pada URL CSS dan JavaScript dashboard, contohnya `splaskscore.js?v=1.8.6`, supaya pelayar memuatkan aset baharu selepas naik taraf.
- Menggunakan versi release yang stabil sebagai cache buster agar cache pelayar masih berfungsi sepanjang versi yang sama.
- Menambah pengawal validasi yang memastikan kedua-dua aset dashboard kekal menggunakan versi enjin.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.8.6`.

### v1.8.5 (18 September 2026) — Kekalkan markah terakhir dan paparkan kod ralat HTTP

- Dashboard terus memaparkan snapshot terakhir yang sah apabila cubaan API terkini gagal; cap masa kegagalan masih direkodkan untuk audit.
- Status sebenar Joomla Scheduled Task dikekalkan: kutipan yang gagal masih dipulangkan sebagai kegagalan kepada scheduler.
- Klien HTTP Joomla dan cURL kini menangkap kod status HTTP dan melaporkan 403, 404, 503 serta ralat bukan-200 lain dalam mesej kegagalan.
- Butang Segar Semula memaparkan mesej kegagalan pelayan melalui sistem mesej Joomla, dengan dialog pelayar sebagai sandaran.
- Menambah ujian regresi kod status HTTP dan menghubungkannya kepada validasi sebelum release.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.8.5`.

### v1.8.4 (18 September 2026) — Penyelenggaraan repositori

- Menambah `.gitignore` untuk output binaan tempatan.
- Membuang rujukan mati kepada folder `fields/` daripada workflow release dan validator.
- Menambah pengawal supaya `.gitignore` tidak termasuk dalam ZIP pemasangan.
- Tiada perubahan tingkah laku runtime berbanding v1.8.3.

### v1.8.3 (18 September 2026) — Buang kod mati fields/ yang bertembung huruf besar/kecil  - Membuang folder `fields/` yang tidak pernah dimuatkan oleh Joomla: `ModuleModel::getForm()` hanya mendaftar `.../modules/<module>/field` (tunggal), jadi `fields` (jamak) tidak boleh dirujuk. - Ini menghapuskan pertembungan `fields/AboutMetadata.php` lawan `fields/aboutmetadata.php` yang membuat Git sentiasa menunjukkan fail itu sebagai berubah pada Windows, dan berisiko menghasilkan pengisytiharan kelas berganda pada pemasangan Linux. - Panel About **tidak terjejas**: ia dirender oleh field `note` dalam manifest, bukan oleh field tersuai yang dibuang itu. - Membuang `<folder>fields</folder>` daripada manifest supaya pemasang tidak mencari folder yang sudah tiada. - Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.8.3`.

### v1.8.2 (18 September 2026) — Kunci skop digunakan untuk semua bacaan dashboard

- Membetulkan bacaan awal dashboard (`kesihatan analitik` dan `mini-trend`) yang masih menghash token secara terus, jadi ia mencari baris di bawah hash token lama dan bukan skop modul.
- Menambah pembantu awam `getAnalyticsScopeKey()` supaya hanya ada satu cara menentukan kunci skop, digunakan oleh template dan semua laluan lain.
- Menambah semakan gate: validasi kini **gagal** jika mana-mana fail dalam `tmpl/` menghash token secara terus.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.8.2`.

### v1.8.1 (18 September 2026) — Panel About diselaraskan dan disegerakkan automatik

- Membetulkan panel About yang masih memaparkan versi `1.6.26` sejak v1.7.0 — kini ia menunjukkan versi release semasa.
- `scripts/sync_release_metadata.py` kini turut menulis versi panel About, jadi nombor versi itu disegerakkan automatik semasa release dan tidak boleh tersasar lagi.
- `scripts/validate_release_metadata.py` kini **gagal** jika versi panel About tidak sepadan dengan versi manifest.
- Menambah fakta seni bina pada panel About: skop analitik kekal dan jaminan snapshot harian di peringkat pangkalan data.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.8.1`.

### v1.8.0 (18 September 2026) — Dashboard menjadi pembaca sahaja

- Dashboard **tidak lagi memanggil API SPLaSK dari browser**. Ia memaparkan snapshot terakhir yang sudah disimpan oleh cron atau butang Segar Semula Analitik.
- Atribut `data-splask-token` **dibuang sepenuhnya** daripada HTML, jadi token tidak lagi terdedah dalam kod sumber halaman pentadbir.
- Skor, gred, status, `Tarikh Semakan`, `Semakan Seterusnya`, dan pautan pengesahan dirender oleh pelayan daripada pangkalan data; JavaScript hanya menyegarkan paparan selepas butang Segar Semula berjaya.
- Menambah keadaan kosong yang jelas: apabila tiada snapshot lagi, dashboard memaparkan *"Tiada rekod lagi"* dan mengarahkan pentadbir menekan Segar Semula atau menyemak Joomla Scheduled Tasks.
- Menambah label *"Setakat &lt;tarikh&gt;"* supaya jelas markah yang dipaparkan ialah kutipan terakhir (SPLaSK menerbitkan markah sekali sehari).
- Menambah semakan gate supaya panggilan API browser tidak boleh masuk semula ke dashboard.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.8.0`.

### v1.7.2 (18 September 2026) — Token SPLaSK daripada pangkalan data untuk semua tindakan

- Butang Refresh kini membaca token daripada parameter modul (`#__modules.params`), bukan daripada permintaan browser. Jika token belum dikonfigurasi, ia memulangkan mesej yang jelas dan bukan kegagalan API yang kabur.
- Laluan AJAX lain (modal sejarah, simpan snapshot dashboard, simpan catatan) turut menggunakan token tersimpan sebagai sumber utama; token dari browser hanya sandaran lama.
- Menambah pembantu `moduleToken()` dan `resolveActionToken()` supaya hanya ada satu tempat token dibaca untuk tindakan pelayan.
- Menambah semakan token autoriti dalam gate validasi.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.7.2`.

### v1.7.1 (18 September 2026) — Jaminan tiada pendua & log kesihatan terurus

- Menambah kolum terjana `history_day` dan kunci unik `uniq_splaskscore_history_day` pada jadual sejarah, jadi snapshot pendua ditolak oleh pangkalan data sendiri, bukan hanya oleh kod PHP.
- Migrasi automatik untuk pemasangan sedia ada: kolum dan kunci ditambah pada penggunaan pertama selepas naik taraf, selepas pendua lama dikolaps terlebih dahulu.
- Menambah pengekalan log kesihatan (lalai 90 hari, minimum 7 hari) supaya jadual kesihatan tidak membesar tanpa had.
- Nota `Rekod pendua diabaikan.` kini direkod maksimum sekali sehari bagi setiap skop.
- Menambah semakan kebenaran (ACL) pada laluan baca dan tulis sejarah analitik dari dashboard, supaya token tidak lagi menjadi kunci akses tersembunyi.
- Menambah semakan token jaminan pendua harian dalam gate validasi.
- Menyelaraskan metadata modul, plugin, pakej dan pelayan kemaskini kepada `1.7.1`.

### v1.7.0 (18 September 2026) — Skop analitik kekal & pemulihan sejarah

- Menambah `analytics_scope` (UUID) sebagai penanda sejarah yang kekal bagi setiap instance modul, menggantikan hash token sebagai kunci sejarah.
- Sejarah sedia ada dalam pangkalan data diadopsi secara automatik ke dalam skop tersebut apabila dashboard analitik dibuka — tiada data hilang dan tiada migrasi manual.
- Menormalkan token SPLaSK (buang ruang, newline, non-breaking space, dan BOM di hujung sahaja) supaya semua laluan kod menghasilkan kunci yang sama.
- Menambah notis operator dalam modal Sejarah & Analitik apabila rekod lama dipautkan ke skop baharu.
- Membaiki harness `scripts/test_scheduler_timezone.php` yang sebelum ini tidak dapat dijalankan kerana autoload `Webmozart\Assert` tidak didaftarkan.
- Menambah ujian regresi `scripts/test_history_scope.php` dan menyambungkan kedua-dua ujian ke `scripts/pre_pr_validation.py`.
- Menyelaraskan metadata modul, plugin, pakej, panel About dan pelayan kemaskini kepada `1.7.0`.

### v1.6.26 (18 September 2026) — Joomla timezone scheduling fix

- Membetulkan kutipan harian supaya `Masa Kutipan` mengikuti zon masa laman Joomla pada Joomla 5.2 dan lebih baharu. Contohnya, `06:00` dengan `Asia/Kuala_Lumpur` bermaksud 6:00 pagi waktu Malaysia.
- Menggunakan peraturan cron Joomla untuk kutipan harian; kutipan setiap jam kekal pada sela satu jam.
- Menambah ujian integrasi zon masa dan mendokumenkan had daylight saving dalam pustaka cron Joomla.
- Menyelaraskan metadata modul, plugin, pakej, panel About dan pelayan kemaskini kepada `1.6.26`.
- Selepas naik taraf, simpan semula tetapan modul untuk menyelaraskan tugas harian sedia ada. Runner Joomla Scheduled Tasks mesti aktif pada hosting.

### Evolusi Release Enterprise Terkini

Rangkaian release terkini disusun sebagai perkembangan produk yang berurutan: asas analitik operasi dimantapkan dahulu, paparan graf diperhalus, telemetry dashboard disatukan, konfigurasi branding enterprise dibuka kepada pentadbir, panel About diperhalus sebagai metadata produk enterprise, kronologi release disegerakkan, cadence operasi dashboard dipermudah dengan jam timezone Joomla, dan akhirnya paparan `Semakan Seterusnya` diperhalus tanpa mengubah timestamp audit lain.

**v1.6.5 (18 Mei 2026) — Semakan Seterusnya date-only display refinement**

- Memperhalus paparan kad `Semakan Seterusnya` supaya hanya hari dan tarikh dipaparkan, contohnya `Selasa • 19 Mei 2026`, tanpa masa.
- Mengekalkan `Tarikh Semakan` dengan timestamp penuh, jam live dashboard berasaskan timezone Joomla, dan timestamp analitik/audit tanpa perubahan runtime.
- Menyelaraskan manifest, package manifest, plugin manifest, helper engine constant, update-server metadata, URL muat turun, About panel, dan README kepada `v1.6.5`.
- Metadata release disegerakkan untuk tag `v1.6.5` dan pakej `mod_splaskscore_v1.6.5.zip`.

**v1.6.4 (18 Mei 2026) — Operational cadence simplification and Joomla-timezone live clock**

- Memastikan `Semakan Seterusnya` dikira secara konsisten daripada `Tarikh Semakan + 1 hari` sebagai cadence operasi dashboard.
- Menambah jam telemetry masa nyata pada dashboard pentadbir berdasarkan timezone Joomla supaya konteks masa semasa jelas tanpa bergantung pada timezone browser semata-mata.
- Mengekalkan timestamp `Tarikh Semakan` dan timestamp analitik/audit sebagai rekod masa operasi yang lengkap.
- Metadata release disegerakkan untuk tag `v1.6.4` dan pakej `mod_splaskscore_v1.6.4.zip`.

**v1.6.3 (17 Mei 2026) — Release metadata synchronization hotfix**

- Menyelaraskan semua rujukan metadata release selepas refinement v1.6.2 bagi memastikan kronologi deployment dan package identity kekal konsisten.
- Menyegerakkan manifest, update metadata, plugin metadata, About panel version display, dan rujukan README kepada `v1.6.3`.
- Metadata release disegerakkan untuk tag `v1.6.3` dan pakej `mod_splaskscore_v1.6.3.zip`.

**v1.6.2 (17 Mei 2026) — About metadata panel refinement**

- Memperhalus tab About kepada panel metadata enterprise yang lebih kemas tanpa rupa input readonly, dengan hierarki tipografi dan jarak yang lebih konsisten.
- Menyelaraskan maklumat organisasi rasmi serta membuang seksyen Release Target untuk pengalaman metadata yang lebih bersih dan profesional.
- Metadata release disegerakkan untuk tag `v1.6.2` dan pakej `mod_splaskscore_v1.6.2.zip`.

**v1.6.1 (17 Mei 2026) — Enterprise branding/configuration**

- Menambah tab Configuration untuk mengurus tajuk dashboard, tajuk/subtajuk analitik, label butang, label KPI, label graf, dan label jadual sejarah melalui parameter modul Joomla.
- Menambah tab About sebagai permulaan metadata produk yang meliputi produk, owner, organisasi, repository, issue tracker, compatibility, dan release channel.
- Metadata release disegerakkan untuk tag `v1.6.1` dan pakej `mod_splaskscore_v1.6.1.zip`.

**v1.6.0 (17 Mei 2026) — Unified telemetry dashboard**

- Menyatukan modal `Sejarah & Analitik SPLaSK` sebagai permukaan telemetry enterprise dengan rail KPI dan carta dalam satu komposisi visual yang konsisten.
- Mengoptimumkan nisbah KPI/carta, irama jarak dalaman, dan ruang menegak carta tanpa mengubah sumber dataset analitik atau label tarikh paksi-x.
- Metadata release disegerakkan untuk tag `v1.6.0` dan pakej `mod_splaskscore_v1.6.0.zip`.

**v1.5.8 (17 Mei 2026) — Graph visual balance refinement**

- Memperhalus keseimbangan visual graf melalui ruang paksi-x, anchoring tick, alignment tepi, dan jarak bawah yang lebih terkawal.
- Mengurangkan keagresifan label condong supaya tarikh operasi kekal lengkap tetapi lebih bersih dalam susun atur enterprise yang padat.
- Metadata release disegerakkan untuk tag `v1.5.8` dan pakej `mod_splaskscore_v1.5.8.zip`.

**v1.5.7 (17 Mei 2026) — Operational analytics foundation**

- Memantapkan kebolehbacaan graf analitik dengan paparan semua tarikh paksi-x untuk setiap titik data operasi.
- Mengekalkan normalisasi satu rekod sehari, dataset analitik bersatu, pagination frontend, dan struktur dashboard operasi sebagai asas kesinambungan audit.
- Metadata release disegerakkan untuk tag `v1.5.7` dan pakej `mod_splaskscore_v1.5.7.zip`.

### Release Terdahulu

**v1.5.6 (16 Mei 2026)**

- Menjadikan graf mini `Trend 7 Hari` dashboard berpunca daripada dataset sejarah analitik sebenar yang sama dengan graf modal, dipotong kepada tujuh titik terkini tanpa gelombang sintetik.
- Menambah kolum `Masa Semakan` di sebelah `Tarikh` dalam jadual analitik untuk audit masa operasi tanpa menggabungkan tarikh dan masa.
- Metadata release disegerakkan untuk tag `v1.5.6` dan pakej `mod_splaskscore_v1.5.6.zip`.

**v1.5.4 (16 Mei 2026)**

- Memisahkan dataset carta 30 hari daripada jadual sejarah analitik penuh supaya pagination memaparkan semua rekod DB.
- Menukar KPI `Jumlah Rekod` kepada kiraan `COUNT(*)` sebenar dan mematikan pangkasan sejarah automatik secara lalai untuk kesinambungan audit enterprise.
- Metadata release disegerakkan untuk tag `v1.5.4` dan pakej `mod_splaskscore_v1.5.4.zip`.

**v1.5.3 (16 Mei 2026)**

- Menukar tarikh operasi dashboard kepada format Bahasa Melayu boleh baca seperti `17 Mei 2026` tanpa slash, label hari, masa, atau pemisah bullet.
- Memusatkan rail KPI analitik operasi untuk `Skor Hari Ini`, `Skor Terendah`, dan `Jumlah Rekod` supaya komposisi lebih padat dan seimbang.
- Metadata release disegerakkan untuk tag `v1.5.3` dan pakej `mod_splaskscore_v1.5.3.zip`.

**v1.5.2 (16 Mei 2026)**

- Mengkonsolidasi paparan kepada satu sistem analitik operasi Grid Operasi tanpa pilihan preset dashboard lain.
- Memusatkan hierarki KPI utama kepada struktur `100% / GRED A / Cemerlang`, membuang kad Status Pematuhan dan Penjadual, serta menukar label kepada Semakan Seterusnya.
- Menyamakan graf mini dashboard dengan bahasa visual graf analitik melalui garis bercahaya minimal tanpa paksi, label, atau tooltip.
- Memadatkan modal analitik dengan KPI `Skor Hari Ini`, tarikh di bawah skor, dan `Skor Terendah`.
- Metadata release disegerakkan untuk tag `v1.5.2` dan pakej `mod_splaskscore_v1.5.2.zip`.

**v1.5.1 (16 Mei 2026)**

- Memperkemas irama papan pemuka eksekutif, operasi, dan keselamatan digital dengan susun atur KPI lebih padat serta rasa enterprise premium.
- Menambah graf mikro 7 hari yang berbeza bagi setiap preset: sparkline eksekutif, bar operasi, dan gelombang isyarat keselamatan digital.
- Menukar teks dashboard dan analitik kepada Bahasa Melayu yang lebih konsisten serta membuang nama preset daripada paparan awam.
- Metadata release disegerakkan untuk tag `v1.5.1` dan pakej `mod_splaskscore_v1.5.1.zip`.

**v1.5.0 (16 Mei 2026)**

- Mengkonsolidasi preset dashboard kepada hanya 3 mod premium dalaman untuk laporan eksekutif, grid operasi, dan konsol keselamatan digital.
- Menyelaraskan widget dashboard dan modal Sejarah & Analitik supaya setiap mod mempunyai struktur DOM, hierarki KPI, rawatan graf, dan personaliti visual tersendiri.
- Mengekalkan pagination sejarah, carta analitik 30 hari, tooltip carta yang mudah dibaca, asas scheduler, dan metadata release `v1.5.0`.

**v1.3.0 (12 Mei 2026)**

- Menstabilkan sejarah analitik dengan pencegahan snapshot pendua, trend berdasarkan rekod bermakna yang distinct, dan rendering carta yang mengabaikan salinan identik.
- Memindahkan tindakan refresh hanya ke modal Sejarah & Analitik serta menyatukan gaya butang refresh/tutup modal.
- Metadata release disegerakkan untuk versi manifest/update server, URL muat turun, tag `v1.3.0`, dan pakej `mod_splaskscore_v1.3.0.zip`.

**v1.2.9 (12 Mei 2026)**

- Memperkemas UI dashboard dan sejarah analitik dengan refresh icon-only yang ringan, membuang metadata operasi daripada paparan, dan menyelaraskan metadata release `v1.2.9`.

**v1.2.8 (11 Mei 2026)**

- Metadata release disegerakkan untuk versi manifest/update server, URL muat turun, tag `v1.2.8`, dan pakej `mod_splaskscore_v1.2.8.zip`.

**v1.2.5 (11 Mei 2026)**

- Menambah nota operasi bahawa kutipan analitik automatik memerlukan infrastruktur Joomla Scheduled Tasks/cron hosting aktif, serta mendokumentasikan tingkah laku scheduler multi-modul yang menggunakan satu tugas scheduler dikongsi.
- Workflow release disegerakkan untuk versi manifest/update server, URL muat turun, tag `v1.2.5`, dan pakej `mod_splaskscore_v1.2.5.zip`.
- Validasi ZIP kini mengesan kandungan direktori melalui prefix fail, bukan entri folder eksplisit.
- Format markah membuang sifar perpuluhan yang tidak perlu dan tarikh PHP/JS menggunakan pemprosesan UTC deterministik.

**v1.1.6 (22 Julai 2025)**

- Logik penggredan baharu:
  - Gred A (100 sahaja), B (95-99), C (91-94), D (86-90), GAGAL (85 ke bawah)
- Semua label paparan gred kini "Gred ..."
- Fail manifest & update server dikemaskini
- README & versi seragam

**v1.1.5**

- Penambahbaikan logik penggredan:
  - Gred A (95-100), B (91-94), C (86-90), GAGAL (85 ke bawah)
- Fail manifest & update server dikemaskini.
- Versi & tarikh diseragamkan.

## Maklumat Tambahan

- Dibangunkan oleh: **Muhammad Azizan Hazim**
- Versi: **1.6.5**
- Tarikh: **18 Mei 2026**

## Lesen

Kod ini dilesenkan di bawah [GNU General Public License v3.0](LICENSE.txt).

Anda bebas menggunakan, mengubah suai, dan mengedarkan kod ini, dengan syarat:

- Menyertakan notis hak cipta asal.
- Menyertakan lesen GPL.
- Jika anda edarkan semula versi ubah suai, anda mesti membuka kod tersebut kepada umum di bawah lesen yang sama.

Lesen ini direka untuk memastikan kebebasan penggunaan dan pengubahsuaian dalam komuniti sumber terbuka.
