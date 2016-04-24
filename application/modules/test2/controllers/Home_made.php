<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Test2_Home_Made_module extends CI_Module {

	/**
	 * 构造函数
	 *
	 * @return void
	 * @author
	 **/
	function __construct()
	{
		parent::__construct();
	}

	function index()
	{
		// 这是装载本模块的模型，如果在本模块下找不到，则自动装载全局模型
		$this->load->model('Main_data_model');
		$this->Main_data_model->start();

		$this->load->view('view_test');
	}
}
