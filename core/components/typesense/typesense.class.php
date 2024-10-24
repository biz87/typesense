<?php

require_once dirname(__FILE__) . '/Controllers/CollectionController.php';
require_once dirname(__FILE__) . '/Controllers/DocumentController.php';
require_once dirname(__FILE__) . '/Controllers/SearchController.php';
require_once dirname(__FILE__) . '/Controllers/FilterController.php';
require_once dirname(__FILE__) . '/Request.php';

class TypeSense
{
    public $modx;
    private $apiKey;
    public $collection;
    public $document;
    public $search;
    public $filter;
    public $request;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->apiKey = '8t4LwfSVBSKssXoF7gxVq6UBnFwT68Xtb8bCKFmYjXw3sapJ';
        $this->collection = new CollectionController($modx);
        $this->document = new DocumentController($modx);
        $this->search = new SearchController($this);
        $this->filter = new FilterController($this);
        $this->request = new Request($modx);


        //Вызов из MODX
        //$typesense = $modx->getService('typesense', 'TypeSense', MODX_CORE_PATH . 'components/typesense/');
        //$response =  $typesense->setScheme();
    }
}
