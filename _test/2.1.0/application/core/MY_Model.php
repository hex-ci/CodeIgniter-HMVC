<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package     CodeIgniter
 * @author      ExpressionEngine Dev Team
 * @copyright   Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license     http://codeigniter.com/user_guide/license.html
 * @link        http://codeigniter.com
 * @since       Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * 已扩展的 Model 类库
 *
 * 此类库相对于原始 Model 类库，主要是增加了对 HMVC 的支持
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @author      Hex
 * @category    HMVC
 * @link        http://codeigniter.org.cn/forums/thread-1319-1-2.html
 */
class MY_Model extends CI_Model {

    /**
     * Constructor
     *
     * @access public
     */
    function __construct()
    {
        log_message('debug', "MY_Model Class Initialized");
    }

    /**
     * __get
     *
     * Allows models to access CI's loaded classes using the same
     * syntax as controllers.
     *
     * @param   string
     * @access private
     */
    function __get($key)
    {
        $CI =& get_instance();
        return $CI->$key;
    }
}
// END Model Class

/* End of file Model.php */
/* Location: ./system/core/Model.php */