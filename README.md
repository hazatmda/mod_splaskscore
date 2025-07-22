# mod_splaskscore

Modul Joomla untuk memaparkan markah penilaian dan tarikh kemaskini terakhir dari sistem SPLaSK (Sistem Pemantauan Laman Web dan Perkhidmatan Dalam Talian).

## Fungsi Utama

- Paparan markah penilaian SPLaSK melalui API rasmi.
- Tarikh kemaskini terakhir dan semakan seterusnya.
- Tiada tracking pelawat – hanya integrasi API.
- Menyokong semakan kemaskini automatik melalui GitHub (update server).

## Cara Pasang

1. Muat turun `mod_splaskscore_v1.1.5.zip` dari tab [Releases](https://github.com/hazatmda/mod_splaskscore/releases).
2. Pasang di Joomla: **Extensions > Manage > Install**.
3. Masukkan token SPLaSK anda dalam konfigurasi modul.

## Kemaskini Automatik

Modul ini menyokong Joomla Update Server.

Fail `mod_splaskscore_update.xml` menyediakan maklumat kemaskini dan disemak secara automatik oleh Joomla.

## Changelog

**v1.1.5 (22 Julai 2025)**
- Penambahbaikan logik penggredan:  
  - Gred A (95-100), B (91-94), C (86-90), GAGAL (85 ke bawah)
- Fail manifest & update server dikemaskini.
- Versi & tarikh diseragamkan.

**v1.1.4**
- Versi asal (rujuk release sebelum ini)

## Maklumat Tambahan

- Dibangunkan oleh: **Muhammad Azizan Hazim**
- Versi: **1.1.5**
- Tarikh: **22 Julai 2025**

## Lesen

Kod ini dilesenkan di bawah [GNU General Public License v3.0](LICENSE.txt).

Anda bebas menggunakan, mengubah suai, dan mengedarkan kod ini, dengan syarat:
- Menyertakan notis hak cipta asal.
- Menyertakan lesen GPL.
- Jika anda edarkan semula versi ubah suai, anda mesti membuka kod tersebut kepada umum di bawah lesen yang sama.

Lesen ini direka untuk memastikan kebebasan penggunaan dan pengubahsuaian dalam komuniti sumber terbuka.
