<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Folder_Test_Main_data_model extends CI_Model {

    function __construct()
    {
        parent::__construct();
        echo $this->test1;
    }

    function start()
    {
        echo '<p>Folder_Test_Main_data_model</p>';
        echo $this->test2;
    }

}
