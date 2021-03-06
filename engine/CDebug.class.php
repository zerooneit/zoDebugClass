<?php

class CDebug{
	protected static $settings = null;
	protected static $data = null;
	protected static $e = null;
	protected static $status = CDEBUG_FLOW_POINT;
	protected static $output = '';
	
	public static function init($settings = null){
		
		ob_start(array('CDebug', 'output_analizer'));
		
		self::$settings = new CDebug_Settings;
		$autoloads = self::getSettings()->helper_autoload;
		
		if ($autoloads) foreach ($autoloads as $helper){
			self::import($helper);
		}
		
		if (defined('E_DEPRECATED')) {
			self::getSettings()->Core->error_table[E_DEPRECATED] = CDEBUG_TOKEN_DEPRECATED;
		}

		if (defined('E_USER_DEPRECATED')) {
			self::getSettings()->Core->error_table[E_USER_DEPRECATED] = CDEBUG_TOKEN_USER_DEPRECATED;
		}
		
		self::$data =  CDebug_Data::Create();
		self::$e = new CDebug_ErrorManagement();
		
		register_shutdown_function('CDebug::_END');
	}
	
	public static function auto($method = null, $args = array()) {
		
		$Helpers = self::getSettings()->Helpers;
		
		if (!empty($Helpers) && !empty($method)) {

			foreach ($Helpers as $helper) {
				if (method_exists($helper, $method)){
					return call_user_func_array(array($helper, $method), $args);
				}
			}
		}
		
		
	}
	
	
	
	public static function isEnabled() {
		return ((bool)self::$settings -> enable & 255) && (self::$settings -> enable == CDEBUG_ENABLE);
	}
	
	public static function getSettings(){
		return self::$settings;
	}
	
	public static function getHelper($name){
		return self::getSettings()->$name;
	}
	
	public static function import($name,  $settings = array() ) {
		self::getSettings()->import($name, $settings);
	}
	
	public static function output_analizer($buffer) {
		self::$output = $buffer;
		
		$buffer.= self::auto(__FUNCTION__);
		self::message('output_analizer');
		
		return $buffer;
		
	}
	
	public static function getTrace() {
		return self::$data;
	}
	
	/**
	 * Add a Message to debug report
	 * @param string $message
	 */ 
	 
	public static function message($message) {
		if (!self::isEnabled())
			return false;

		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$caller = array_shift($bt);
		$file = $caller['file'];

		$data = array('value' => $message, 'line' => $caller['line'], 'file' => $file,  'trace' => $bt);
		$TOKEN = new TOKEN($data, CDEBUG_TOKEN_MESSAGE);
		self::getTrace() -> add($TOKEN -> returnToken());
		
	}
	
