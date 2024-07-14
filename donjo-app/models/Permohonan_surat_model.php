<?php class Permohonan_surat_model extends CI_Model {

	public function __construct()
	{
		parent::__construct();
		$this->load->model(['referensi_model', 'anjungan_model']);
	}

	public function insert($data)
	{
		$outp = $this->db
			->insert('permohonan_surat', array_merge(
				['no_antrian' => $this->generate_no_antrian()],
				$data
			));

		return $outp;
	}

	public function delete($id_permohonan)
	{
		$outp = $this->db->where('id', $id_permohonan)
			->delete('permohonan_surat');
		if (!$outp)
			$this->session->set_userdata('success', -1);
	}

	public function update($id_permohonan, $data)
	{
		$outp = $this->db
			->where('id', $id_permohonan)
			->update('permohonan_surat', $data);

		return $outp;
	}

	public function autocomplete()
	{
		$data = $this->db->select('n.nik')
			->from('permohonan_surat u')
			->join('tweb_penduduk n', 'u.id_pemohon = n.id', 'left')
			->get()->result_array();

		$outp = '';
		foreach ($data as $baris)
		{
			$outp .= ",'" .$baris['nik']. "'";
		}
		$outp = substr($outp, 1);
		$outp = '[' .$outp. ']';

		return $outp;
	}

	private function search_sql()
	{
		if ($cari = $this->session->cari)
		{
			$this->db
				->group_start()
					->like('n.nik', $cari)
					->or_like('n.nama', $cari)
					->or_like('u.no_antrian', str_replace('-', '', $cari))
				->group_end();
		}
	}

	private function filter_sql()
	{
		if ($filter = $this->session->filter)
		{
			$this->db->where('u.status', $filter);
		}
	}

	public function paging($p=1, $o=0)
	{
		$this->db->select('COUNT(u.id) as jml');
		$this->list_data_sql();
		$jml_data = $this->db->get()->row()->jml;

		$this->load->library('paging');
		$cfg['page'] = $p;
		$cfg['per_page'] = $this->session->per_page;
		$cfg['num_links'] = 10;
		$cfg['num_rows'] = $jml_data;
		$this->paging->init($cfg);

		return $this->paging;
	}

	private function list_data_sql()
	{
		$this->db->from('permohonan_surat u')
			->join('tweb_penduduk n', 'u.id_pemohon = n.id', 'left')
			->join('tweb_surat_format s', 'u.id_surat = s.id', 'left');

		$this->search_sql();
		$this->filter_sql();
	}

	public function list_data($o=0, $offset=0, $limit=500)
	{
		//Ordering SQL
		switch ($o)
		{
			case 1: $this->db->order_by('u.updated_at', 'asc'); break;
			case 2: $this->db->order_by('u.updated_at', 'desc'); break;
			default: $this->db->order_by('u.status, ISNULL(u.no_antrian), u.no_antrian', 'asc');
		}

		//Main Query
		$this->list_data_sql();
		$data = $this->db->select([
				'u.*',
				'u.status as status_id',
				'n.nama AS nama',
				'n.nik AS nik',
				's.nama as jenis_surat'
			])
			->limit($limit, $offset)
			->get()
			->result_array();

		//Formating Output
		$j = $offset;
		for ($i=0; $i<count($data); $i++)
		{
			$data[$i]['no'] = $j + 1;
			$data[$i]['status'] = $this->referensi_model->list_ref_flip(STATUS_PERMOHONAN)[$data[$i]['status']];
			$j++;
		}

		return $data;
	}

	public function list_permohonan_perorangan($id_pemohon)
	{
		$data = $this->db
			->select('u.*, u.status as status_id, n.nama AS nama, n.nik AS nik, s.nama as jenis_surat')
			->where('id_pemohon', $id_pemohon)
			->from('permohonan_surat u')
			->join('tweb_penduduk n', 'u.id_pemohon = n.id', 'left')
			->join('tweb_surat_format s', 'u.id_surat = s.id', 'left')
			->order_by('updated_at', 'DESC')
			->get()->result_array();
		for ($i=0; $i<count($data); $i++)
		{
			$data[$i]['no'] = $j + 1;
			$data[$i]['status'] = $this->referensi_model->list_ref_flip(STATUS_PERMOHONAN)[$data[$i]['status']];
			$j++;
		}
		return $data;
	}

	public function get_permohonan($where = [])
	{
		$data = $this->db
			->get_where('permohonan_surat', $where)
			->row_array();

		return $data;
	}

	public function list_data_status($id)
	{
		$this->db->select('id, status');
		$this->db->from('permohonan_surat');
		$this->db->where('id', $id);

		return $this->db->get()->row_array();
	}

	public function proses($id, $status, $id_pemohon = '')
	{
		if ($status == 0)
		{
			// Belum Lengkap
			$this->db->where('status', 1);
		}
		elseif ($status == 5)
		{
			// Batalkan hanya jika status = 0 (belum lengkap) atau 1 (sedang diproses)
			$this->db->where_in('status', ['0', '1']);
			
			if ($id_pemohon) $this->db->where('id_pemohon', $id_pemohon);
		}
		else
		{
			// Lainnya
			$this->db->where('status', ($status - 1));
		}

		$outp = $this->db
			->where('id', $id)
			->update('permohonan_surat', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);

		status_sukses($outp);
	}

	public function ambil_isi_form($isian_form)
	{
		$isian_form = json_decode($isian_form, true);
		$hapus = array('url_surat', 'url_remote', 'nik', 'id_surat', 'nomor', 'pilih_atas_nama', 'pamong', 'pamong_nip', 'jabatan', 'pamong_id');
		foreach ($hapus as $kolom)
		{
			unset($isian_form[$kolom]);
		}
		return $isian_form;
	}

	public function get_syarat_permohonan($id)
	{
		$permohonan = $this->db->where('id', $id)
			->get('permohonan_surat')
			->row_array();
		$syarat_permohonan = json_decode($permohonan['syarat'], true);
		$dok_syarat = array_values($syarat_permohonan);
		if ($dok_syarat) $this->db->where_in('id', $dok_syarat);
		$dokumen_kelengkapan = $this->db
			->select('id, nama')
			->get('dokumen')
			->result_array();

		$dok_syarat = array();
		foreach ($dokumen_kelengkapan as $dok)
		{
			$dok_syarat[$dok['id']] = $dok['nama'];
		}
		$syarat_surat = $this->surat_master_model->get_syarat_surat($permohonan['id_surat']);
		for ($i = 0; $i < count($syarat_surat); $i++)
		{
			$dok_id = $syarat_permohonan[$syarat_surat[$i]['ref_syarat_id']];
			$syarat_surat[$i]['dok_id'] = $dok_id;
			$syarat_surat[$i]['dok_nama'] = ($dok_id == '-1') ? 'Bawa bukti fisik ke Kantor Desa' : $dok_syarat[$dok_id];
		}

		return $syarat_surat;
	}

	protected function generate_no_antrian()
	{
		if (is_null($this->anjungan_model->cek_anjungan()))
		{
			return;
		}

		$nomor_terakhir = $this->db
			->select_max('no_antrian')
			->from('permohonan_surat')
			->where('CAST(created_at AS DATE) >= CURDATE()')
			->get()
			->row()
			->no_antrian;

		return is_null($nomor_terakhir)
			? date('dmy') . '001'
			: $nomor_terakhir + 1;
	}
}
