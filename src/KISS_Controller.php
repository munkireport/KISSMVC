<?php 

namespace KISS;

//===============================================================
// Controller
// Parses the HTTP request and routes to the appropriate function
//===============================================================
class KISS_Controller {
	public $controller_path='../app/controllers/'; //with trailing slash
	public $web_folder='/'; //with trailing slash
	public $default_controller='main';
	public $default_function='index';
	public $request_uri_parts=array();

	function __construct($controller_path,$web_folder,$default_controller,$default_function)  {
		$this->controller_path=$controller_path;
		$this->web_folder=$web_folder;
		$this->default_controller=$default_controller;
		$this->default_function=$default_function;
		$this->parse_http_request();
		$this->route_request();
	}

	//This function parses the HTTP request to set the controller name, function name and parameter parts.
	function parse_http_request() {
		$requri = $_SERVER['REQUEST_URI'];
		if (strpos($requri,$this->web_folder)===0)
			$requri=substr($requri,strlen($this->web_folder));
		$this->request_uri_parts = $requri ? explode('/',$requri) : array();
		return $this;
	}

	//This function maps the controller name and function name to the file location of the .php file to include
	function route_request() {
		$controller = $this->default_controller;
		$function = $this->default_function;
		$params = array();

		$p = $this->request_uri_parts;
		if (isset($p[0]) && $p[0])
			$controller=$p[0];
		if (isset($p[1]) && $p[1])
			$function=$p[1];
		if (isset($p[2]))
			$params=array_slice($p,2);

		$controllerfile=$this->controller_path.$controller.'/'.$function.'.php';
		if (!preg_match('#^[A-Za-z0-9_-]+$#',$controller) || !file_exists($controllerfile))
			$this->request_not_found();

		$function='_'.$function;
		if (!preg_match('#^[A-Za-z_][A-Za-z0-9_-]*$#',$function) || function_exists($function))
			$this->request_not_found();
		require($controllerfile);
		if (!function_exists($function))
			$this->request_not_found();

		call_user_func_array($function,$params);
		return $this;
	}

	//Override this function for your own custom 404 page
	function request_not_found() {
		header("HTTP/1.0 404 Not Found");
		die('<html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p><p>Please go <a href="javascript: history.back(1)">back</a> and try again.</p><hr /><p>Powered By: <a href="http://kissmvc.com">KISSMVC</a></p></body></html>');
	}
}
