# mod_splaskscore

Modul Joomla untuk memaparkan markah penilaian dan tarikh kemaskini terakhir dari sistem SPLaSK (Sistem Pemantauan Laman Web dan Perkhidmatan Dalam Talian).

## Fungsi Utama

- Paparan markah penilaian SPLaSK melalui API rasmi.
- Tarikh kemaskini terakhir dan semakan seterusnya.
- Tiada tracking pelawat – hanya integrasi API.
- Menyokong semakan kemaskini automatik melalui GitHub (update server).

## Cara Pasang

1. Muat turun `mod_splaskscore_v1.5.4.zip` dari tab [Releases](https://github.com/hazatmda/mod_splaskscore/releases).
2. Pasang di Joomla: **Extensions > Manage > Install**.
3. Masukkan token SPLaSK anda dalam konfigurasi modul.


## Automasi Analitik & Joomla Scheduled Tasks

Tetapan modul ialah panel kawalan utama untuk automasi analitik. Selepas pemasangan atau simpanan modul, SPLaSK Score akan cuba memasang/mengaktifkan plugin Scheduler, mencipta tugas Joomla Scheduled Tasks yang diperlukan, dan menyelaraskan status aktif, frekuensi, masa kutipan, duplicate cooldown, retention days, serta had rekod sejarah daripada parameter modul.

**Nota operasi penting:** Kutipan analitik automatik bergantung pada Joomla Scheduled Tasks yang aktif dalam persekitaran hosting. Pastikan infrastruktur Joomla Scheduled Tasks/cron di hosting anda berjalan untuk jaminan kutipan automatik; tanpa runner Scheduled Tasks yang aktif, tugas boleh wujud dan aktif tetapi tidak akan dilaksanakan sehingga scheduler Joomla diproses.

### Tingkah Laku Multi-Modul

SPLaSK Score menggunakan satu tugas Joomla Scheduled Tasks yang dikongsi untuk rutin `splaskscore.analytics.collect`. Semasa tugas dijalankan, collector memproses semua instance modul administrator yang published, mempunyai token, dan mengaktifkan **Kutipan Analitik Automatik**.

Untuk mengelakkan beberapa module instance saling menulis jadual scheduler yang sama semasa install/upgrade, bootstrap installer hanya menyelaraskan instance modul published pertama/terkini yang ditemui. Selepas itu, apabila mana-mana instance modul disimpan, instance terakhir yang disimpan akan menjadi sumber tetapan jadual bagi tugas scheduler yang dikongsi. Jika anda memasang beberapa instance modul, gunakan satu instance utama sebagai sumber tetapan automation bagi masa/frekuensi scheduler, sementara semua instance published yang enabled masih akan dikutip ketika scheduler berjalan.

## Kemaskini Automatik

Modul ini menyokong Joomla Update Server.

Fail `mod_splaskscore_update.xml` menyediakan maklumat kemaskini dan disemak secara automatik oleh Joomla.


## Workflow Wajib Sebelum PR / Release

Sebelum membuka sebarang PR atau menerbitkan release, jalankan simulasi installer Joomla dan validasi setempat:

```bash
python3 scripts/pre_pr_validation.py
```

Semakan ini adalah disiplin wajib projek dan merangkumi:

- Simulasi pembinaan ZIP installer Joomla di `dist/mod_splaskscore_v<version>.zip`.
- Pemeriksaan kandungan ZIP yang dijana.
- Pengesahan pembungkusan direktori `sql` apabila dideklarasikan dalam manifest.
- Pengesahan fail SQL install/uninstall wujud dan tidak kosong dalam ZIP.
- Sinkronisasi versi `mod_splaskscore.xml`, `updates.xml`, dan versi dalam deskripsi manifest.
- Penjajaran tag release `v<version>` dengan metadata manifest/update-server.
- Lint PHP untuk semua fail PHP modul.
- Semakan sanity CSS untuk struktur, selector dashboard/analytics, dan mod gelap.
- Validasi sintaks JS apabila fail JS wujud.
- Semakan konsistensi rendering analytics/dashboard, format ketepatan markah, serta tingkah laku dark/light appearance.

Jika semakan gagal, betulkan isu sebelum PR dibuat supaya masalah packaging, metadata, UI, dan release dikesan lebih awal.

## Changelog

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
- Versi: **1.5.4**
- Tarikh: **16 Mei 2026**

## Lesen

Kod ini dilesenkan di bawah [GNU General Public License v3.0](LICENSE.txt).

Anda bebas menggunakan, mengubah suai, dan mengedarkan kod ini, dengan syarat:
- Menyertakan notis hak cipta asal.
- Menyertakan lesen GPL.
- Jika anda edarkan semula versi ubah suai, anda mesti membuka kod tersebut kepada umum di bawah lesen yang sama.

Lesen ini direka untuk memastikan kebebasan penggunaan dan pengubahsuaian dalam komuniti sumber terbuka.
