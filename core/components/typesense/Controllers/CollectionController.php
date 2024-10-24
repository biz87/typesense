<?php

require_once dirname(__FILE__, 2) . '/Request.php';

class CollectionController
{

    private $modx;
    protected $request;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->request = new Request($modx);
    }

    public function setScheme()
    {
        $schema = [
            'name' => 'products_2',
            'fields' => [
                [
                    'name' => 'id',
                    'type' => 'string',
                    'facet' => false,
                    'index' => false
                ],
                [
                    'name' => 'price',
                    'type' => 'float',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'date',
                    'type' => 'int32',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'partner_price',
                    'type' => 'float',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'thumb',
                    'type' => 'string',
                    'facet' => false,
                    'index' => false,
                    'optional' => true,
                ],
                [
                    'name' => 'availability',
                    'type' => 'bool',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'sale',
                    'type' => 'float',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'partner_sale',
                    'type' => 'float',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'onorder',
                    'type' => 'bool',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'subscribe',
                    'type' => 'bool',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'bomb',
                    'type' => 'bool',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'category',
                    'type' => 'string[]',
                    'locale' => 'ru',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'tag_pages',
                    'type' => 'string[]',
                    'locale' => 'ru',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'brand_page',
                    'type' => 'int32',
                    'locale' => 'ru',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'popular_score',
                    'type' => 'int32',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'available_position',
                    'type' => 'int32',
                    'facet' => false,
                    'index' => true
                ],
                [
                    'name' => 'popular',
                    'type' => 'bool',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'new',
                    'type' => 'bool',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'favorite',
                    'type' => 'bool',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'is_demo',
                    'type' => 'bool',
                    'facet' => true,
                    'index' => true
                ],
                [
                    'name' => 'content',
                    'type' => 'string',
                    'facet' => false,
                    'index' => true
                ],
                [
                    'name' => '.*',
                    'type' => 'auto',
                    'facet' => false,
                    'locale' => 'ru',
                ],
                [
                    'name' => '.*_facet',
                    'type' => 'auto',
                    'locale' => 'ru',
                    'facet' => true,
                    'index' => true
                ],
            ],

            'default_sorting_field' => 'available_position',
            'token_separators' => ['-', '&', '.', '/', "'"]
        ];

        return $this->request->post('collections', $schema);
    }

    public function retrieve()
    {
        return $this->request->get('collections/products_2');
    }

    public function drop()
    {
        return $this->request->delete('collections/products_2');
    }

    public function getFacetFields()
    {
        $output = [];
        $response = $this->retrieve();
        foreach ($response['fields'] as $item) {
            if ($item['facet'] && $item['name'] !== '.*_facet') {
                $output[] = $item['name'];
            }
        }
        return $output;
    }
}
