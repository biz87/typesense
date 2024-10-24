<?php

namespace Zoomx\Controllers\Api\V1\Search2;

use Zoomx\Controllers\Controller;

class SearchController extends Controller
{
    public function index()
    {
        $response = $this->searchProducts();

        return jsonx(
            [
                'success' => true,
                'products' => $response['products'],
                'total' => $response['total'],
                'prev' => $response['prev'],
                'next' => $response['next'],
                'counts' => $response['counts'],
                'tree' => $response['tree'],
            ]
        );
    }

    private function searchProducts()
    {
        $this->modx->addPackage('msearch2', MODX_CORE_PATH . 'components/msearch2/model/');
        $page = (int)$_POST['page'];
        /** @var \TypeSense $typesense */
        $typesense = $this->modx->getService('typesense', 'TypeSense', MODX_CORE_PATH . 'components/typesense/');
        $facet_query = '';
        if (!empty($_POST['facet'])) {
            $facet_query = filter_input(INPUT_POST, 'facet', FILTER_SANITIZE_STRING);
        }

        $searchParams = [];
        $query = $_POST['query'];
//        $query = filter_input(INPUT_POST, 'query', FILTER_SANITIZE_STRING);
        $alias = $this->loadAlias($query);
        $searchParams['q'] = $alias;
        $searchParams['page'] = $page;
        $searchParams['facet_by'] = 'category';
        $searchParams['filter_by'] = 'is_demo:=false';

        if (!empty($facet_query)) {
            $searchParams['filter_by'] = 'is_demo:=false&&category:' . $facet_query;
        }

        if (is_numeric($query)) {
            $searchParams['query_by'] = 'article,barcode,pagetitle,content';
        }

        $response = $typesense->search->search($searchParams);

        $output = [];
        $output['products'] = [];
        $output['found'] = 0;
        $output['prev'] = false;
        $output['next'] = false;
        $output['counts'] = [];
        $output['total'] = 0;
        if ($response['found'] > 0) {
            foreach ($response['hits'] as $item) {
                $tmp = [];

                $tmp['id'] = $item['document']['id'];
                $tmp['article'] = $item['document']['article'];
                $tmp['availability'] = $item['document']['availability'];
                $tmp['barcode'] = $item['document']['barcode'];
                $tmp['subscribe'] = (int)$item['document']['subscribe'];
                $tmp['bomb'] = (int)$item['document']['bomb'];
                $tmp['sale'] = $item['document']['sale'];
                $tmp['favorite'] = (int)$item['document']['favorite'];
                $tmp['pagetitle'] = $item['document']['pagetitle'];
                $tmp['new'] = (int)$item['document']['new'];
                $tmp['image'] = 'https://giperzoo.by' . $item['document']['thumb'];
                $tmp['url'] = 'https://giperzoo.by/' . $item['document']['uri'];


                $data = $this->getAddData($tmp['id']);
                if (!empty($data)) {
                    $tmp['availability'] = !empty($data['available_skarb']);
                    $tmp = array_merge($tmp, $data);

                    if ($this->modx->user->hasSessionContext('web') && $this->modx->user->Profile->get(
                            'partner'
                        ) == 1 && $tmp['partner_price'] > 0) {
                        $tmp['old_price'] = $tmp['price'];
                        $tmp['price'] = $tmp['partner_price'];
                    }

                    if ($tmp['price'] === 0) {
                        $tmp['availability'] = 0;
                    }
                }

                $output['products'][] = $tmp;
            }

            $output['total'] = $response['found'];

            if (!empty($response['facet_counts'])) {
                $output['counts'] = $response['facet_counts'][0]['counts'];
                if (empty($_POST['facet'])) {
                    $tree = $this->getTree($output['counts']);
                    $output['tree'] = $tree['tree'];
                    $output['total'] = $tree['totalTree'];
                }
            }

            $per_page = 10;
            if ($page > 1) {
                $output['prev'] = true;
            }

            if (($page * $per_page) < $output['total']) {
                $output['next'] = true;
            }
        }

        $this->saveHistory($query, $response['found']);

        return $output;
    }

    private function getTree($counts)
    {
        $tree = $this->buildTree();
        $totalTree = 0;
        foreach ($tree as $id_1 => $level1) {
            $total = 0;
            if (empty($level1['children'])) {
                unset($tree[$id_1]);
                continue;
            }
            foreach ($level1['children'] as $id_2 => $level2) {
                $count = $this->categoryNotEmpty($counts, $level2['id']);
                if ($count > 0) {
                    $total += $count;
                    $tree[$id_1]['children'][$id_2]['count'] = $count;
                } else {
                    unset($tree[$id_1]['children'][$id_2]);
                }
            }
            $tree[$id_1]['count'] = $total;
            if ($total === 0) {
                unset($tree[$id_1]);
            }
            $totalTree += $total;
        }
        return compact('tree', 'totalTree');
    }

    private function buildTree()
    {
        /** @var \pdoTools $pdoTools */
        $pdoTools = $this->modx->getService('pdoTools');
        $pdoFetch = $this->modx->getService('pdoFetch');
        $resources = $pdoFetch->getCollection(
            'msCategory',
            ['class_key' => 'msCategory'],
            ['select' => 'id, parent, pagetitle', 'sortby' => 'menuindex', 'sortdir' => 'asc']
        );
        $tree = $pdoTools->buildTree($resources);
        $output = $tree[922]['children'];
        return $output;
    }

    private function categoryNotEmpty($counts, $id)
    {
        foreach ($counts as $count) {
            if ((int)$count['value'] == (int)$id) {
                return $count['count'];
            }
        }
        return 0;
    }

    private function saveHistory($query, $found = 0)
    {
        $mseQuery = $this->modx->getObject(\mseQuery::class, ['query' => $query]);
        if ($mseQuery) {
            $mseQuery->set('quantity', $mseQuery->get('quantity') + 1);
        } else {
            $mseQuery = $this->modx->newObject(\mseQuery::class);
            $mseQuery->set('query', $query);
            $mseQuery->set('quantity', 1);
        }
        $mseQuery->set('found', $found);
        $mseQuery->save();
    }

    private function loadAlias($query)
    {
        $mseAlias = $this->modx->getObject(\mseAlias::class, ['word' => $query]);
        if ($mseAlias) {
            return $mseAlias->get('alias');
        }

        return $query;
    }

    private function getAddData($id)
    {
        $q = $this->modx->newQuery(\msProduct::class);
        $q->leftJoin(\msProductData::class, 'Data', 'msProduct.id = Data.id');
        $q->select('Data.available_skarb, Data.price, Data.partner_price, Data.old_price');
        $q->where([
            'msProduct.id' => $id
        ]);
        $q->prepare();
        $q->stmt->execute();
        return $q->stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
