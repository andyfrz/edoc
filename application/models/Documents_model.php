<?php
if(!defined('BASEPATH')) exit('No direct script access allowed');
class Documents_model extends MY_Model {
	public $tableName = "documents";
	public $pkey = "fin_document_id";

	public function __construct(){
		parent:: __construct();
	}

	public function getRules($mode="ADD",$id=0){

		$rules = [];

		$rules[] = [
			'field' => 'fst_name',
			'label' => 'Name',
			'rules' => 'required',
			'errors' => array(
				'required' => '%s tidak boleh kosong'
			)
		];

		$rules[] = [
			'field' => 'fst_source',
			'label' => 'Source',
			'rules' => 'required',
			'errors' => array(
				'required' => '%s tidak boleh kosong'
			)
		];

		$rules[] = [
			'field' => 'fst_print_scope',
			'label' => 'Print Scope',
			'rules' => 'required',
			'errors' => array(
				'required' => '%s tidak boleh kosong'
			)
		];

		$rules[] = [
			'field' => 'fbl_flow_control',
			'label' => 'Flow Control',
			'rules' => 'required',
			'errors' => array(
				'required' => '%s tidak boleh kosong'
			)
		];

		return $rules;
	}


	public function getDataById($id){
		$ssql = "select a.*,fst_username from ". $this->tableName . " a 
			inner join users b on a.fin_insert_id = b.fin_user_id 
			where " . $this->pkey ." = ?";

		$qr = $this->db->query($ssql,[$id]);
		if ($qr){
			return $qr->row();
		}
		return NULL;
	}

	public function editPermission($fin_document_id){
		//return false;
		$this->load->model("users_model");
		//only same user or other user with same department and have a higher level group get permit
		$ssql = "select b.fin_user_id,b.fin_department_id,c.fin_level from " . $this->tableName . " a 
			inner join users b on a.fin_insert_id = b.fin_user_id
			inner join master_groups c on b.fin_group_id = c.fin_group_id
			where a.fin_document_id = ?";
		$qr = $this->db->query($ssql,[$fin_document_id]);

		if($qr){
			$rwDoc = $qr->row();
			// echo $this->db->last_query();
			// print_r($rwDoc);
			// die();

			$activeUserId = $this->aauth->get_user_id();
			//echo $activeUserId;
			

			if ($rwDoc->fin_user_id == $activeUserId){
				return true;
			}
			$userActive = $this->users_model->getDataById($activeUserId)["user"];
			
			if ($rwDoc->fin_department_id == $userActive->fin_department_id){
				if ($userActive->fin_level < $rwDoc->fin_level ){
					return true;
				}else{
					return false;
				}
			}else{
				return false;
			}
		}else{
			return false;
		}
		

	}

	public function scopePermission($fin_document_id,$scopeMode = "VIEW"){
		$this->load->model("users_model");
		$activeUserId = $this->aauth->get_user_id();


		$fst_scope = ($scopeMode == "VIEW" ) ? "fst_view_scope" : "fst_print_scope";

		//Get Scope
		$ssql = "select a.fst_view_scope,a.fst_print_scope,a.fin_confidential_lvl,b.fin_user_id,b.fin_department_id,c.fin_level from " . $this->tableName . " a 
			inner join users b on a.fin_insert_id = b.fin_user_id
			inner join master_groups c on b.fin_group_id = c.fin_group_id
			where a.fin_document_id = ?";

		$qr = $this->db->query($ssql,[$fin_document_id]);
		//echo $this->db->last_query();
		if ($qr){
			$rwDoc = $qr->row();            
			if ($rwDoc->fin_user_id == $activeUserId){
				return true;
			}
			$userActive = $this->users_model->getDataById($activeUserId)["user"];


			//PRV, GBL, CST
			if($rwDoc->$fst_scope == "PRV"){ // only user same department can view document
				if ($rwDoc->fin_department_id  != $userActive->fin_department_id){
					return false;
				}
			}

			if ($rwDoc->$fst_scope == "CST"){
				$this->load->model("document_custom_permission_model");
				if ($this->document_custom_permission_model->isPermit("USER",$scopeMode,$fin_document_id,$userActive->fin_user_id)){
					return true;
				}

				if (! $this->document_custom_permission_model->isPermit("DEPARTMENT",$scopeMode,$fin_document_id,$userActive->fin_department_id)){
					return false;
				}               
			}
			// Cek Level
			if ($rwDoc->fin_confidential_lvl >= $userActive->fin_level  ){
				return true;
			}else{
				return false;
			}

			return false;
		}else{
			show_404();
		}
	}

	public function getFile($fin_document_id){
		$this->load->helper('download');
		$this->load->helper('file');

		$ssql = "select * from " .$this->tableName . " where fin_document_id = ? and fst_active = 'A'";
		$qr = $this->db->query($ssql,[$fin_document_id]);
		$rw = $qr->row();
		if ($rw){
			$uploadPath = getDbConfig("document_folder");
			$fileName	=  md5('doc_'. $rw->fin_document_id .'_' . $rw->fin_version) . ".pdf"; 

			$fileLoc = $uploadPath . $fileName;
			
			$string = read_file($fileLoc);
			//header("Content-type:application/pdf");
			//header("Content-Disposition:inline;filename=download.pdf");
			header("Content-Disposition:inline;filename=". $rw->fst_real_file_name);
			return $string;
		}
		return NULL;
	}
}