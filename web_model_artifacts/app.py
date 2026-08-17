from flask import Flask, request, jsonify
import pandas as pd
import numpy as np
import pickle
from sklearn.metrics.pairwise import cosine_similarity
import os

app = Flask(__name__)

# 1. INISIALISASI & MEMUAT ARTEFAK MODEL
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ARTIFACTS_PATH = os.path.join(BASE_DIR, 'hybrid_artifacts.pkl')

try:
    with open(ARTIFACTS_PATH, 'rb') as f:
        artifacts = pickle.load(f)
        
    df_menu = artifacts['df_menu']
    tfidf = artifacts['tfidf_vectorizer']
    tfidf_matrix = artifacts['tfidf_matrix']
    cos_sim = artifacts['cosine_sim_matrix']
    user_item_matrix = artifacts['user_item_matrix']
    user_means = artifacts['user_means']
    user_sim_df = artifacts['user_similarity_matrix']
    alpha_default = artifacts['alpha_default']
    beta_default = artifacts['beta_default']
    
    id_to_idx = {mid: i for i, mid in enumerate(df_menu['ID_Menu'])}
    
    print("✅ Berhasil memuat artefak model hybrid_artifacts.pkl dari:", ARTIFACTS_PATH)
except Exception as e:
    print(f"❌ Gagal memuat artefak model: {e}")
    df_menu = pd.DataFrame()


# 2. FUNGSI FILTER & REKOMENDASI (DENGAN HARD FILTER)

def get_filtered_menu_ids(kategori='', bahan='', rasa=''):
    """
    Menyaring ID_Menu agar sesuai 100% dengan Dropdown yang dipilih pelayan.
    Jika tidak ada dropdown yang dipilih, kembalikan semua ID.
    """
    if df_menu.empty: return []
    
    mask = pd.Series([True] * len(df_menu), index=df_menu.index)
    if kategori:
        mask &= (df_menu['Kategori_Hidangan'] == kategori)
    if bahan:
        mask &= (df_menu['Bahan_Baku'] == bahan)
    if rasa:
        mask &= (df_menu['Rasa_Dominan'] == rasa)
        
    return df_menu[mask]['ID_Menu'].tolist()


def rekomendasi_populer(top_n=5, kategori='', bahan='', rasa=''):
    """Fallback ke rekomendasi populer, tapi WAJIB sesuai kategori yang dipilih."""
    valid_ids = get_filtered_menu_ids(kategori, bahan, rasa)
    if not valid_ids:
        return pd.DataFrame() # Kosong karena filter terlalu spesifik

    rata2 = user_item_matrix.mean(axis=0)
    # Filter rata2 agar hanya memproses menu yang lolos filter dropdown
    rata2 = rata2[rata2.index.isin(valid_ids)]
    rata2 = rata2.sort_values(ascending=False).head(top_n)
    
    df_hasil = df_menu[df_menu['ID_Menu'].isin(rata2.index)].copy()
    
    # PERBAIKAN: Normalisasi rating (1-5) menjadi rasio (0-1) agar persentase tidak tembus 100%
    df_hasil['skor'] = df_hasil['ID_Menu'].map(lambda x: round(rata2[x] / 5.0, 3))
    
    df_hasil['metode'] = 'Popular Fallback'
    return df_hasil.sort_values('skor', ascending=False)[
        ['ID_Menu', 'Nama_Menu', 'Kategori_Hidangan', 'Bahan_Baku', 'Rasa_Dominan', 'skor', 'metode']
    ].reset_index(drop=True)


def rekomendasi_dari_preferensi(kategori='', bahan='', rasa='', top_n=5):
    """Content-Based Filtering murni untuk Pelanggan Baru dengan Hard Filter."""
    valid_ids = get_filtered_menu_ids(kategori, bahan, rasa)
    if not valid_ids:
        return pd.DataFrame()

    bagian = [str(x).replace(' ', '_') for x in (kategori, bahan, rasa) if x]
    if not bagian:
        # Jika pelayan klik 'cari' tanpa memilih dropdown apa-apa
        return rekomendasi_populer(top_n, kategori, bahan, rasa)
    
    query_vec = tfidf.transform([' '.join(bagian)])
    skor = cosine_similarity(query_vec, tfidf_matrix).flatten()
    
    hasil = df_menu.copy()
    hasil['skor'] = skor.round(3)
    hasil['metode'] = 'Content-Based Filtering'
    
    # HARD FILTER: Buang menu yang tidak sesuai dropdown secara harfiah
    hasil = hasil[hasil['ID_Menu'].isin(valid_ids)]
    
    hasil = hasil.sort_values('skor', ascending=False).head(top_n)
    return hasil[['ID_Menu', 'Nama_Menu', 'Kategori_Hidangan', 'Bahan_Baku', 'Rasa_Dominan', 'skor', 'metode']].reset_index(drop=True)


def cbf_affinity(user_id, candidate_item_id):
    riwayat = user_item_matrix.loc[user_id].dropna()
    if len(riwayat) == 0: return 0.0
    cand_idx = id_to_idx.get(candidate_item_id)
    if cand_idx is None: return 0.0
    
    total_skor = sum(rating * cos_sim[cand_idx, id_to_idx[item_id]] for item_id, rating in riwayat.items() if item_id in id_to_idx)
    total_bobot = riwayat.sum()
    return total_skor / total_bobot if total_bobot > 0 else 0.0


