<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class Xmlview extends CI_Controller
{
	public function __construct() {
		parent::__construct();
		$this->load->controller('bhd');
	}

	public function index()
	{

	    $response = $this->input->post('responseData');

		header('Content-type: application/xml');
		$xml = new SimpleXMLElement('<ShippingConfirmation  version="1.0"/>');
		$this->bhd->_toxml($xml, $response);
		print $xml->asXML();


	}

}
