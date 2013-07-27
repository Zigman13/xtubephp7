<?php


require_once 'Zend/Search/Lucene/Analysis/Analyzer/Common/Utf8Num.php';

require_once 'Zend/Search/Lucene/Analysis/TokenFilter/LowerCaseUtf8.php';



class Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive extends Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num
{
    public function __construct()
    {
        parent::__construct();

        $this->addFilter(new Zend_Search_Lucene_Analysis_TokenFilter_LowerCaseUtf8());
    }
}

