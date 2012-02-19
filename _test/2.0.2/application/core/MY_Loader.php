<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 5.1.6 or newer
 *
 * @package		CodeIgniter
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2008 - 2011, EllisLab, Inc.
 * @license		http://codeigniter.com/user_guide/license.html
 * @link		http://codeigniter.com
 * @since		Version 1.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * 已扩展的 Loader 类库
 *
 * 此类库相对于原始 Loader 类库，主要是增加了对 HMVC 的支持
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @author		Hex
 * @category	HMVC
 * @link		http://codeigniter.org.cn/forums/thread-1319-1-2.html
 */
class MY_Loader extends CI_Loader {

	var $_ci_is_inside_module = false;	// 当前是否是 Module 里的 Loader
	var $_ci_module_path = '';  // 当前 Module 所在路径
	var $_ci_module_class = '';  // 当前 Module 的控制器名
	var $_ci_autoload_libraries = array();	// 自动装载的类库名数组
	var $_ci_autoload_models = array();		// 自动装载的模型名数组
	var $_ci_module_uri = '';	// 当前 Module 的调用 URI
	var $_ci_module_method = '';	// 当前 Module 执行的方法

	/**
	 * Module Loader
	 *
	 * This function lets users load and instantiate module.
	 *
	 * @access	public
	 * @param	string	the module uri of the module
	 * @return	void
	 */
	function module($module_uri, $vars = array(), $return = FALSE)
	{
		if ($module_uri == '')
		{
			return;
		}

		$module_uri = trim($module_uri, '/');

		$CI =& get_instance();

		$default_controller = $CI->router->default_controller;

		if (strpos($module_uri, '/') === FALSE)
		{
			$path = '';
			// 只有模块名，使用默认控制器和默认方法
			$module = $module_uri;
			$controller = $default_controller;
			$method = 'index';
			$segments = array();
		}
		else
		{
			$segments = explode('/', $module_uri);

			if (file_exists(APPPATH.'modules/'.$segments[0].'/controllers/'.$segments[1].EXT))
			{
				$path = '';
				$module = $segments[0];
				$controller = $segments[1];
				$method = isset($segments[2]) ? $segments[2] : 'index';
			}
			// 子目录下有模块？
			elseif (is_dir(APPPATH.'modules/'.$segments[0].'/'.$segments[1].'/controllers'))
			{
				// Set the directory and remove it from the segment array
				$path = $segments[0];
				$segments = array_slice($segments, 1);

				if (count($segments) > 0)
				{
					// 子目录下有模块？
					if (is_dir(APPPATH.'modules/'.$path.'/'.$segments[0].'/controllers'))
					{
						$module = $segments[0];
						$controller = isset($segments[1]) ? $segments[1] : $default_controller;
						$method = isset($segments[2]) ? $segments[2] : 'index';
					}
				}
				else
				{
					show_error('Unable to locate the module you have specified: '.$path);
				}
			}
			else
			{
				show_error('Unable to locate the module you have specified: '.$module_uri);
			}

			if ($path != '')
			{
				$path = rtrim($path, '/') . '/';
			}
		}

		// 模块名全部小写
		$module = strtolower($module);

		// 必须是类似这样的模块类名：目录_模块名_控制器名_module (如：Account_Message_Home_module)
		$c = str_replace(' ', '_', ucwords(str_replace('_', ' ', $controller)));
		$class_name = str_replace(' ', '_', ucwords(str_replace('/', ' ', $path.$module.' '.$c))) . '_module';

		// Module 的控制器文件的路径
		$controller_path = APPPATH.'modules/'.$path.$module.'/controllers/'.$controller.EXT;

		if ( ! file_exists($controller_path))
		{
			show_error('Unable to locate the module you have specified: '.$path.$module.'/controllers/'.$controller.EXT);
		}

		if ( ! class_exists('CI_Module'))
		{
			require_once(APPPATH.'core/Module'.EXT);
		}

		if (!isset($CI->$class_name))
		{
			// 装载 Module 控制器文件
			require_once($controller_path);

			// 实例化 Module 控制器
			$CI->$class_name = new $class_name();

			// 注意：要操作模块里的 loader 类实例
			$CI->$class_name->load->_ci_module_path = $path.$module;
			$CI->$class_name->load->_ci_module_class = $class_name;

			$CI->$class_name->_ci_module_uri = $path.$module.'/'.$controller;
			$CI->$class_name->_ci_module_method = $method;
		}

		$module_load =& $CI->$class_name->load;

		if (strncmp($method, '_', 1) != 0 && in_array(strtolower($method), array_map('strtolower', get_class_methods($class_name))))
		{
			ob_start();

			log_message('debug', 'Module call: '.$class_name.'->'.$method);

			// Call the requested method.
			// Any URI segments present (besides the class/function) will be passed to the method for convenience
			$output = call_user_func_array(array($CI->$class_name, $method), $module_load->_ci_object_to_array($vars));

			if ($return === TRUE)
			{
				$buffer = ob_get_contents();
				@ob_end_clean();

				$result = ($output) ? $output : $buffer;

				return $result;
			}
			else
			{
				if (ob_get_level() > $this->_ci_ob_level + 1)
				{
					ob_end_flush();
				}
				else
				{
					$buffer = ob_get_contents();
					$result = ($output) ? $output : $buffer;
					$CI->output->append_output($result);
					@ob_end_clean();
				}
			}
		}
		else
		{
			show_error('Unable to locate the '.$method.' method you have specified: '.$class_name);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Model Loader
	 *
	 * This function lets users load and instantiate models.
	 *
	 * @access	public
	 * @param	string	the name of the class
	 * @param	string	name for the model
	 * @param	bool	database connection
	 * @return	void
	 */
	function model($model, $name = '', $db_conn = FALSE)
	{
		if (is_array($model))
		{
			foreach ($model as $babe)
			{
				$this->model($babe);
			}
			return;
		}

		if ($model == '')
		{
			return;
		}

		$path = '';

		// Is the model in a sub-folder? If so, parse out the filename and path.
		if (($last_slash = strrpos($model, '/')) !== FALSE)
		{
			// The path is in front of the last slash
			$path = substr($model, 0, $last_slash + 1);

			// And the model name behind it
			$model = substr($model, $last_slash + 1);
		}

		if ($name == '')
		{
			$name = $model;
		}

		if (in_array($name, $this->_ci_models, TRUE))
		{
			return;
		}

		$CI =& get_instance();

		$model_paths = $this->_ci_model_paths;

		if ($this->_ci_is_inside_module)
		{
			$module_class_name = $this->_ci_module_class;
			array_unshift($model_paths, APPPATH.'modules/'.$this->_ci_module_path.'/');
			$module_model_name = str_replace(' ', '_', ucwords(str_replace('/', ' ', $this->_ci_module_path.' '.$model)));
			if (isset($CI->$module_class_name->$name))
			{
				show_error('The model name you are loading is the name of a resource that is already being used: '.$module_class_name.'.'.$module_model_name);
			}
		}
		else
		{
			if (isset($CI->$name))
			{
				show_error('The model name you are loading is the name of a resource that is already being used: '.$name);
			}
		}

		$model = strtolower($model);

		foreach ($model_paths as $key=>$mod_path)
		{
			if ( ! file_exists($mod_path.'models/'.$path.$model.EXT))
			{
				continue;
			}

			if ($db_conn !== FALSE AND ! class_exists('CI_DB'))
			{
				if ($db_conn === TRUE)
				{
					$db_conn = '';
				}

				$CI->load->database($db_conn, FALSE, TRUE);
			}

			if ( ! class_exists('CI_Model'))
			{
				load_class('Model', 'core');
			}

			require_once($mod_path.'models/'.$path.$model.EXT);

			$model = ucfirst($model);

			if ($this->_ci_is_inside_module)
			{
				if ($key == 0)
				{
					$CI->$module_class_name->$name = new $module_model_name();
				}
				else
				{
					$CI->$module_class_name->$name = new $model();
				}
			}
			else
			{
				$CI->$name = new $model();
			}

			$this->_ci_models[] = $name;
			return;
		}

		// couldn't find the model
		show_error('Unable to locate the model you have specified: '.$model);
	}

	// --------------------------------------------------------------------

	/**
	 * Load View
	 *
	 * This function is used to load a "view" file.  It has three parameters:
	 *
	 * 1. The name of the "view" file to be included.
	 * 2. An associative array of data to be extracted for use in the view.
	 * 3. TRUE/FALSE - whether to return the data or load it.  In
	 * some cases it's advantageous to be able to return data so that
	 * a developer can process it in some way.
	 *
	 * @access	public
	 * @param	string
	 * @param	array
	 * @param	bool
	 * @return	void
	 */
	function view($view, $vars = array(), $return = FALSE)
	{
		if ($this->_ci_is_inside_module)
		{
			$ext = pathinfo($view, PATHINFO_EXTENSION);
			$view = ($ext == '') ? $view.EXT : $view;
			$path = APPPATH.'modules/'.$this->_ci_module_path.'/views/'.$view;

			if (file_exists($path))
			{
				return $this->_ci_load(array('_ci_view' => $view, '_ci_vars' => $this->_ci_object_to_array($vars), '_ci_path' => $path, '_ci_return' => $return));
			}
			else
			{
				return $this->_ci_load(array('_ci_view' => $view, '_ci_vars' => $this->_ci_object_to_array($vars), '_ci_return' => $return));
			}
		}
		else
		{
			return $this->_ci_load(array('_ci_view' => $view, '_ci_vars' => $this->_ci_object_to_array($vars), '_ci_return' => $return));
		}
	}

	// --------------------------------------------------------------------

	/**
	 * 取当前 Module 某方法的 URL 地址
	 *
	 * @access	public
	 * @param	string	方法名/参数1/.../参数n
	 * @param	string	URL 中要替换的控制器名，为空使用当前控制器名
	 * @return	string
	 */
	function module_url($uri, $controller_name = '')
	{
		$CI =& get_instance();
		$class = $this->_ci_module_class;

		$module_uri = trim($CI->$class->_ci_module_uri, '/');

		if (!empty($controller_name))
		{
			$arr = explode('/', $module_uri);
			$arr[count($arr) - 1] = str_replace(array('/', '.'), '', $controller_name);
			$module_uri = implode('/', $arr);
		}

		return $this->config->site_url('module/' . $module_uri . '/' . trim($uri, '/'));
	}

	// --------------------------------------------------------------------

	/**
	 * Database Loader
	 *
	 * @access	public
	 * @param	string	the DB credentials
	 * @param	bool	whether to return the DB object
	 * @param	bool	whether to enable active record (this allows us to override the config setting)
	 * @return	object
	 */
	function database($params = '', $return = FALSE, $active_record = NULL)
	{
		// Grab the super object
		$CI =& get_instance();

		// Do we even need to load the database class?
		if (class_exists('CI_DB') AND $return == FALSE AND $active_record == NULL AND isset($CI->db) AND is_object($CI->db))
		{
			if ($this->_ci_is_inside_module and isset($CI->db))
			{
				$module_class_name = $this->_ci_module_class;
				$CI->$module_class_name->db =& $CI->db;
			}

			return FALSE;
		}

		require_once(BASEPATH.'database/DB'.EXT);

		if ($return === TRUE)
		{
			return DB($params, $active_record);
		}

		// Initialize the db variable.  Needed to prevent
		// reference errors with some configurations
		$CI->db = '';

		// Load the DB class
		$CI->db =& DB($params, $active_record);

		if ($this->_ci_is_inside_module)
		{
			$module_class_name = $this->_ci_module_class;
			$CI->$module_class_name->db =& $CI->db;
		}
	}

	// --------------------------------------------------------------------

	// Module 中的 Loader 类实例初始化时，自动调用此函数
	public function _ci_module_ready($class_path, $class_name)
	{
		$this->_ci_is_inside_module = true;
		$this->_ci_module_path = $class_path;
		$this->_ci_module_class = $class_name;
		$this->_ci_models = array();
	}

	// 根据是否在模块中来取超级对象
	private function &get_instance()
	{
		$CI =& get_instance();

		if (!empty($this->_ci_module_path))
		{
			$module_class_name = $this->_ci_module_class;
			return $CI->$module_class_name;
		}
		else
		{
			return $CI;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Class Loader
	 *
	 * This function lets users load and instantiate classes.
	 * It is designed to be called from a user's app controllers.
	 *
	 * @access	public
	 * @param	string	the name of the class
	 * @param	mixed	the optional parameters
	 * @param	string	an optional object name
	 * @return	void
	 */
	function library($library = '', $params = NULL, $object_name = NULL)
	{
		if (is_array($library))
		{
			foreach ($library as $class)
			{
				$this->library($class, $params);
			}

			return;
		}

		if ($library == '' OR isset($this->_base_classes[$library]))
		{
			return FALSE;
		}

		if ( ! is_null($params) && ! is_array($params))
		{
			$params = NULL;
		}

		$this->_ci_load_class($library, $params, $object_name);
	}

	// --------------------------------------------------------------------

	/**
	 * Load the Utilities Class
	 *
	 * @access	public
	 * @return	string
	 */
	function dbutil()
	{
		if ( ! class_exists('CI_DB'))
		{
			$this->database();
		}

		$CI = $this->get_instance();

		// for backwards compatibility, load dbforge so we can extend dbutils off it
		// this use is deprecated and strongly discouraged
		$CI->load->dbforge();

		require_once(BASEPATH.'database/DB_utility'.EXT);
		require_once(BASEPATH.'database/drivers/'.$CI->db->dbdriver.'/'.$CI->db->dbdriver.'_utility'.EXT);
		$class = 'CI_DB_'.$CI->db->dbdriver.'_utility';

		$CI->dbutil = new $class();
	}

	// --------------------------------------------------------------------

	/**
	 * Load the Database Forge Class
	 *
	 * @access	public
	 * @return	string
	 */
	function dbforge()
	{
		if ( ! class_exists('CI_DB'))
		{
			$this->database();
		}

		$CI = $this->get_instance();

		require_once(BASEPATH.'database/DB_forge'.EXT);
		require_once(BASEPATH.'database/drivers/'.$CI->db->dbdriver.'/'.$CI->db->dbdriver.'_forge'.EXT);
		$class = 'CI_DB_'.$CI->db->dbdriver.'_forge';

		$CI->dbforge = new $class();
	}


	// --------------------------------------------------------------------

	/**
	 * Loads a language file
	 *
	 * @access	public
	 * @param	array
	 * @param	string
	 * @return	void
	 */
	function language($file = array(), $lang = '')
	{
		$CI = $this->get_instance();

		if ( ! is_array($file))
		{
			$file = array($file);
		}

		foreach ($file as $langfile)
		{
			$CI->lang->load($langfile, $lang);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Loads a config file
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function config($file = '', $use_sections = FALSE, $fail_gracefully = FALSE)
	{
		$CI = $this->get_instance();
		$CI->config->load($file, $use_sections, $fail_gracefully);
	}

	// --------------------------------------------------------------------

	/**
	 * Loader
	 *
	 * This function is used to load views and files.
	 * Variables are prefixed with _ci_ to avoid symbol collision with
	 * variables made available to view files
	 *
	 * @access	private
	 * @param	array
	 * @return	void
	 */
	function _ci_load($_ci_data)
	{
		// Set the default data variables
		foreach (array('_ci_view', '_ci_vars', '_ci_path', '_ci_return') as $_ci_val)
		{
			$$_ci_val = ( ! isset($_ci_data[$_ci_val])) ? FALSE : $_ci_data[$_ci_val];
		}

		// Set the path to the requested file
		if ($_ci_path == '')
		{
			$_ci_ext = pathinfo($_ci_view, PATHINFO_EXTENSION);
			$_ci_file = ($_ci_ext == '') ? $_ci_view.EXT : $_ci_view;
			$_ci_path = $this->_ci_view_path.$_ci_file;
		}
		else
		{
			$_ci_x = explode('/', $_ci_path);
			$_ci_file = end($_ci_x);
		}

		if ( ! file_exists($_ci_path))
		{
			show_error('Unable to load the requested file: '.$_ci_file);
		}

		// This allows anything loaded using $this->load (views, files, etc.)
		// to become accessible from within the Controller and Model functions.

		$_ci_CI = $this->get_instance();
		foreach (get_object_vars($_ci_CI) as $_ci_key => $_ci_var)
		{
			if ( ! isset($this->$_ci_key))
			{
				$this->$_ci_key =& $_ci_CI->$_ci_key;
			}
		}

		/*
		 * Extract and cache variables
		 *
		 * You can either set variables using the dedicated $this->load_vars()
		 * function or via the second parameter of this function. We'll merge
		 * the two types and cache them so that views that are embedded within
		 * other views can have access to these variables.
		 */
		if (is_array($_ci_vars))
		{
			$this->_ci_cached_vars = array_merge($this->_ci_cached_vars, $_ci_vars);
		}
		extract($this->_ci_cached_vars);

		/*
		 * Buffer the output
		 *
		 * We buffer the output for two reasons:
		 * 1. Speed. You get a significant speed boost.
		 * 2. So that the final rendered template can be
		 * post-processed by the output class.  Why do we
		 * need post processing?  For one thing, in order to
		 * show the elapsed page load time.  Unless we
		 * can intercept the content right before it's sent to
		 * the browser and then stop the timer it won't be accurate.
		 */
		ob_start();

		// If the PHP installation does not support short tags we'll
		// do a little string replacement, changing the short tags
		// to standard PHP echo statements.

		if ((bool) @ini_get('short_open_tag') === FALSE AND config_item('rewrite_short_tags') == TRUE)
		{
			echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($_ci_path))));
		}
		else
		{
			include($_ci_path); // include() vs include_once() allows for multiple views with the same name
		}

		log_message('debug', 'File loaded: '.$_ci_path);

		// Return the file data if requested
		if ($_ci_return === TRUE)
		{
			$buffer = ob_get_contents();
			@ob_end_clean();
			return $buffer;
		}

		/*
		 * Flush the buffer... or buff the flusher?
		 *
		 * In order to permit views to be nested within
		 * other views, we need to flush the content back out whenever
		 * we are beyond the first level of output buffering so that
		 * it can be seen and included properly by the first included
		 * template and any subsequent ones. Oy!
		 *
		 */
		if (ob_get_level() > $this->_ci_ob_level + 1)
		{
			ob_end_flush();
		}
		else
		{
			$_ci_CI->output->append_output(ob_get_contents());
			@ob_end_clean();
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Load class
	 *
	 * This function loads the requested class.
	 *
	 * @access	private
	 * @param	string	the item that is being loaded
	 * @param	mixed	any additional parameters
	 * @param	string	an optional object name
	 * @return	void
	 */
	function _ci_load_class($class, $params = NULL, $object_name = NULL)
	{
		// Get the class name, and while we're at it trim any slashes.
		// The directory path can be included as part of the class name,
		// but we don't want a leading slash
		$class = str_replace(EXT, '', trim($class, '/'));

		// Was the path included with the class name?
		// We look for a slash to determine this
		$subdir = '';
		if (($last_slash = strrpos($class, '/')) !== FALSE)
		{
			// Extract the path
			$subdir = substr($class, 0, $last_slash + 1);

			// Get the filename from the path
			$class = substr($class, $last_slash + 1);
		}

		// We'll test for both lowercase and capitalized versions of the file name
		foreach (array(ucfirst($class), strtolower($class)) as $class)
		{
			$subclass = APPPATH.'libraries/'.$subdir.config_item('subclass_prefix').$class.EXT;

			// Is this a class extension request?
			if (file_exists($subclass))
			{
				$baseclass = BASEPATH.'libraries/'.ucfirst($class).EXT;

				if ( ! file_exists($baseclass))
				{
					log_message('error', "Unable to load the requested class: ".$class);
					show_error("Unable to load the requested class: ".$class);
				}

				// Safety:  Was the class already loaded by a previous call?
				if (in_array($subclass, $this->_ci_loaded_files))
				{
					// Before we deem this to be a duplicate request, let's see
					// if a custom object name is being supplied.  If so, we'll
					// return a new instance of the object
					if ( ! is_null($object_name))
					{
						$CI = $this->get_instance();
						if ( ! isset($CI->$object_name))
						{
							return $this->_ci_init_class($class, config_item('subclass_prefix'), $params, $object_name);
						}
					}

					$is_duplicate = TRUE;
					log_message('debug', $class." class already loaded. Second attempt ignored.");
					return;
				}

				include_once($baseclass);
				include_once($subclass);
				$this->_ci_loaded_files[] = $subclass;

				return $this->_ci_init_class($class, config_item('subclass_prefix'), $params, $object_name);
			}

			// Lets search for the requested library file and load it.
			$is_duplicate = FALSE;
			foreach ($this->_ci_library_paths as $path)
			{
				$filepath = $path.'libraries/'.$subdir.$class.EXT;

				// Does the file exist?  No?  Bummer...
				if ( ! file_exists($filepath))
				{
					continue;
				}

				// Safety:  Was the class already loaded by a previous call?
				if (in_array($filepath, $this->_ci_loaded_files))
				{
					// Before we deem this to be a duplicate request, let's see
					// if a custom object name is being supplied.  If so, we'll
					// return a new instance of the object
					if ( ! is_null($object_name))
					{
						$CI = $this->get_instance();
						if ( ! isset($CI->$object_name))
						{
							return $this->_ci_init_class($class, '', $params, $object_name);
						}
					}

					$is_duplicate = TRUE;
					log_message('debug', $class." class already loaded. Second attempt ignored.");
					return;
				}

				include_once($filepath);
				$this->_ci_loaded_files[] = $filepath;
				return $this->_ci_init_class($class, '', $params, $object_name);
			}

		} // END FOREACH

		// One last attempt.  Maybe the library is in a subdirectory, but it wasn't specified?
		if ($subdir == '')
		{
			$path = strtolower($class).'/'.$class;
			return $this->_ci_load_class($path, $params);
		}

		// If we got this far we were unable to find the requested class.
		// We do not issue errors if the load call failed due to a duplicate request
		if ($is_duplicate == FALSE)
		{
			log_message('error', "Unable to load the requested class: ".$class);
			show_error("Unable to load the requested class: ".$class);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Instantiates a class
	 *
	 * @access	private
	 * @param	string
	 * @param	string
	 * @param	string	an optional object name
	 * @return	null
	 */
	function _ci_init_class($class, $prefix = '', $config = FALSE, $object_name = NULL)
	{
		// Is there an associated config file for this class?  Note: these should always be lowercase
		if ($config === NULL)
		{
			// Fetch the config paths containing any package paths
			$config_component = $this->_ci_get_component('config');

			if (is_array($config_component->_config_paths))
			{
				// Break on the first found file, thus package files
				// are not overridden by default paths
				foreach ($config_component->_config_paths as $path)
				{
					// We test for both uppercase and lowercase, for servers that
					// are case-sensitive with regard to file names. Check for environment
					// first, global next
					if (defined('ENVIRONMENT') AND file_exists($path .'config/'.ENVIRONMENT.'/'.strtolower($class).EXT))
					{
						include_once($path .'config/'.ENVIRONMENT.'/'.strtolower($class).EXT);
						break;
					}
					elseif (defined('ENVIRONMENT') AND file_exists($path .'config/'.ENVIRONMENT.'/'.ucfirst(strtolower($class)).EXT))
					{
						include_once($path .'config/'.ENVIRONMENT.'/'.ucfirst(strtolower($class)).EXT);
						break;
					}
					elseif (file_exists($path .'config/'.strtolower($class).EXT))
					{
						include_once($path .'config/'.strtolower($class).EXT);
						break;
					}
					elseif (file_exists($path .'config/'.ucfirst(strtolower($class)).EXT))
					{
						include_once($path .'config/'.ucfirst(strtolower($class)).EXT);
						break;
					}
				}
			}
		}

		if ($prefix == '')
		{
			if (class_exists('CI_'.$class))
			{
				$name = 'CI_'.$class;
			}
			elseif (class_exists(config_item('subclass_prefix').$class))
			{
				$name = config_item('subclass_prefix').$class;
			}
			else
			{
				$name = $class;
			}
		}
		else
		{
			$name = $prefix.$class;
		}

		// Is the class name valid?
		if ( ! class_exists($name))
		{
			log_message('error', "Non-existent class: ".$name);
			show_error("Non-existent class: ".$class);
		}

		// Set the variable name we will assign the class to
		// Was a custom class name supplied?  If so we'll use it
		$class = strtolower($class);

		if (is_null($object_name))
		{
			$classvar = ( ! isset($this->_ci_varmap[$class])) ? $class : $this->_ci_varmap[$class];
		}
		else
		{
			$classvar = $object_name;
		}

		// Save the class name and object name
		$this->_ci_classes[$class] = $classvar;

		// Instantiate the class
		$CI = $this->get_instance();
		if ($config !== NULL)
		{
			$CI->$classvar = new $name($config);
		}
		else
		{
			$CI->$classvar = new $name;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Autoloader
	 *
	 * The config/autoload.php file contains an array that permits sub-systems,
	 * libraries, and helpers to be loaded automatically.
	 *
	 * @access	private
	 * @param	array
	 * @return	void
	 */
	function _ci_autoloader()
	{
		if (defined('ENVIRONMENT') AND file_exists(APPPATH.'config/'.ENVIRONMENT.'/autoload'.EXT))
		{
			include_once(APPPATH.'config/'.ENVIRONMENT.'/autoload'.EXT);
		}
		else
		{
			include_once(APPPATH.'config/autoload'.EXT);
		}


		if ( ! isset($autoload))
		{
			return FALSE;
		}

		// Autoload packages
		if (isset($autoload['packages']))
		{
			foreach ($autoload['packages'] as $package_path)
			{
				$this->add_package_path($package_path);
			}
		}

		// Load any custom config file
		if (count($autoload['config']) > 0)
		{
			$CI = $this->get_instance();
			foreach ($autoload['config'] as $key => $val)
			{
				$CI->config->load($val);
			}
		}

		// Autoload helpers and languages
		foreach (array('helper', 'language') as $type)
		{
			if (isset($autoload[$type]) AND count($autoload[$type]) > 0)
			{
				$this->$type($autoload[$type]);
			}
		}

		// A little tweak to remain backward compatible
		// The $autoload['core'] item was deprecated
		if ( ! isset($autoload['libraries']) AND isset($autoload['core']))
		{
			$autoload['libraries'] = $autoload['core'];
		}

		// Load libraries
		if (isset($autoload['libraries']) AND count($autoload['libraries']) > 0)
		{
			// Load the database driver.
			if (in_array('database', $autoload['libraries']))
			{
				$this->database();
				$autoload['libraries'] = array_diff($autoload['libraries'], array('database'));
			}

			// Load all other libraries
			foreach ($autoload['libraries'] as $item)
			{
				$this->library($item);
			}

			$this->_ci_autoload_libraries = $autoload['libraries'];
		}

		// Autoload models
		if (isset($autoload['model']))
		{
			$this->model($autoload['model']);

			$this->_ci_autoload_models = $autoload['model'];
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Get a reference to a specific library or model
	 *
	 * @access	private
	 * @return	bool
	 */
	function &_ci_get_component($component)
	{
		$CI = $this->get_instance();
		return $CI->$component;
	}

}

/* End of file MY_Loader.php */
/* Location: ./application/core/MY_Loader.php */
