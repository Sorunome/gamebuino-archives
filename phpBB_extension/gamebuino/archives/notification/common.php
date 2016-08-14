<?php
namespace gamebuino\archives\notification;

abstract class notification_base extends \phpbb\notification\type\base {
	public function is_available(){
		return true;
	}
	public static function get_item_id($data){
		return (int)$data['query_id'];
	}
	public static function get_item_parent_id($data){
		return (int)$data['file_id'];
	}
	public function find_users_for_notification($data,$options = array()){
		$this->user_loader->load_users(array($data['user_id']));
		return $this->check_user_notification_options(array($data['user_id']),$options);
	}
	public function get_title(){
		return $this->user->lang($this->language_key,$this->get_data('file_name'));
	}
	public function get_url(){
		return '/archives/?file='.$this->get_data('file_id');
	}
	public function get_redirect_url(){
		return $this->get_url();
	}
	public function create_insert_array($data,$pre_data = array()){
		$this->set_data('file_id', $data['file_id']);
		$this->set_data('file_name', $data['file_name']);
		
		return parent::create_insert_array($data,$pre_data);
	}
	public function users_to_query(){
		return array();
	}
}