	public static function _END() {
		
		try {
			
			if (!is_null($e = error_get_last()) && !in_array(self::getSettings()->Core->error_table[$e['type']], array(CDEBUG_TOKEN_WARNING, CDEBUG_TOKEN_NOTICE, CDEBUG_TOKEN_DEPRECATED))) {
				self::getSettings()->hasFatalError = true;
				$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				$data = array('value' => $e['message'], 'line' => $e['line'], 'file' => $e['file'], 'trace' => $bt);
				$TOKEN = new TOKEN($data, self::getSettings()->Core->error_table[$e['type']]);
				self::getTrace() -> add($TOKEN -> returnToken());

			}
			self::getTrace()->saveSession();
			
			/*try {
				self::propagate(CDEBUG_EVENT_END, self::getTrace() -> Trace());
			} catch (Exception $e) {
				echo 'Trace: <pre>' . print_r($e, true) . '</pre><br />';
			}*/
			echo 'Errors: <pre>'.print_r(self::getTrace()->getBy(CDEBUG_TOKEN_ERROR),true).'</pre><br />';
			echo 'Trace: <pre>'.print_r(self::getTrace()->getTrace(),true).'</pre><br />';
			//echo 'Cookies: <pre>'.print_r($_COOKIE,true).'</pre><br />';
			echo 'Config: <pre>'.print_r(CDebug::getSettings(),true).'</pre><br />';
			echo 'Include Files: <pre>'.print_r(get_included_files(),true).'</pre><br />';
			
			
		} catch (Exception $e) {
			echo 'Error on end: <pre>' . print_r($e, true) . '</pre><br />';
		}

	}
}
/*
class CDebug {
	private static $settings = null;
	private static $data = null;
	private static $profile = null;
	private static $e_handling = null;
	private static $status = CDEBUG_FLOW_POINT;

	private static $Blocks = array('__MAIN__');
	private static $Modules = array();
	private static $output = 'null';

	public static $errorTable = array(
		E_ERROR 			=> CDEBUG_TOKEN_ERROR, 
		E_WARNING 			=> CDEBUG_TOKEN_WARNING, 
		E_PARSE 			=> CDEBUG_TOKEN_ERROR, 
		E_NOTICE 			=> CDEBUG_TOKEN_NOTICE, 
		E_CORE_ERROR 		=> CDEBUG_TOKEN_ERROR, 
		E_CORE_WARNING 		=> CDEBUG_TOKEN_WARNING, 
		E_COMPILE_ERROR 	=> CDEBUG_TOKEN_ERROR, 
		E_COMPILE_WARNING 	=> CDEBUG_TOKEN_WARNING, 
		E_USER_ERROR 		=> CDEBUG_TOKEN_USER_ERROR, 
		E_USER_WARNING 		=> CDEBUG_TOKEN_USER_WARNING, 
		E_USER_NOTICE 		=> CDEBUG_TOKEN_USER_NOTICE, 
		E_STRICT 			=> CDEBUG_TOKEN_NOTICE, 
		E_RECOVERABLE_ERROR => CDEBUG_TOKEN_ERROR);

	public static function init($configs = null) {
		ob_start(array(CDebug, 'output_analizer'));
		

		self::$settings = new CDebug_Settings();

		if (!empty($configs)) {
			foreach ($configs as $config => $value) {
				self::$settings -> $config = $value;
			}
		}
		self::$e_handling = new CDebug_ErrorManagement();
		self::$data = new CDebug_Data();

		register_shutdown_function('CDebug::_END');
	}

	public static function hasHTML() {
		return empty(self::$output);
	}

	public static function output_analizer($buffer) {

		self::$output = $buffer;
		return $buffer;
	}

	public static function _END() {
		/**
		 * Only Fatal Errors Catched
		 *
		try {
			if (!is_null($e = error_get_last()) && !in_array(self::$errorTable[$e['type']], array(CDEBUG_TOKEN_WARNING, CDEBUG_TOKEN_NOTICE, CDEBUG_TOKEN_DEPRECATED))) {
				CDebug::set('hasFatalError', true);
				$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				$data = array('value' => $e['message'], 'line' => $e['line'], 'file' => $e['file'], 'block' => CDebug::getBlock(), 'trace' => $bt);
				$TOKEN = new TOKEN($data, self::$errorTable[$e['type']]);
				self::getTrace() -> add($TOKEN -> returnToken());

			}

			try {
				self::propagate(CDEBUG_EVENT_END, self::getTrace() -> Trace());
			} catch (Exception $e) {
				echo 'Trace: <pre>' . print_r($e, true) . '</pre><br />';
			}
			//echo 'Trace: <pre>'.print_r(self::getTrace(),true).'</pre><br />';
			//echo 'Cookies: <pre>'.print_r($_COOKIE,true).'</pre><br />';
			//echo 'Config: <pre>'.print_r(CDebug::getConfig(),true).'</pre><br />';
			//echo 'Include Files: <pre>'.print_r(get_included_files(),true).'</pre><br />';
			//self::getTrace()->saveSession();
		} catch (Exception $e) {
			echo 'Trace: <pre>' . print_r($e, true) . '</pre><br />';
		}

	}

	public static function log($variable, $name) {
		if (!self::isEnabled())
			return false;

		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$caller = array_shift($bt);
		$file = $caller['file'];

		$data = array('value' => $variable, 'line' => $caller['line'], 'file' => $file, 'block' => self::getBlock(), 'name' => $name, 'trace' => $bt);

		$TOKEN = new TOKEN($data, self::varType($variable));
		self::$data -> add($TOKEN -> returnToken());
		self::propagate(CDEBUG_EVENT_LOG, $TOKEN -> returnToken());

	}

	

	public static function __callStatic($name, $arguments) {
		self::propagate($name, self::getTrace() -> Trace());
	}

	public static function setStatus($status = CDEBUG_FLOW_POINT) {
		if (!in_array($status, array(CDEBUG_FLOW_POINT, CDEBUG_START_POINT, CDEBUG_END_POINT)))
			$status = CDEBUG_FLOW_POINT;
		self::$status = $status;
	}

	public static function getStatus() {
		return self::$status;
	}

	public static function startBlock($name = '') {
		if (empty($name))
			return false;

		array_push(self::$Blocks, $name);
	}

	public static function endBlock() {
		array_pop(self::$Blocks);
	}

	public static function getBlock() {
		return end(self::$Blocks);
	}

	public static function set($config, $value) {
		if (empty($config) && empty($value))
			return false;

		self::$settings -> $config = $value;
		self::$e_handling -> reset();
	}

	public static function getConfig() {
		return self::$settings;
	}

	public static function getTrace() {
		return self::$data;
	}

	public static function saveSession() {
		self::$data -> saveSession();
	}

	

	public static function Uses($name, $file = null, $params = array()) {

		if (empty($file)) {
			$file = $name . '.module.php';
		}

		if (file_exists($file)) {
			$rootModule = $file;
		} elseif (defined('CDEBUG_USER_MODULES') && file_exists(CDEBUG_USER_MODULES . $file)) {
			$rootModule = CDEBUG_USER_MODULES . $file;
		} elseif (file_exists(CDEBUG_MODULES . $file)) {
			$rootModule = CDEBUG_MODULES . $file;
		} else {
			$rootModule = false;
			trigger_error("Module <b>$name</b> doesn't valid or not exists");
		}

		if ($rootModule) {
			$data = new stdClass;
			$data -> file = $rootModule;
			$data -> params = $params;
			self::$Modules[$name] = $data;
		}

	}

	public static function propagate($event = CDEBUG_EVENT_NONE, $data = array()) {
		$mods = self::$Modules;

		if (!empty(self::$Modules)) {

			foreach (self::$Modules as $name => $module) {

				try {
					require_once $module -> file;
					$REF = new ReflectionClass(ucfirst($name));
					if ($REF -> getShortName() !== ucfirst($name))
						throw new Exception();

					if ($REF -> isSubclassOf('CDebug_Module')) {
						$oReference = $REF -> newInstanceArgs(array($module -> params));
						$oReference -> trigger($event, $data);

					} else {
						trigger_error("Class: <b>" . $REF -> getShortName() . "</b> isn't a CDebugModule class");
					}

				} catch( Exception $Exception ) {
					print_r($Exception -> getMessage());
					trigger_error("Class Module: <b>$name</b> doesn't exists verify that class is perfect declared");
				}

			}
		}
	}

	
}*/
?>