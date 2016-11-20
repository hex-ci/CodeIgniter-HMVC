<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Folder_Test_Home_Made_module extends CI_Module {

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
        $this->test1 = 'test1';
        $this->test2 = 'test2';

        // 这是装载本模块的模型，如果在本模块下找不到，则自动装载全局模型
        $this->load->model('Main_data_model');
        $this->Main_data_model->start();

        // 装载全局模型
        $this->load->model('Test_model');
        $this->Test_model->abc();

        $this->load->library('form_validation');
        $this->form_validation->run();

        $this->load->view('view_test');
    }
}
