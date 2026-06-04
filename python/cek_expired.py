#!/usr/bin/env python3
# ============================================
#  CloudLibrary Mini — Cron Job: Cek Expired
#  File   : python/cek_expired.py
#  FIXED  : Disesuaikan dengan struktur tabel
#           notifikasi (id, user_id, pesan,
#           tipe, is_read, created_at)
#
#  Jalankan otomatis via cron setiap hari:
#  0 0 * * * /usr/bin/python3 /path/to/python/cek_expired.py
# ============================================

import pymysql
import logging
from datetime import datetime

# ── Konfigurasi Database ──
DB_CONFIG = {
    "host"    : "localhost",
    "port"    : 3306,
    "user"    : "root",
    "password": "",                   # kosong untuk XAMPP default
    "db"      : "cloudlibrary_mini",  # nama database
    "charset" : "utf8mb4",
}

# ── Konfigurasi ──
HARI_PERINGATAN = 3   # Kirim notif jika sisa <= N hari
LOG_FILE        = "logs/cek_expired.log"

# ── Setup Logging ──
import os
os.makedirs("logs", exist_ok=True)

logging.basicConfig(
    level    = logging.INFO,
    format   = "%(asctime)s [%(levelname)s] %(message)s",
    handlers = [
        logging.FileHandler(LOG_FILE, encoding="utf-8"),
        logging.StreamHandler(),
    ]
)
log = logging.getLogger(__name__)


def get_connection():
    return pymysql.connect(
        host        = DB_CONFIG["host"],
        port        = DB_CONFIG["port"],
        user        = DB_CONFIG["user"],
        password    = DB_CONFIG["password"],
        db          = DB_CONFIG["db"],
        charset     = DB_CONFIG["charset"],
        cursorclass = pymysql.cursors.DictCursor,
    )


def update_status_expired(cursor) -> int:
    """Update peminjaman yang lewat tanggal expired → status 'expired'."""
    sql = """
        UPDATE peminjaman
        SET status = 'expired'
        WHERE status IN ('aktif', 'hampir_habis')
          AND tanggal_expired < CURDATE()
    """
    cursor.execute(sql)
    return cursor.rowcount


def update_status_hampir_habis(cursor) -> int:
    """Update peminjaman aktif yang sisa <= HARI_PERINGATAN → 'hampir_habis'."""
    sql = """
        UPDATE peminjaman
        SET status = 'hampir_habis'
        WHERE status = 'aktif'
          AND tanggal_expired >= CURDATE()
          AND DATEDIFF(tanggal_expired, CURDATE()) <= %s
    """
    cursor.execute(sql, (HARI_PERINGATAN,))
    return cursor.rowcount


def kirim_notifikasi_hampir_habis(cursor) -> int:
    """Kirim notif ke user yang pinjaman hampir habis (tanpa duplikat per hari)."""
    sql_select = """
        SELECT p.user_id, p.tanggal_expired, b.judul,
               DATEDIFF(p.tanggal_expired, CURDATE()) AS sisa_hari
        FROM peminjaman p
        JOIN buku b ON p.buku_id = b.id
        WHERE p.status = 'hampir_habis'
    """
    cursor.execute(sql_select)
    rows = cursor.fetchall()

    count = 0
    for r in rows:
        # Cek duplikat notif hari ini
        cursor.execute("""
            SELECT id FROM notifikasi
            WHERE user_id = %s
              AND tipe = 'hampir_habis'
              AND DATE(created_at) = CURDATE()
              AND pesan LIKE %s
        """, (r["user_id"], f"%{r['judul']}%"))
        if cursor.fetchone():
            continue

        pesan = (
            f"⚠️ Peminjaman buku \"{r['judul']}\" akan berakhir dalam "
            f"{r['sisa_hari']} hari (exp: {r['tanggal_expired']}). Segera perpanjang!"
        )
        cursor.execute("""
            INSERT INTO notifikasi (user_id, pesan, tipe, is_read)
            VALUES (%s, %s, 'hampir_habis', 0)
        """, (r["user_id"], pesan))
        count += 1

    return count


def kirim_notifikasi_expired(cursor) -> int:
    """Kirim notif ke user yang pinjaman baru expired."""
    cursor.execute("""
        SELECT p.user_id, p.tanggal_expired, b.judul
        FROM peminjaman p
        JOIN buku b ON p.buku_id = b.id
        WHERE p.status = 'expired'
          AND DATE(p.tanggal_expired) >= CURDATE() - INTERVAL 1 DAY
    """)
    rows = cursor.fetchall()

    count = 0
    for r in rows:
        # Cek duplikat
        cursor.execute("""
            SELECT id FROM notifikasi
            WHERE user_id = %s AND tipe = 'expired'
              AND pesan LIKE %s
        """, (r["user_id"], f"%{r['judul']}%"))
        if cursor.fetchone():
            continue

        pesan = (
            f"🔴 Masa pinjam buku \"{r['judul']}\" telah berakhir "
            f"pada {r['tanggal_expired']}. Harap segera mengembalikan."
        )
        cursor.execute("""
            INSERT INTO notifikasi (user_id, pesan, tipe, is_read)
            VALUES (%s, %s, 'expired', 0)
        """, (r["user_id"], pesan))
        count += 1

    return count


