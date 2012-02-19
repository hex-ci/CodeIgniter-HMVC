<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2006, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * 已扩展的 Router 类库
 *
 * 实现 Module 的 URL 可访问
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @author		Hex
 * @category	HMVC
 * @link		http://codeigniter.org.cn/forums/thread-1319-1-2.html
 */

class MY_Router extends CI_Router
{
	/**
	 * Constructor
	 *
	 * Runs the route mapping function.
	 */
	public function __construct()
	{
		parent::__construct();
	}

	// --------------------------------------------------------------------
	/**
	 * Validates the supplied segments.  Attempts to determine the path to
	 * the controller.
	 *
	 * @access	private
	 * @param	array
	 * @return	array
	 */
	function _validate_request($segments)
	{
		if (count($segments) == 0)
		{
			return $segments;
		}

		if ($segments[0] === 'module')
		{
			return $this->_validate_module_request($segments);
		}

		// Does the requested controller exist in the root folder?
		if (file_exists(APPPATH.'controllers/'.$segments[0].'.php'))
		{
			return $segments;
		}

		// Is the controller in a sub-folder?
		if (is_dir(APPPATH.'controllers/'.$segments[0]))
		{
			// Set the directory and remove it from the segment array
			//$this->set_directory($segments[0]);
			//$segments = array_slice($segments, 1);
            $tempDir = array();
            $i = 0;

            for(; $i < count($segments); $i++)
            {
                // We keep going until we can't find a directory
                $tempDir[] = $segments[$i];
                if(!is_dir(APPPATH.'/controllers/'.implode('/', $tempDir)))
                {
                    // The last "segment" is not a part of the "directory" so we can get rid of it.
                    unset($tempDir[count($tempDir)-1]);
                    break;
                }
            }

            $this->set_directory(implode('/', $tempDir));
            $segments = array_slice($segments, $i);

			if (count($segments) > 0)
			{
				// Does the requested controller exist in the sub-folder?
				if ( ! file_exists(APPPATH.'controllers/'.$this->fetch_directory().$segments[0].'.php'))
				{
					show_404($this->fetch_directory().$segments[0]);
				}
			}
			else
			{
				$this->set_class($this->default_controller);
				$this->set_method('index');

				// Does the default controller exist in the sub-folder?
				if ( ! file_exists(APPPATH.'controllers/'.$this->fetch_directory().$this->default_controller.'.php'))
				{
					$this->directory = '';
					return array();
				}

			}

			return $segments;
		}

		// Can't find the requested controller...
		show_404($segments[0]);
	}

	// --------------------------------------------------------------------

	/**
	 * Module 的访问直接路由到特殊的 Module_proxy 控制器
	 *
	 * @access	private
	 * @param	array
	 * @return	array
	 */
	function _validate_module_request($segments)
	{
		$segments = array_slice($segments, 1);

		$this->set_directory('../third_party');
		$this->set_class('module_proxy');
		$this->set_method('index');

		return $segments;
	}

	// --------------------------------------------------------------------

	/**
	 * Set the Route
	 *
	 * This function takes an array of URI segments as
	 * input, and sets the current class/method
	 *
	 * @access	private
	 * @param	array
	 * @param	bool
	 * @return	void
	 */
	function _set_request($segments = array())
	{
		// 如果是访问 Module，则转到 _validate_module_request 方法处理
		if (count($segments) > 0 && $segments[0] === 'module')
		{
			$segments = $this->_validate_module_request($segments);
			$this->uri->rsegments = $segments;

			return;
		}

		$segments = $this->_validate_request($segments);

		if (count($segments) == 0)
		{
			return $this->_set_default_controller();
		}

		$this->set_class($segments[0]);

		if (isset($segments[1]))
		{
			// A standard method request
			$this->set_method($segments[1]);
		}
		else
		{
			// This lets the "routed" segment array identify that the default
			// index method is being used.
			$segments[1] = 'index';
		}

		// Update our "routed" segment array to contain the segments.
		// Note: If there is no custom routing, this array will be
		// identical to $this->uri->segments
		$this->uri->rsegments = $segments;
	}

	// --------------------------------------------------------------------

	/**
	 *  Set the directory name
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function set_directory($dir)
	{
		$this->directory = $dir.'/';
	}

}

/* End of file MY_Router.php */
/* Location: ./application/core/MY_Router.php */
