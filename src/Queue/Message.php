<?php
namespace mywin\Queue;
class Message
{
	protected $_data = [];
	
	public function addData($data) {
	    $this->_data[] = $data;
	    return $this;
	}
	
	public function setData() {
	    $this->_data = $data;
	    return $this;
	}
	
	public function getData() {
	    return $this->_data;
	}
}
