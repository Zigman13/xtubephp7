<?php


require_once 'Zend/Gdata/App/Extension.php';

class Zend_Gdata_App_Extension_Uri extends Zend_Gdata_App_Extension
{

    protected $_rootElement = 'uri';

    public function __construct($text = null)
    {
        parent::__construct();
        $this->_text = $text;
    }

}
