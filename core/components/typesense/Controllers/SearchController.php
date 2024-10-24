<?php

require_once dirname(__FILE__, 2) . '/Request.php';

class SearchController
{
    private $typesense;
    private $modx;
    protected $request;

    public function __construct(TypeSense $typesense)
    {
        $this->typesense = $typesense;
        $this->modx = $typesense->modx;
        $this->request = new Request($this->modx);
    }

    public function search($param)
    {
        $searchParameters = [];
        if (isset($param['q'])) {
            $searchParameters['q'] = mb_strtolower($param['q']);
        }
        if (isset($param['sort_by'])) {
            $searchParameters['sort_by'] = $param['sort_by'];
        } else {
            $searchParameters['sort_by'] = 'availability:desc';
        }
        if (isset($param['query_by'])) {
            $searchParameters['query_by'] = $param['query_by'];
        } else {
            $searchParameters['query_by'] = 'pagetitle,content,article,barcode';
        }
        if (isset($param['page'])) {
            $searchParameters['page'] = $param['page'];
        } else {
            $searchParameters['page'] = 1;
        }
        if (isset($param['per_page'])) {
            $searchParameters['per_page'] = $param['per_page'];
        } else {
            $searchParameters['per_page'] = 10;
        }


        if (isset($param['facet_by'])) {
            if (is_array($param['facet_by'])) {
                $param['facet_by'] = implode(',', $param['facet_by']);
            }
            $searchParameters['facet_by'] = $param['facet_by'];
        } else {
            $facetedFields = $this->typesense->collection->getFacetFields();
            $searchParameters['facet_by'] = implode(',', $facetedFields);
        }
        $searchParameters['max_facet_values'] = 100;

        if (isset($param['filter_by'])) {
            $searchParameters['filter_by'] = $param['filter_by'];
        } else {
            if (!empty($param['facet_query'])) {
                $searchParameters['filter_by'] = 'category:' . $param['facet_query'];
            }
        }

        $path = 'collections/products_2/documents/search?' . http_build_query($searchParameters);
        return $this->request->get($path);
    }
}
