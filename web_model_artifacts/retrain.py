import pandas as pd
import numpy as np
import pickle
import os
import pymysql
import warnings
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

# Matikan peringatan/warning Pandas terkait penggunaan koneksi non-SQLAlchemy
warnings.filterwarnings('ignore', category=UserWarning)


# 1. KONFIGURASI DATABASE & KONEKSI

# Ganti kredensial ini sesuai dengan pengaturan MySQL/MAMP lokal Anda
DB_USER = 'root'
DB_PASS = 'root' 
DB_HOST = 'localhost'
DB_PORT = 8889 
DB_NAME = 'maufood'

print("⏳ Menghubungkan ke database MySQL dan mengambil data...")

# Menggunakan PyMySQL murni untuk menghindari bentrok versi SQLAlchemy vs Pandas
conn = pymysql.connect(
    host=DB_HOST,
    user=DB_USER,
    password=DB_PASS,
    database=DB_NAME,
    port=DB_PORT
)

try:
    
    # 2. AMBIL DAN PROSES DATA MENU (CONTENT-BASED FILTERING)
    
    query_menu = """
        SELECT 
            id AS ID_Menu, 
            nama_menu AS Nama_Menu, 
            kategori AS Kategori_Hidangan, 
            bahan_baku AS Bahan_Baku, 
            rasa AS Rasa_Dominan 
        FROM menu
    """
    df_menu = pd.read_sql(query_menu, conn)

    # Bersihkan nilai NaN menjadi string kosong
    df_menu.fillna('', inplace=True)

    # Membuat fitur gabungan
    def buat_fitur_gabungan(row):
        kat = str(row['Kategori_Hidangan']).replace(' ', '_')
        bah = str(row['Bahan_Baku']).replace(' ', '_')
        ras = str(row['Rasa_Dominan']).replace(' ', '_')
        return f"{kat} {bah} {ras}".strip()

    df_menu['fitur_gabungan'] = df_menu.apply(buat_fitur_gabungan, axis=1)

    # Ekstraksi Fitur TF-IDF
    tfidf = TfidfVectorizer(token_pattern=r'(?u)\b\w+\b')
    tfidf_matrix = tfidf.fit_transform(df_menu['fitur_gabungan'])

    # Hitung Cosine Similarity antar Menu
    cos_sim = cosine_similarity(tfidf_matrix)

    print(f"✅ Data CBF diproses: {df_menu.shape[0]} menu dimuat.")

    
    # 3. AMBIL DAN PROSES DATA TRANSAKSI (COLLABORATIVE FILTERING)
    
    query_transaksi = """
        SELECT 
            p.member_id, 
            dp.menu_id AS ID_Menu, 
            SUM(dp.jumlah) AS total_beli
        FROM pesanan p
        JOIN detail_pesanan dp ON p.id = dp.pesanan_id
        WHERE p.member_id IS NOT NULL 
          AND p.status = 'selesai'
        GROUP BY p.member_id, dp.menu_id
    """
    df_transaksi = pd.read_sql(query_transaksi, conn)

    if not df_transaksi.empty:
        # Format ID agar cocok dengan yang dikirim PHP: "U001", "U002", dll
        df_transaksi['User_ID'] = df_transaksi['member_id'].apply(lambda x: f"U{str(int(x)).zfill(3)}")
        
        def hitung_rating_implisit(qty):
            if qty == 1: return 3.0
            elif 2 <= qty <= 4: return 4.0
            else: return 5.0
            
        df_transaksi['Rating_Pelanggan'] = df_transaksi['total_beli'].apply(hitung_rating_implisit)
        
        # Buat User-Item Matrix
        user_item_matrix = df_transaksi.pivot_table(
            index='User_ID', columns='ID_Menu', values='Rating_Pelanggan'
        )
        
        # Hitung User-User Similarity (Adjusted Cosine)
        user_means = user_item_matrix.mean(axis=1)
        uim_centered = user_item_matrix.sub(user_means, axis=0).fillna(0)
        user_sim = cosine_similarity(uim_centered)
        user_sim_df = pd.DataFrame(user_sim, index=user_item_matrix.index, columns=user_item_matrix.index)
        
        print(f"✅ Data CF diproses: {user_item_matrix.shape[0]} member dimuat.")
    else:
        # Fallback jika belum ada transaksi selesai di tabel
        user_item_matrix = pd.DataFrame()
        user_means = pd.Series(dtype=float)
        user_sim_df = pd.DataFrame()
        print("⚠️ Belum ada transaksi dari member. CF matrix kosong.")

    
    # 4. SIMPAN ARTEFAK BARU
    
    BASE_DIR = os.path.dirname(os.path.abspath(__file__))
    ARTIFACTS_PATH = os.path.join(BASE_DIR, 'hybrid_artifacts.pkl')

    with open(ARTIFACTS_PATH, 'wb') as f:
        pickle.dump({
            'df_menu': df_menu,
            'tfidf_vectorizer': tfidf,
            'tfidf_matrix': tfidf_matrix,
            'cosine_sim_matrix': cos_sim,
            'user_item_matrix': user_item_matrix,
            'user_means': user_means,
            'user_similarity_matrix': user_sim_df,
            'alpha_default': 0.6,
            'beta_default': 0.4,
        }, f)

    print(f"🚀 SELESAI! Model berhasil diperbarui dan disimpan di {ARTIFACTS_PATH}.")

finally:
    # Selalu pastikan koneksi tertutup meskipun ada error
    conn.close()