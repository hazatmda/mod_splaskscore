# mod_splaskscore

Modul Joomla untuk memaparkan markah penilaian dan tarikh kemaskini terakhir dari sistem SPLaSK (Sistem Pemantauan Laman Web dan Perkhidmatan Dalam Talian).

## Fungsi Utama

- Paparan markah penilaian SPLaSK melalui API rasmi.
- Paparan `Tarikh Semakan` dengan timestamp penuh untuk audit operasi.
- Paparan `Semakan Seterusnya` sebagai hari dan tarikh sahaja, dikira daripada `Tarikh Semakan + 1 hari`.
- Jam telemetry masa nyata berasaskan timezone Joomla pada dashboard pentadbir.
- Tiada tracking pelawat – hanya integrasi API dan sejarah analitik operasi pentadbir.
- Menyokong semakan kemaskini automatik melalui GitHub (update server).

## Cara Pasang

1. Muat turun `mod_splaskscore_v1.8.1.zip` dari tab [Releases](https://github.com/hazatmda/mod_splaskscore/releases).
2. Pasang di Joomla: **Extensions > Manage > Install**.
3. Masukkan token SPLaSK anda dalam konfigurasi modul.
4. Semak tetapan automasi analitik jika mahu kutipan sejarah berjalan melalui Joomla Scheduled Tasks.

## Automasi Analitik & Joomla Scheduled Tasks

Tetapan modul ialah panel kawalan utama untuk automasi analitik. Selepas pemasangan atau simpanan modul, SPLaSK Score akan cuba memasang/mengaktifkan plugin Scheduler, mencipta tugas Joomla Scheduled Tasks yang diperlukan, dan menyelaraskan status aktif, frekuensi, masa kutipan, duplicate cooldown, retention days, serta had rekod sejarah daripada parameter modul.

Pada Joomla 5.2 dan lebih baharu, kutipan **Harian** menggunakan peraturan cron Joomla yang mengikuti zon masa laman dalam Global Configuration. Contohnya, `Masa Kutipan = 06:00` dengan zon masa `Asia/Kuala_Lumpur` bermaksud 6:00 pagi waktu Malaysia. Joomla menyimpan masa pelaksanaan seterusnya dalam UTC dan mengira jadual berikutnya menggunakan zon masa laman. Kutipan **Setiap Jam** kekal pada sela satu jam.

Selepas memasang pembetulan zon masa ini, simpan semula modul untuk menukar tugas harian sedia ada kepada peraturan baharu. Simpan semula modul juga selepas menukar zon masa Joomla supaya masa pelaksanaan seterusnya dikira semula dengan segera. Joomla 5.0/5.1 mentafsir peraturan cron dalam UTC; naik taraf kepada Joomla 5.2 atau lebih baharu diperlukan untuk tingkah laku zon masa ini.

**Had pustaka Joomla:** Ujian dengan Joomla 5.4.0 dan `cron-expression` 3.4.0 mendapati jadual boleh melangkau hari peralihan daylight saving musim bunga (contoh `Europe/Berlin`). Zon masa Malaysia tidak menggunakan daylight saving. Pengendalian peralihan ini bergantung pada pustaka cron Joomla.

**Nota operasi penting:** Kutipan analitik automatik bergantung pada Joomla Scheduled Tasks yang aktif dalam persekitaran hosting. Pastikan infrastruktur Joomla Scheduled Tasks/cron di hosting anda berjalan untuk jaminan kutipan automatik; tanpa runner Scheduled Tasks yang aktif, tugas boleh wujud dan aktif tetapi tidak akan dilaksanakan sehingga scheduler Joomla diproses.

## Tingkah Laku Multi-Modul

SPLaSK Score menggunakan satu tugas Joomla Scheduled Tasks yang dikongsi untuk rutin `splaskscore.analytics.collect`. Semasa tugas dijalankan, collector memproses semua instance modul administrator yang published, mempunyai token, dan mengaktifkan **Kutipan Analitik Automatik**.

Untuk mengelakkan beberapa module instance saling menulis jadual scheduler yang sama semasa install/upgrade, bootstrap installer hanya menyelaraskan instance modul published pertama/terkini yang ditemui. Selepas itu, apabila mana-mana instance modul disimpan, instance terakhir yang disimpan akan menjadi sumber tetapan jadual bagi tugas scheduler yang dikongsi. Jika anda memasang beberapa instance modul, gunakan satu instance utama sebagai sumber tetapan automation bagi masa/frekuensi scheduler, sementara semua instance published yang enabled masih akan dikutip ketika scheduler berjalan.

## Kemaskini Automatik

Modul ini menyokong Joomla Update Server.

Fail `updates.xml` dan `mod_splaskscore_update.xml` menyediakan metadata kemaskini, versi, dan URL muat turun pakej release yang disemak oleh Joomla.

Untuk release semasa, metadata kemaskini menunjuk kepada tag `v1.8.1` dan pakej `mod_splaskscore_v1.8.1.zip`.

## Skop Analitik Kekal (analytics_scope)

Setiap instance modul menyimpan satu kunci skop analitik (`analytics_scope`, UUID 32 aksara) dalam parameter modul. Kunci ini dijana secara automatik pada penggunaan pertama dan menjadi penanda sejarah yang **kekal**, jadi menukar token SPLaSK, menyunting gred, atau naik taraf pakej tidak lagi memisahkan sejarah lama daripada dashboard.

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
python3 scripts/pre_pr_validation.py --release-tag v1.8.1
```

Semakan ini adalah disiplin wajib projek dan merangkumi:

- Simulasi pembinaan ZIP installer Joomla di `dist/mod_splaskscore_v<version>.zip`.
- Simulasi pembinaan pakej Joomla di `dist/pkg_splaskscore_v<version>.zip`.
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
- Ujian integrasi zon masa scheduler dijalankan apabila `JOOMLA_SOURCE_ROOT` ditetapkan kepada direktori sumber Joomla 5.2+ (dilangkau jika tidak ditetapkan).
- Semakan konsistensi rendering analytics/dashboard, format ketepatan markah, tingkah laku dark/light appearance, `Semakan Seterusnya` date-only, `Tarikh Semakan` timestamp, jam Joomla live, dan timestamp analitik.

Jika semakan gagal, betulkan isu sebelum PR dibuat supaya masalah packaging, metadata, UI, dan release dikesan lebih awal.

## Changelog

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
