<?php

require_once dirname(__FILE__, 2) . '/Request.php';

class DocumentController
{

    private $modx;
    protected $request;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->request = new Request($modx);
        $this->modx->addPackage(
            'skarbcabinet',
            $this->modx->getOption('core_path') . 'components/skarbcabinet/model/'
        );
    }

    public function updateOrCreate()
    {
        /** @var \bombs $bombs */
        $bombs = $this->modx->getService(
            'bombs',
            'bombs',
            MODX_CORE_PATH . 'components/bombs/'
        );

        $ids = $bombs->getAllBombIds();
        $toozikIds = $this->getToozikIds();

        $q = $this->modx->newQuery(msProduct::class);
        $q->leftJoin(msProductData::class, 'Data', 'Data.id = msProduct.id');
        $q->leftJoin(msVendor::class, 'msVendor', 'msVendor.id = Data.vendor');
        $q->where([
            'msProduct.class_key' => 'msProduct',
            'msProduct.published' => 1,
            'msProduct.deleted' => 0,
            'Data.article:!=' => '',
            'msProduct.parent:!=' => 7215,
//            'msProduct.id' => 10347,
        ]);
        $q->select(
            'msProduct.pagetitle,msProduct.parent, msProduct.content, msProduct.id, msProduct.barcode,
             msProduct.uri,  msProduct.createdon'
        );
        $q->select('Data.article,Data.thumb,Data.price,Data.old_price,Data.available_skarb,Data.subscribe,
        Data.onorder,Data.availability as available,Data.new,Data.favorite,Data.partner_price,Data.vendor');
//        $q->select('BombTV.value as BombTV');
        $q->select('msVendor.name as brand');
        $q->prepare();
        $q->stmt->execute();
        $items = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
        $update = 0;
        $create = 0;
        $errors = 0;
        foreach ($items as $item) {
            $id = $item['id'];
            unset($item['id']);


            //Сначала в наличии, затем под заказ, затем отсутствующие
            //  Нет в наличии = 0, Только под заказ = 1, В наличии = 2
            //  Если товар наш (Поставщик ЧТУП Тузик, подборка № 2) - то его вперед в каждой секции

            $availablePosition = 0;
            if (!empty($item['available'])) {
                $availablePosition = 2;

                if (in_array($id, $toozikIds)) {
                    $availablePosition = 3;
                }
            } elseif (!empty($item['onorder'])) {
                $availablePosition = 1;
            }


            $item['price'] = (float)$item['price'];
            $item['article'] = mb_strtolower($item['article']);
            $item['partner_price'] = (float)$item['partner_price'];
            $item['availability'] = (bool)$item['available'];
            $item['sale'] = (float)$this->getSalePrice($item);
            $item['partner_sale'] = (float)$this->getPartnerSalePrice($item);
            $item['subscribe'] = (bool)$item['subscribe'];
//            $item['bomb'] = !empty(json_decode($item['BombTV'], 1));
            $item['bomb'] = in_array($id, $ids);
            $item['is_demo'] = false;
            $item['onorder'] = !empty($item['onorder']) && empty($item['available']);
            $item['popular_score'] = (int)$this->getPopularScore($id);
            $item['date'] = (int)$item['createdon'];
            $item['new'] = (bool)$item['new'];
            $item['favorite'] = (bool)$item['favorite'];
            $item['available_position'] = $availablePosition;

            if (empty($item['barcode'])) {
                $item['barcode'] = '';
            }
            if (empty($item['thumb'])) {
                $item['thumb'] = '/assets/images/products/24879/small/zastavka-360x270.png';
            }
            $parentsIds = $this->modx->getParentIds($id, 3, ['context' => 'web']);
            $parents = [];
            foreach ($parentsIds as $k) {
                if (!in_array($k, [0])) {
                    $parents[] = (string)$k;
                }
            }
            $item['category'] = $parents;
            $item['content'] = $item['article'] . ' ' . $item['content'];

            $options = $this->getProductOptions($id);
            if (!empty($options)) {
                foreach ($options as $option) {
                    $key = $option['key'];
                    switch ($key) {
                        case 'strana-proizvoditel':
                            $item[$key . '_facet'] = $option['value'];
                            break;
                        default:
                            $item[$key . '_facet'] = mb_strtolower($option['value']);
                    }
                }
            }

            if (!empty($item['brand'])) {
                $item['brand_facet'] = $item['brand'];
            }


            $item['tag_pages'] = $this->getProductTagPages($id, $item['parent']);
            $item['brand_page'] = $this->getBrandPages($item['vendor']);

            $isExists = $this->isExists($id);
            if ($isExists) {
                $response = $this->singleUpdate($id, $item);
                if (!empty($response['message'])) {
                    $this->modx->log(
                        1,
                        print_r([
                            $response,
                            array_merge($item, ['id' => $id])
                        ], 1)
                    );
                    $errors++;
                    continue;
                }
                $update++;
                continue;
            }

            $response = $this->singleIndex(array_merge($item, ['id' => $id]));
            if (!empty($response['message'])) {
                $this->modx->log(
                    1,
                    print_r([
                        $response,
                        array_merge($item, ['id' => $id])
                    ], 1)
                );
                $errors++;
                continue;
            }
            $create++;
        }

        return ['update' => $update, 'create' => $create, 'errors' => $errors];
    }

    public function addDemoProducts()
    {
        $ids = [];
        $q = $this->modx->newQuery(msProduct::class);
        $q->leftJoin(msProductData::class, 'Data', 'Data.id = msProduct.id');
        $q->leftJoin(msVendor::class, 'msVendor', 'msVendor.id = Data.vendor');
        $q->where([
            'msProduct.class_key' => 'msProduct',
            'msProduct.published' => 1,
            'msProduct.deleted' => 0,
            'msProduct.parent' => 7215,
        ]);
        $q->select(
            'msProduct.pagetitle,msProduct.parent, msProduct.content, msProduct.id, msProduct.barcode,
             msProduct.uri,  msProduct.createdon'
        );
        $q->select('Data.article,Data.thumb,Data.price,Data.old_price,Data.available_skarb,Data.subscribe,
        Data.onorder,Data.availability as available,Data.new,Data.favorite,Data.partner_price,Data.vendor');
        $q->select('msVendor.name as brand');
        $q->prepare();
        $q->stmt->execute();
        $items = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
        $update = 0;
        $create = 0;
        $errors = 0;
        foreach ($items as $item) {
            $id = $item['id'];
            $ids[] = $id;
            unset($item['id']);
            $item['price'] = (float)$item['price'];
            $item['article'] = mb_strtolower($item['article']);
            $item['partner_price'] = (float)$item['partner_price'];
            $item['availability'] = (bool)$item['available'];
            $item['available_position'] = 0;
            $item['sale'] = (float)$this->getSalePrice($item);
            $item['partner_sale'] = (float)$this->getPartnerSalePrice($item);
            $item['subscribe'] = false;
            $item['bomb'] = false;
            $item['is_demo'] = true;
            $item['onorder'] = !empty($item['onorder']) && empty($item['available']);
            $item['popular_score'] = (int)$this->getPopularScore($id);
            $item['date'] = (int)$item['createdon'];
            $item['new'] = (bool)$item['new'];
            $item['favorite'] = (bool)$item['favorite'];

            if (empty($item['barcode'])) {
                $item['barcode'] = '';
            }
            if (empty($item['thumb'])) {
                $item['thumb'] = '/assets/images/products/24879/small/zastavka-360x270.png';
            }
            $item['category'] = [(string)7215];
            $item['content'] = $item['article'] . ' ' . $item['content'];

            if (!empty($item['brand'])) {
                $item['brand_facet'] = $item['brand'];
            }

            $isExists = $this->isExists($id);
            if ($isExists) {
                $response = $this->singleUpdate($id, $item);
                if (!empty($response['message'])) {
                    $this->modx->log(
                        1,
                        print_r([
                            $response,
                            array_merge($item, ['id' => $id])
                        ], 1)
                    );
                    $errors++;
                    continue;
                }
                $update++;
                continue;
            }

            $response = $this->singleIndex(array_merge($item, ['id' => $id]));
            if (!empty($response['message'])) {
                $this->modx->log(
                    1,
                    print_r([
                        $response,
                        array_merge($item, ['id' => $id])
                    ], 1)
                );
                $errors++;
                continue;
            }
            $create++;
        }


        // Все демо-товары помечаю доступными на складе, чтобы не было проблем на фронте
        $this->modx->updateCollection(msProductData::class, ['available_skarb' => 1], ['id:IN' => $ids]);


        return ['update' => $update, 'create' => $create, 'errors' => $errors];
    }

    public function updateAvailable()
    {
        $q = $this->modx->newQuery(msProduct::class);
        $q->leftJoin(msProductData::class, 'Data', 'Data.id = msProduct.id');
        $q->where([
            'msProduct.class_key' => 'msProduct',
            'msProduct.published' => 1,
            'msProduct.deleted' => 0,
            'Data.article:!=' => '',
            'msProduct.parent:!=' => 7215,
        ]);
        $q->select(
            'msProduct.id'
        );
        $q->select('Data.available_skarb,Data.onorder,Data.availability as available');
        $q->prepare();
        $q->stmt->execute();
        $items = $q->stmt->fetchAll(PDO::FETCH_ASSOC);

        $update = 0;
        $errors = 0;
        foreach ($items as $item) {
            $id = $item['id'];
            unset($item['id']);

            $availablePosition = 0;
            if (!empty($item['available'])) {
                $availablePosition = 2;
            } elseif (!empty($item['onorder'])) {
                $availablePosition = 1;
            }

            $item['availability'] = (bool)$item['available'];
            $item['onorder'] = !empty($item['onorder']) && empty($item['available']);
            $item['available_position'] = $availablePosition;
            $isExists = $this->isExists($id);
            if (!$isExists) {
                continue;
            }
            $response = $this->singleUpdate($id, $item);
            if (!empty($response['message'])) {
                $this->modx->log(
                    1,
                    print_r([
                        $response,
                        array_merge($item, ['id' => $id])
                    ], 1)
                );
                $errors++;
                continue;
            }
            $update++;
        }

        return ['update' => $update, 'errors' => $errors];
    }

    public function updateTagsIds($product_id, $tag_id = 0)
    {
        $q = $this->modx->newQuery(msProduct::class);
        $q->leftJoin(msProductData::class, 'Data', 'Data.id = msProduct.id');
        $q->where([
            'msProduct.class_key' => 'msProduct',
            'msProduct.published' => 1,
            'msProduct.deleted' => 0,
            'Data.article:!=' => '',
            'msProduct.parent:!=' => 7215,
            'msProduct.id' => $product_id
        ]);
        $q->select(
            'msProduct.id'
        );
        $q->prepare();
        $q->stmt->execute();
        $item = $q->stmt->fetch(PDO::FETCH_ASSOC);


        $id = $item['id'];
        unset($item['id']);

        $tag_pages = $this->getProductTagPages($id, $item['parent']);
        if (!empty($tag_id) && !in_array($tag_id, $tag_pages)) {
            $tag_pages[] = $tag_id;
        }

        $item['tag_pages'] = $tag_pages;

        $isExists = $this->isExists($id);
        if (!$isExists) {
            return true;
        }
        $response = $this->singleUpdate($id, $item);
        if (!empty($response['message'])) {
            $this->modx->log(
                1,
                print_r([
                    $response,
                    array_merge($item, ['id' => $id])
                ], 1)
            );
            return true;
        }

        return true;
    }

    public function cleanTagsIds($product_id, $tag_id)
    {
        $q = $this->modx->newQuery(msProduct::class);
        $q->leftJoin(msProductData::class, 'Data', 'Data.id = msProduct.id');
        $q->where([
            'msProduct.class_key' => 'msProduct',
            'msProduct.published' => 1,
            'msProduct.deleted' => 0,
            'Data.article:!=' => '',
            'msProduct.parent:!=' => 7215,
            'msProduct.id' => $product_id
        ]);
        $q->select(
            'msProduct.id'
        );
        $q->prepare();
        $q->stmt->execute();
        $item = $q->stmt->fetch(PDO::FETCH_ASSOC);


        $id = $item['id'];
        unset($item['id']);

        $tag_pages = $this->getProductTagPages($id, $item['parent']);

        $key = array_search($tag_id, $tag_pages);
        if ($key !== false) {
            unset($tag_pages[$key]);
        }

        $item['tag_pages'] = $tag_pages;

        $isExists = $this->isExists($id);
        if (!$isExists) {
            return true;
        }
        $response = $this->singleUpdate($id, $item);
        if (!empty($response['message'])) {
            $this->modx->log(
                1,
                print_r([
                    $response,
                    array_merge($item, ['id' => $id])
                ], 1)
            );
            return true;
        }

        return true;
    }

    public function singleIndex($product)
    {
        $data = [];
        $data['id'] = $product['id'];
        $data['date'] = $product['date'];
        $data['pagetitle'] = $product['pagetitle'];
        $data['article'] = $product['article'];
        $data['barcode'] = $product['barcode'];
        $data['content'] = $this->getContent($product);
        $data['category'] = $product['category'];
        $data['uri'] = $product['uri'];
        $data['thumb'] = $product['thumb'];
        if (empty($product['thumb'])) {
            $data['thumb'] = '/assets/images/products/24879/small/zastavka-360x270.png';
        }
        $data['price'] = (float)$product['price'];
        $data['partner_price'] = (float)$product['partner_price'];

        $data['availability'] = (bool)$product['availability'];
        $data['sale'] = (float)$product['sale'];
        $data['partner_sale'] = (float)$product['partner_sale'];
        $data['onorder'] = (bool)$product['onorder'];
        $data['available_position'] = $product['available_position'];
        $data['subscribe'] = (bool)$product['subscribe'];
        $data['bomb'] = (bool)$product['bomb'];
        $data['popular_score'] = (int)$product['popular_score'];
        $data['popular'] = (bool)$product['popular'];
        $data['is_demo'] = (bool)$product['is_demo'];
        $data['new'] = (bool)$product['new'];
        $data['favorite'] = (bool)$product['favorite'];
        $data['tag_pages'] = !empty($product['tag_pages']) ? $product['tag_pages'] : [];
        $data['brand_page'] = 0;
        if (!empty((int)$product['brand_page'])) {
            $data['brand_page'] = (int)$product['brand_page'];
        }
        $keys = array_keys($product);
        foreach ($keys as $key) {
            $tmp = explode('_facet', $key);
            if (count($tmp) === 2) {
                $data[$key] = $product[$key];
            }
        }

        return $this->request->post('collections/products_2/documents', $data);
    }

    public function get($id)
    {
        return $this->request->get('collections/products_2/documents/' . $id);
    }

    public function singleUpdate($id, $data = [])
    {
        return $this->request->patch('collections/products_2/documents/' . $id, $data);
    }

    private function isExists($id)
    {
        $response = $this->get($id);
        return empty($response['message']);
    }

    private function getProductOptions($product_id)
    {
        $c = $this->modx->newQuery(msProductOption::class);
        $c->leftJoin(msOption::class, 'Option', 'Option.`key` = msProductOption.`key`');

        $c->where([
            'msProductOption.product_id' => $product_id,
            'Option.active' => 1,
            'Option.show_in_filter' => 1
        ]);
        $c->select('msProductOption.`key`, msProductOption.value');
        $c->select('Option.caption');
        $c->prepare();
        $c->stmt->execute();
        return $c->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPopularScore($product_id)
    {
        $q = $this->modx->newQuery(msOrderProduct::class);
        $q->where([
            'product_id' => $product_id
        ]);
        $q->select('SUM(count) as score');
        $q->prepare();
        $q->stmt->execute();
        return $q->stmt->fetch(PDO::FETCH_COLUMN);
    }

    private function getProductTagPages($id, $category)
    {
        //  Беру тэгированные страницы, у которых товар назначен напрямую
        $q = $this->modx->newQuery('skarbTagsProducts');
        $q->where([
            'product_id' => $id,
        ]);
        $q->select('DISTINCT(item_id)');
        $q->prepare();
        $q->stmt->execute();
        $ids = $q->stmt->fetchAll(PDO::FETCH_COLUMN);
        // Беру тэгированные страницы, к которым товар относится посредством назначенной опции

        $q = $this->modx->newQuery('skarbTagsOptions');
        $where = [
            'category' => $category,
        ];
        if (!empty($ids)) {
            $where['id:NOT IN'] = $ids;
        }
        $q->where($where);
        $q->select('item_id, option_key, value');
        $q->prepare();
        $q->stmt->execute();
        $tagPagesOptions = $q->stmt->fetchAll(PDO::FETCH_ASSOC);

        $q = $this->modx->newQuery('msProductOption');
        $q->where([
            'product_id' => $id,
            'value:!=' => ''
        ]);
        $q->select('`key`, value');
        $q->prepare();
        $q->stmt->execute();
        $productOptions = $q->stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tagPagesOptions as $tagPagesOption) {
            foreach ($productOptions as $productOption) {
                if ($tagPagesOption['option_key'] === $productOption['key']
                    && $tagPagesOption['value'] === $productOption['value']) {
                    if (!in_array($tagPagesOption['item_id'], $ids)) {
                        $ids[] = $tagPagesOption['item_id'];
                    }
                }
            }
        }

        return $ids;
    }

    private function getBrandPages($vendor_id)
    {
        $q = $this->modx->newQuery(modResource::class);
        $q->leftJoin(modTemplateVarResource::class, 'Brand', 'modResource.id = Brand.contentid AND Brand.tmplvarid = 15');
        $q->where([
            'modResource.parent' => 2,
            'Brand.value' => $vendor_id
        ]);
        $q->select('modResource.id');
        $q->prepare();
        $q->stmt->execute();
        return $q->stmt->fetch(PDO::FETCH_COLUMN);
    }

    private function getSalePrice($product)
    {
        if (empty($product['available_skarb'])) {
            return 0;
        }
        if ($product['old_price'] > 0 && $product['price'] > 0) {
            return round((100 - ($product['price'] * 100 / $product['old_price'])), 2);
        }

        return 0;
    }

    private function getPartnerSalePrice($product)
    {
        if (empty($product['available_skarb'])) {
            return 0;
        }
        if ($product['partner_price'] > 0 && $product['price'] > 0) {
            $product['old_price'] = $product['price'];
            $product['price'] = $product['partner_price'];
        }

        if ($product['old_price'] > 0 && $product['price'] > 0) {
            return round((100 - ($product['price'] * 100 / $product['old_price'])), 2);
        }

        return 0;
    }

    private function getContent($product)
    {
        $content = $product['content'];
        $keywords = preg_split("/[\s,&.\/']+/", $product['pagetitle']);
        $pagetitle = implode(' ', $keywords);

        return implode(',', [
            $product['article'],
            $pagetitle,
            $content
        ]);
    }

    private function getToozikIds()
    {
        /** @var \Selections $selections */
        $selections = $this->modx->getService(
            'selections',
            'Selections',
            MODX_CORE_PATH . 'components/selections/'
        );

        return $selections->getIdsBySelection(2);
    }
}
