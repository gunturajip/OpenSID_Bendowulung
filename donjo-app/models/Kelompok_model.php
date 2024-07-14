<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * File ini:
 *
 * Model untuk modul Kelompok
 *
 * donjo-app/models/Kelompok_model.php
 *
 */

/**
 *
 * File ini bagian dari:
 *
 * OpenSID
 *
 * Sistem informasi desa sumber terbuka untuk memajukan desa
 *
 * Aplikasi dan source code ini dirilis berdasarkan lisensi GPL V3
 *
 * Hak Cipta 2009 - 2015 Combine Resource Institution (http://lumbungkomunitas.net/)
 * Hak Cipta 2016 - 2020 Perkumpulan Desa Digital Terbuka (https://opendesa.id)
 *
 * Dengan ini diberikan izin, secara gratis, kepada siapa pun yang mendapatkan salinan
 * dari perangkat lunak ini dan file dokumentasi terkait ("Aplikasi Ini"), untuk diperlakukan
 * tanpa batasan, termasuk hak untuk menggunakan, menyalin, mengubah dan/atau mendistribusikan,
 * asal tunduk pada syarat berikut:
 *
 * Pemberitahuan hak cipta di atas dan pemberitahuan izin ini harus disertakan dalam
 * setiap salinan atau bagian penting Aplikasi Ini. Barang siapa yang menghapus atau menghilangkan
 * pemberitahuan ini melanggar ketentuan lisensi Aplikasi Ini.
 *
 * PERANGKAT LUNAK INI DISEDIAKAN "SEBAGAIMANA ADANYA", TANPA JAMINAN APA PUN, BAIK TERSURAT MAUPUN
 * TERSIRAT. PENULIS ATAU PEMEGANG HAK CIPTA SAMA SEKALI TIDAK BERTANGGUNG JAWAB ATAS KLAIM, KERUSAKAN ATAU
 * KEWAJIBAN APAPUN ATAS PENGGUNAAN ATAU LAINNYA TERKAIT APLIKASI INI.
 *
 * @package OpenSID
 * @author Tim Pengembang OpenDesa
 * @copyright Hak Cipta 2009 - 2015 Combine Resource Institution (http://lumbungkomunitas.net/)
 * @copyright Hak Cipta 2016 - 2020 Perkumpulan Desa Digital Terbuka (https://opendesa.id)
 * @license http://www.gnu.org/licenses/gpl.html GPL V3
 * @link https://github.com/OpenSID/OpenSID
 */

class Kelompok_model extends MY_Model {