def prediksi_cf(user_id, item_id, k=10):
    if item_id not in user_item_matrix.columns or user_id not in user_item_matrix.index:
        return None
    penilai = user_item_matrix[item_id].dropna().drop(index=user_id, errors='ignore')
    if len(penilai) == 0: return None
    sim = user_sim_df.loc[user_id, penilai.index]
    sim = sim[sim > 0]
    if len(sim) == 0: return None
    top_k = sim.sort_values(ascending=False).head(k)
    pembilang = sum(top_k[v] * (user_item_matrix.loc[v, item_id] - user_means[v]) for v in top_k.index)
    penyebut = sum(abs(s) for s in top_k)
    return None if penyebut == 0 else user_means[user_id] + pembilang / penyebut


def rekomendasi_weighted_hybrid(user_id, kategori='', bahan='', rasa='', top_n=5, alpha=None, beta=None, k=10):
    """Weighted Hybrid: Menggabungkan CF dan CBF HANYA untuk item yang sesuai filter dropdown."""
    if alpha is None: alpha = alpha_default
    if beta is None: beta = beta_default
    
    valid_ids = get_filtered_menu_ids(kategori, bahan, rasa)
    if not valid_ids:
        return pd.DataFrame()

    sudah_dipesan = user_item_matrix.loc[user_id].dropna().index
    
    # KANDIDAT: Menu yang BUKAN sudah dipesan DAN WAJIB lolos saringan dropdown
    kandidat = [i for i in user_item_matrix.columns if i not in sudah_dipesan and i in valid_ids]
    
    hasil = []
    for item_id in kandidat:
        skor_cbf = cbf_affinity(user_id, item_id)
        skor_cf_raw = prediksi_cf(user_id, item_id, k=k)
        
        if skor_cf_raw is not None:
            skor_cf_norm = max(0.0, min(1.0, skor_cf_raw / 5.0))
            skor_akhir = alpha * skor_cf_norm + beta * skor_cbf
            metode = "Weighted Hybrid (CF+CBF)"
        else:
            skor_akhir = skor_cbf
            metode = "CBF (CF Fallback)"
            
        hasil.append((item_id, skor_akhir, metode))
        
    hasil.sort(key=lambda x: x[1], reverse=True)
    hasil = hasil[:top_n]
    
    if not hasil:
        return pd.DataFrame()
        
    df_hasil = df_menu[df_menu['ID_Menu'].isin([i for i, _, _ in hasil])].copy()
    skor_map = {i: round(s, 3) for i, s, _ in hasil}
    metode_map = {i: m for i, _, m in hasil}
    
    df_hasil['skor'] = df_hasil['ID_Menu'].map(skor_map)
    df_hasil['metode'] = df_hasil['ID_Menu'].map(metode_map)
    
    return df_hasil.sort_values('skor', ascending=False)[
        ['ID_Menu', 'Nama_Menu', 'Kategori_Hidangan', 'Bahan_Baku', 'Rasa_Dominan', 'skor', 'metode']
    ].reset_index(drop=True)


# 3. ENDPOINT API (REST)

@app.route('/api/recommend', methods=['POST'])
def recommend():
    try:
        if df_menu.empty:
            return jsonify({"status": "error", "message": "Model artefak belum dimuat dengan benar."}), 500

        data = request.json
        if not data:
            return jsonify({"status": "error", "message": "Payload JSON kosong."}), 400

        user_id = data.get('user_id', '')
        is_pelanggan_baru = data.get('is_pelanggan_baru', True)
        kategori = data.get('kategori', '')
        bahan = data.get('bahan', '')
        rasa = data.get('rasa', '')
        
        df_rekomendasi = pd.DataFrame()

        # LOGIKA SWITCHING HYBRID
        if is_pelanggan_baru:
            # SKENARIO 1: Pelanggan Baru (Murni CBF) dengan Filter
            df_rekomendasi = rekomendasi_dari_preferensi(kategori, bahan, rasa)
            
            if df_rekomendasi.empty:
                df_rekomendasi = rekomendasi_populer(kategori=kategori, bahan=bahan, rasa=rasa)
        else:
            # SKENARIO 2: Pelanggan Lama (Weighted Hybrid CF + CBF) dengan Filter
            if user_id in user_item_matrix.index:
                df_rekomendasi = rekomendasi_weighted_hybrid(user_id, kategori, bahan, rasa)
                
                if df_rekomendasi.empty:
                    df_rekomendasi = rekomendasi_populer(kategori=kategori, bahan=bahan, rasa=rasa)
            else:
                # Cold Start ekstrem
                if kategori or bahan or rasa:
                    df_rekomendasi = rekomendasi_dari_preferensi(kategori, bahan, rasa)
                if df_rekomendasi.empty:
                    df_rekomendasi = rekomendasi_populer(kategori=kategori, bahan=bahan, rasa=rasa)

        df_rekomendasi = df_rekomendasi.fillna("")
        result_data = df_rekomendasi.to_dict(orient='records')

        return jsonify({
            "status": "success",
            "data": result_data
        }), 200

    except Exception as e:
        return jsonify({
            "status": "error",
            "message": f"Terjadi kesalahan internal: {str(e)}"
        }), 500


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8080, debug=True)