def proses_antrian(cursor) -> int:
    """Notif user terdepan antrian jika stok buku tersedia."""
    cursor.execute("""
        SELECT DISTINCT a.buku_id, b.judul, b.stok
        FROM antrian a
        JOIN buku b ON a.buku_id = b.id
        WHERE a.status = 'menunggu' AND b.stok > 0
    """)
    buku_list = cursor.fetchall()

    count = 0
    for buku in buku_list:
        cursor.execute("""
            SELECT * FROM antrian
            WHERE buku_id = %s AND status = 'menunggu'
            ORDER BY tanggal_daftar ASC LIMIT 1
        """, (buku["buku_id"],))
        antrian = cursor.fetchone()
        if not antrian:
            continue

        # Cek duplikat hari ini
        cursor.execute("""
            SELECT id FROM notifikasi
            WHERE user_id = %s AND tipe = 'antrian_tersedia'
              AND pesan LIKE %s AND DATE(created_at) = CURDATE()
        """, (antrian["user_id"], f"%{buku['judul']}%"))
        if cursor.fetchone():
            continue

        pesan = (
            f"✅ Buku \"{buku['judul']}\" kini tersedia! "
            f"Kamu adalah yang terdepan dalam antrian. Segera pinjam sebelum kehabisan!"
        )
        cursor.execute("""
            INSERT INTO notifikasi (user_id, pesan, tipe, is_read)
            VALUES (%s, %s, 'antrian_tersedia', 0)
        """, (antrian["user_id"], pesan))
        count += 1

    return count


def tambah_poin_harian(cursor) -> int:
    """Tambah 1 poin untuk user yang baca hari ini (jika kolom last_baca ada)."""
    cursor.execute("SHOW COLUMNS FROM peminjaman LIKE 'last_baca'")
    if not cursor.fetchone():
        return 0  # kolom tidak ada, skip

    cursor.execute("""
        UPDATE users u
        JOIN (
            SELECT DISTINCT user_id FROM peminjaman
            WHERE DATE(last_baca) = CURDATE()
              AND status IN ('aktif', 'hampir_habis')
        ) AS baca ON baca.user_id = u.id
        SET u.poin = u.poin + 1
    """)
    return cursor.rowcount


def bersihkan_notifikasi_lama(cursor, hari: int = 30) -> int:
    """Hapus notifikasi sudah dibaca yang lebih dari N hari."""
    cursor.execute("""
        DELETE FROM notifikasi
        WHERE is_read = 1
          AND created_at < DATE_SUB(NOW(), INTERVAL %s DAY)
    """, (hari,))
    return cursor.rowcount


def ringkasan_log(cursor) -> dict:
    """Ambil ringkasan data untuk log akhir."""
    cursor.execute("SELECT COUNT(*) AS n FROM peminjaman WHERE status = 'expired'")
    total_expired = cursor.fetchone()["n"]

    cursor.execute("SELECT COUNT(*) AS n FROM peminjaman WHERE status = 'hampir_habis'")
    total_hampir = cursor.fetchone()["n"]

    cursor.execute("SELECT COUNT(*) AS n FROM antrian WHERE status = 'menunggu'")
    total_antrian = cursor.fetchone()["n"]

    cursor.execute("SELECT COUNT(*) AS n FROM notifikasi WHERE is_read = 0")
    total_unread = cursor.fetchone()["n"]

    return {
        "total_expired"      : total_expired,
        "total_hampir_habis" : total_hampir,
        "total_antrian"      : total_antrian,
        "notif_belum_dibaca" : total_unread,
    }


def main():
    log.info("=" * 55)
    log.info(f"  CloudLibrary Mini — Cron Job Mulai: {datetime.now():%Y-%m-%d %H:%M:%S}")
    log.info("=" * 55)

    try:
        conn = get_connection()
        log.info("✅ Koneksi database berhasil")
    except Exception as e:
        log.error(f"❌ Gagal konek database: {e}")
        return

    try:
        with conn.cursor() as cur:

            n = update_status_expired(cur)
            log.info(f"🔴 Update expired          : {n} baris")

            n = update_status_hampir_habis(cur)
            log.info(f"🟡 Update hampir habis     : {n} baris")

            n = kirim_notifikasi_hampir_habis(cur)
            log.info(f"🔔 Notif hampir habis      : {n} dikirim")

            n = kirim_notifikasi_expired(cur)
            log.info(f"🔔 Notif expired           : {n} dikirim")

            n = proses_antrian(cur)
            log.info(f"📋 Antrian diproses        : {n} notif")

            n = tambah_poin_harian(cur)
            log.info(f"⭐ Poin harian ditambah    : {n} user")

            n = bersihkan_notifikasi_lama(cur, hari=30)
            log.info(f"🗑️  Notif lama dihapus      : {n} baris")

            conn.commit()
            log.info("✅ Semua perubahan di-commit")

            info = ringkasan_log(cur)
            log.info("-" * 55)
            log.info("📊 RINGKASAN AKHIR:")
            log.info(f"   Total expired       : {info['total_expired']}")
            log.info(f"   Hampir habis        : {info['total_hampir_habis']}")
            log.info(f"   Antrian aktif       : {info['total_antrian']}")
            log.info(f"   Notif belum dibaca  : {info['notif_belum_dibaca']}")

    except Exception as e:
        conn.rollback()
        log.error(f"❌ Error, rollback dilakukan: {e}", exc_info=True)

    finally:
        conn.close()
        log.info(f"  Selesai: {datetime.now():%Y-%m-%d %H:%M:%S}")
        log.info("=" * 55)


if __name__ == "__main__":
    main()