	protected $table = 'kelompok';
	protected $tipe = 'kelompok';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('wilayah_model');
	}

	public function set_tipe(string $tipe)
	{
		$this->tipe = $tipe;

		return $this;
	}

	public function autocomplete()
	{
		return $this->autocomplete_str('nama', $this->table);
	}

	private function search_sql()
	{
		if ($search = $this->session->cari)
		{
			$this->db
				->group_start()
					->like('u.nama', $search)
					->or_like('u.keterangan', $search)
					->or_like('c.nama', $search)
				->group_end();
		}

		return $this->db;
	}

	private function filter_sql()
	{
		if ($filter = $this->session->filter)
		{
			$this->db->where('u.id_master', $filter);
		}

		return $this->db;
	}

	public function paging($p = 1)
	{
		$jml_data = $this->list_data_sql()->count_all_results();

		return $this->paginasi($p, $jml_data);
	}

	private function list_data_sql()
	{
		$this->db->from("$this->table u")
			->join('kelompok_master s', 'u.id_master = s.id', 'left')
			->join('tweb_penduduk c', 'u.id_ketua = c.id', 'left')
			->where('u.tipe', $this->tipe);

		$this->search_sql();
		$this->filter_sql();

		return $this->db;
	}

	public function list_data($o = 0, $offset = 0, $limit = 0)
	{
		switch ($o)
		{
			case 1: $this->db->order_by('u.nama'); break;
			case 2: $this->db->order_by('u.nama', 'desc'); break;
			case 3: $this->db->order_by('c.nama'); break;
			case 4: $this->db->order_by('c.nama desc'); break;
			case 5: $this->db->order_by('master'); break;
			case 6: $this->db->order_by('master desc'); break;
			default: $this->db->order_by('u.nama'); break;
		}

		$this->list_data_sql();

		if ($limit > 0 ) $this->db->limit($limit, $offset);

		return $this->db
			->select('u.*, s.kelompok AS master, c.nama AS ketua, (SELECT COUNT(id) FROM kelompok_anggota WHERE id_kelompok = u.id) AS jml_anggota')
			->get()
			->result_array();
	}

	private function validasi($post)
	{
		if ($post['id_ketua'])
		{
			$data['id_ketua'] = bilangan($post['id_ketua']);
		}

		$data['id_master'] = bilangan($post['id_master']);
		$data['nama'] = nama_terbatas($post['nama']);
		$data['keterangan'] = htmlentities($post['keterangan']);
		$data['kode'] = nomor_surat_keputusan($post['kode']);
		$data['tipe'] = $this->tipe;

		return $data;
	}

	public function insert()
	{
		$data = $this->validasi($this->input->post());

		if ($this->get_kelompok(null, $data['kode']))
		{
			$this->session->success = -1;
			$this->session->error_msg = '<br/>Kode ' . $this->tipe . ' sudah digunakan';
			return false;
		}

		$outpa = $this->db->insert($this->table, $data);
		$insert_id = $this->db->insert_id();

		// TODO : Ubah cara penanganan penambahan ketua kelompok, simpan di bagian kelompok anggota
		$outpb = $this->db
			->set('id_kelompok', $insert_id)
			->set('id_penduduk', $data['id_ketua'])
			->set('no_anggota', 1)
			->set('jabatan', 1)
			->set('keterangan', "Ketua $this->tipe") // keterangan default untuk Ketua Kelompok
			->set('tipe', $this->tipe)
			->insert('kelompok_anggota');

		status_sukses($outpa && $outpb);
	}

	private function validasi_anggota($post)
	{
		if ($post['id_penduduk'])
		{
			$data['id_penduduk'] = bilangan($post['id_penduduk']);
		}

		$data['no_anggota'] = bilangan($post['no_anggota']);
		$data['jabatan'] = alfanumerik_spasi($post['jabatan']);
		$data['no_sk_jabatan'] = nomor_surat_keputusan($post['no_sk_jabatan']);
		$data['keterangan'] = htmlentities($post['keterangan']);
		$data['tipe'] = $this->tipe;
		
		if ($this->tipe == 'lembaga')
		{
			$data['nmr_sk_pengangkatan'] = nomor_surat_keputusan($post['nmr_sk_pengangkatan']);
			$data['tgl_sk_pengangkatan'] = ! empty($post['tgl_sk_pengangkatan']) ? tgl_indo_in($post['tgl_sk_pengangkatan']) : null;
			$data['nmr_sk_pemberhentian'] = nomor_surat_keputusan($post['nmr_sk_pemberhentian']);
			$data['tgl_sk_pemberhentian'] = ! empty($post['tgl_sk_pemberhentian']) ? tgl_indo_in($post['tgl_sk_pemberhentian']) : null;
			$data['periode'] = htmlentities($post['periode']);
		}

		return $data;
	}

	public function insert_a($id = 0)
	{
		$data = $this->validasi_anggota($this->input->post());
		$data['id_kelompok'] = $id;
		$this->ubah_jabatan($data['id_kelompok'], $data['id_penduduk'], $data['jabatan'], NULL);

		$outp = $this->db->insert('kelompok_anggota', $data);
		$id_pend = $data['id_penduduk'];
		$nik = $this->get_anggota($id, $id_pend);

		// Upload foto dilakukan setelah ada id, karena nama foto berisi nik
		if ($foto = upload_foto_penduduk($id_pend, $nik['nik'])) $this->db->where('id', $id_pend)->update('tweb_penduduk', ['foto' => $foto]);

		status_sukses($outp); //Tampilkan Pesan
	}

	public function update($id = 0)
	{
		$data = $this->validasi($this->input->post());

		if ($this->get_kelompok($id, $data['kode']))
		{
			$this->session->success = -1;
			$this->session->error_msg = '<br/>Kode ' . $this->tipe . ' sudah digunakan';
			return false;
		}

		$this->db->where('id', $id);
		$outp = $this->db->update($this->table, $data);

		status_sukses($outp); //Tampilkan Pesan
	}

	public function update_a($id = 0, $id_a = 0)
	{
		$data = $this->validasi_anggota($this->input->post());
		$this->ubah_jabatan($id, $id_a, $data['jabatan'], $this->input->post('jabatan_lama'));

		$outp = $this->db
			->where('id_penduduk', $id_a)
			->update('kelompok_anggota', $data);

		$nik = $this->get_anggota($id, $id_a);

		// Upload foto dilakukan setelah ada id, karena nama foto berisi nik
		if ($foto = upload_foto_penduduk($id_a, $nik['nik'])) $this->db->where('id', $id_a)->update('tweb_penduduk', ['foto' => $foto]);

		status_sukses($outp); //Tampilkan Pesan
	}

	public function delete($id = '', $semua = FALSE)
	{
		if ( ! $semua) $this->session->success = 1;

		$outp = $this->db->where('id', $id)->where('tipe', $this->tipe)->delete($this->table);

		status_sukses($outp, $gagal_saja = TRUE); //Tampilkan Pesan
	}

	public function delete_all()
	{
		$this->session->success = 1;

		$id_cb = $_POST['id_cb'];
		foreach ($id_cb as $id)
		{
			$this->delete($id, $semua=TRUE);
		}
	}

	public function delete_anggota($id = '', $semua = FALSE)
	{
		if ( ! $semua) $this->session->success = 1;

		$outp = $this->db->where('id', $id)->where('tipe', $this->tipe)->delete('kelompok_anggota');

		status_sukses($outp, $gagal_saja=TRUE); //Tampilkan Pesan
	}

	public function delete_anggota_all()
	{
		$this->session->success = 1;

		$id_cb = $_POST['id_cb'];
		foreach ($id_cb as $id)
		{
			$this->delete_anggota($id, $semua=TRUE);
		}
	}

	public function get_kelompok($id = NULL, $kode = NULL)
	{
		if ($id && $kode) $this->db->where('k.id !=', $id);

		$data = $this->db
			->select('k.*, km.kelompok AS kategori, tp.nama AS nama_ketua')
			->from('kelompok k')
			->join('kelompok_master km', 'k.id_master = km.id', 'left')
			->join('tweb_penduduk tp', 'k.id_ketua = tp.id', 'left')
			->group_start()
				->where('k.id', $id)
				->or_where('k.kode', $kode)
			->group_end()
			->get()
			->row_array();

		return $data;
	}

	public function get_ketua_kelompok($id)
	{
		$this->load->model('penduduk_model');
		$sql = "SELECT u.id, u.nik, u.nama, k.id as id_kelompok, k.nama as nama_kelompok, u.tempatlahir, u.tanggallahir, s.nama as sex,
				DATE_FORMAT(FROM_DAYS(TO_DAYS(NOW())-TO_DAYS(`tanggallahir`)), '%Y')+0 AS umur,
				d.nama as pendidikan, f.nama as warganegara, a.nama as agama,
				wil.rt, wil.rw, wil.dusun
			FROM kelompok k
			LEFT JOIN tweb_penduduk u ON u.id = k.id_ketua
			LEFT JOIN tweb_penduduk_pendidikan_kk d ON u.pendidikan_kk_id = d.id
			LEFT JOIN tweb_penduduk_warganegara f ON u.warganegara_id = f.id
			LEFT JOIN tweb_penduduk_agama a ON u.agama_id = a.id
			LEFT JOIN tweb_penduduk_sex s ON s.id = u.sex
			LEFT JOIN tweb_wil_clusterdesa wil ON wil.id = u.id_cluster
			WHERE k.id = $id LIMIT 1";
		$query = $this->db->query($sql);
		$data = $query->row_array();
		$data['alamat_wilayah'] = $this->penduduk_model->get_alamat_wilayah($data['id']);

		return $data;
	}

	public function get_anggota($id = 0, $id_a = 0)
	{
		$data = $this->db
			->select('ka.*, tp.sex as id_sex, tp.foto, tp.nik')
			->from('kelompok_anggota ka')
			->join('tweb_penduduk tp', 'ka.id_penduduk = tp.id')
			->where('id_kelompok', $id)
			->where('id_penduduk', $id_a)
			->get()
			->row_array();

		return $data;
	}

	public function list_master()
	{
		return $this->db
			->where('tipe', $this->tipe)
			->get('kelompok_master')
			->result_array();
	}

	private function in_list_anggota($kelompok)
	{
		$anggota = $this->db
			->select('p.id')
			->from('kelompok_anggota k')
			->join('penduduk_hidup p', 'k.id_penduduk = p.id', 'left')
			->where('k.id_kelompok', $kelompok)
			->where('k.tipe', $this->tipe)
			->get()
			->result_array();

		return sql_in_list(array_column($anggota, 'id'));
	}

	public function list_penduduk($ex_kelompok = '')
	{
		if ($ex_kelompok)
		{
			$anggota = $this->in_list_anggota($ex_kelompok);
			if ($anggota) $this->db->where("p.id not in ($anggota)");
		}
		$sebutan_dusun = ucwords($this->setting->sebutan_dusun);
		$this->db
			->select('p.id, nik, nama')
			->select("(
				case when (p.id_kk IS NULL or p.id_kk = 0)
					then
						case when (cp.dusun = '-' or cp.dusun = '')
							then CONCAT(COALESCE(p.alamat_sekarang, ''), ' RT ', cp.rt, ' / RW ', cp.rw)
							else CONCAT(COALESCE(p.alamat_sekarang, ''), ' {$sebutan_dusun} ', cp.dusun, ' RT ', cp.rt, ' / RW ', cp.rw)
						end
					else
						case when (ck.dusun = '-' or ck.dusun = '')
							then CONCAT(COALESCE(k.alamat, ''), ' RT ', ck.rt, ' / RW ', ck.rw)
							else CONCAT(COALESCE(k.alamat, ''), ' {$sebutan_dusun} ', ck.dusun, ' RT ', ck.rt, ' / RW ', ck.rw)
						end
				end) AS alamat")
			->from('penduduk_hidup p')
			->join('tweb_wil_clusterdesa cp', 'p.id_cluster = cp.id', 'left')
			->join('tweb_keluarga k', 'p.id_kk = k.id', 'left')
			->join('tweb_wil_clusterdesa ck', 'k.id_cluster = ck.id', 'left');
		$data = $this->db->get()->result_array();
		
		return $data;
	}

	public function list_pengurus($id_kelompok)
	{
		$this->db->where('jabatan <>', 90);
		$data = $this->list_anggota($id_kelompok);

		return $data;
	}

	public function list_anggota($id_kelompok = 0, $sub = '')
	{
		$dusun = ucwords($this->setting->sebutan_dusun);
		if ($sub == 'anggota')
		{
			$this->db->where('jabatan', 90); // Hanya anggota saja, tidak termasuk pengurus
		}

		$data = $this->db
			->select('ka.*, tp.nik, tp.nama, tp.tempatlahir, tp.tanggallahir, tp.sex AS id_sex, tpx.nama AS sex, tp.foto, tpp.nama as pendidikan, tpa.nama as agama')
			->select("(SELECT DATE_FORMAT(FROM_DAYS(TO_DAYS(NOW())-TO_DAYS(tanggallahir)), '%Y')+0 FROM tweb_penduduk WHERE id = tp.id) AS umur")
			->select('a.dusun,a.rw,a.rt')
			->select("CONCAT('{$dusun} ', a.dusun, ' RW ', a.rw, ' RT ', a.rt) AS alamat")
			->from('kelompok_anggota ka')
			->join('tweb_penduduk tp', 'ka.id_penduduk = tp.id', 'left')
			->join('tweb_penduduk_sex tpx', 'tp.sex = tpx.id', 'left')
			->join('tweb_penduduk_pendidikan tpp', 'tp.pendidikan_sedang_id = tpp.id', 'left')
			->join('tweb_penduduk_agama tpa', 'tp.agama_id = tpa.id', 'left')
			->join('tweb_wil_clusterdesa a', 'tp.id_cluster = a.id', 'left')
			->where('ka.id_kelompok', $id_kelompok)
			->where('ka.tipe', $this->tipe)
			->order_by("CAST(jabatan AS UNSIGNED) + 30 - jabatan, CAST(no_anggota AS UNSIGNED)")
			->get()
			->result_array();

			foreach ($data as $key => $anggota)
			{
				if ($anggota['jabatan'] <> 90)
				{
					$data[$key]['jabatan'] = $this->referensi_model->list_ref(JABATAN_KELOMPOK)[$anggota['jabatan']] ?: strtoupper($anggota['jabatan']);
				}
				else
				{
					$data[$key]['jabatan'] = $this->referensi_model->list_ref(JABATAN_KELOMPOK)[$anggota['jabatan']];
				}
			}

		return $data;
	}

	public function ubah_jabatan($id_kelompok, $id_penduduk, $jabatan, $jabatan_lama)
	{
		// jika ada orang lain yang sudah jabat KETUA ubah jabatan menjadi anggota
		// update id_ketua kelompok di tabel kelompok
		if ($jabatan == '1') // Ketua
		{
			$this->db
				->set('jabatan', '90') // Anggota
				->set('no_sk_jabatan', '')
				->where('id_kelompok', $id_kelompok)
				->where('jabatan', '1')
				->update('kelompok_anggota');

			$this->db
				->set('id_ketua', $id_penduduk)
				->where('id', $id_kelompok)
				->update($this->table);
		}
		elseif ($jabatan_lama == '1') // Ketua
		{
			// jika yang diubah adalah jabatan KETUA maka kosongkan id_ketua kelompok di tabel kelompok
			$this->db
				->set('id_ketua', -9999) // kolom id_ketua di tabel kelompok tidak bisa NULL
				->where('id', $id_kelompok)
				->update($this->table);
		}
	}

	public function list_jabatan($id_kelompok = 0)
	{
		$data = $this->db
			->distinct()
			->select('UPPER(jabatan) as jabatan ')
			->where("jabatan REGEXP '[a-zA-Z]+'")
			->where('id_kelompok', $id_kelompok)
			->where('tipe', $this->tipe)
			->order_by("jabatan")
			->get('kelompok_anggota')
			->result_array();

		return $data;
	}
}
