# mod_splaskscore

Modul Joomla untuk memaparkan markah penilaian dan tarikh kemaskini terakhir dari sistem SPLaSK (Sistem Pemantauan Laman Web dan Perkhidmatan Dalam Talian).

## Fungsi Utama

- Paparan markah penilaian SPLaSK melalui API rasmi.
- Tarikh kemaskini terakhir dan semakan seterusnya.
- Tiada tracking pelawat – hanya integrasi API.
- Menyokong semakan kemaskini automatik melalui GitHub (update server).

## Cara Pasang

1. Muat turun `mod_splaskscore_v1.2.4.zip` dari tab [Releases](https://github.com/hazatmda/mod_splaskscore/releases).
2. Pasang di Joomla: **Extensions > Manage > Install**.
3. Masukkan token SPLaSK anda dalam konfigurasi modul.

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

**v1.2.4 (11 Mei 2026)**
- Workflow release disegerakkan untuk versi manifest/update server, URL muat turun, tag `v1.2.4`, dan pakej `mod_splaskscore_v1.2.4.zip`.
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
- Versi: **1.2.4**
- Tarikh: **11 Mei 2026**

## Lesen

Kod ini dilesenkan di bawah [GNU General Public License v3.0](LICENSE.txt).

Anda bebas menggunakan, mengubah suai, dan mengedarkan kod ini, dengan syarat:
- Menyertakan notis hak cipta asal.
- Menyertakan lesen GPL.
- Jika anda edarkan semula versi ubah suai, anda mesti membuka kod tersebut kepada umum di bawah lesen yang sama.

Lesen ini direka untuk memastikan kebebasan penggunaan dan pengubahsuaian dalam komuniti sumber terbuka.
