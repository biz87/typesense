<?php


/** @var TypeSense $typesense */
/** @var modX $modx */


$typesense = $modx->getService('typesense', 'TypeSense', MODX_CORE_PATH . 'components/typesense/');
$tmp = explode('/', $_GET['q']);

// Добавляю уникальные акционные чекбоксы
$unique = [];
$unique['key'] = 'sale';
$unique['checked'] = !empty($_GET['sale']);
$unique['disabled'] = in_array('sale', $tmp);
$unique['title'] = 'На акции';
$unique['field_name'] = 'sale';
$unique['icon'] = '/assets/template/img/labels_2/sale_2.png';
$uniques[] = $unique;

$unique = [];
$unique['key'] = 'availability';
$unique['checked'] = !empty($_GET['availability']);
$unique['disabled'] = false;
$unique['title'] = 'В наличии';
$unique['field_name'] = 'availability';
$unique['icon'] = '/assets/template/img/labels_2/v_nalichii.png';
$uniques[] = $unique;

$unique = [];
$unique['key'] = 'onorder';
$unique['checked'] = !empty($_GET['onorder']);
$unique['disabled'] = false;
$unique['title'] = 'Под заказ (1-2 дня)';
$unique['field_name'] = 'onorder';
$unique['icon'] = '/assets/template/img/labels_2/pod_zakaz.png';
$uniques[] = $unique;

$unique = [];
$unique['key'] = 'subscribe';
$unique['checked'] = !empty($_GET['subscribe']);
$unique['disabled'] = false;
$unique['title'] = 'По автоподписке';
$unique['field_name'] = 'subscribe';
$unique['icon'] = '/assets/template/img/labels_2/avtopodpiska.png';
$uniques[] = $unique;

$unique = [];
$unique['key'] = 'bomb';
$unique['checked'] = !empty($_GET['bomb']);
$unique['disabled'] = false;
$unique['title'] = 'Подарок к заказу';
$unique['field_name'] = 'bomb';
$unique['icon'] = '/assets/template/img/labels_2/bomb.png';
$uniques[] = $unique;

$filters = [];
$rawUniques = [];
$paginationSort = '';
$sortBy = 'availability:desc,popular_score:desc';

/** @var array $scriptProperties */
if (!empty($scriptProperties['mode']) && $scriptProperties['mode'] == 'brand_page') {
    $_SESSION['brand'] = [
        'id' => $modx->resource->id,
    ];
}

// Убираем возможный page=1
if (!empty($_GET['page']) && (int)$_GET['page'] === 1) {
    $q = $_GET['q'];
    unset($_GET['page']);
    unset($_GET['q']);
    $modx->sendRedirect($q . '?' . http_build_query($_GET));
}

foreach ($_GET as $key => $value) {
    switch ($key) {
        case 'page':
        case 'q':
        case 'query':
        case 'per_page':
        case 'popular':
            break;
        case 'sort':
            $whiteList = [
                'price:asc',
                'price:desc',
                'date:desc',
                'sale:desc',
                'popular:desc'
            ];
            if (in_array($value, $whiteList)) {
                switch ($value) {
                    case 'popular:desc':
                        $sort = 'popular_score:desc';
                        break;
                    default:
                        $sort = $value;
                }

                $sortBy = 'availability:desc,' . $sort;
                $paginationSort = $value;
            }
            break;
        case 'price':
            //  Такой разделитель где-то в старых подборках остался
            if (strpos($value, '/')) {
                $modx->log(1, 'Нашел странное поле price ' . $value);
                $tmp = explode('/', $value);
                $value = $tmp[0] . '|' . $tmp[1];
                $_GET['price'] = $value;
                unset($_GET['q']);
                $url = $modx->makeUrl($modx->resource->id, 'web', $_GET);
                $modx->sendRedirect($url);
            }
            break;
        case 'sale':
        case 'availability':
        case 'subscribe':
        case 'onorder':
        case 'bomb':
            $value = true;
            break;
        default:
            //  check is unique
            foreach ($uniques as $u) {
                if ($key === $u['field_name']) {
                    $rawUniques[$key] = $value;
                    break;
                }
            }

            $filters[$key] = explode('||', $value);
    }
}


$parent = $modx->resource->id;

/** @var array $scriptProperties */
if (!empty((int)$scriptProperties['brand_id'])) {
    $brand_id = (int)$scriptProperties['brand_id'];
    $parent = 922;
}

if (in_array('brands', $tmp)) {
    $resource_id = $modx->findResource(ltrim($_GET['q'], '/'), 'web');
    if (!empty($resource_id)) {
        $parent = 922;
    }
}

if (in_array('new', $tmp)) {
    $parent = 922;
    $rawUniques['new'] = true;
}

if (in_array('sale', $tmp)) {
    $parent = 922;
    $rawUniques['sale'] = true;
}

if (in_array('best', $tmp)) {
    $parent = 922;
    $rawUniques['favorite'] = true;
}

$page = !empty($_GET['page']) ? (int)$_GET['page'] : 1;


$query = '*';

$initParams = [];
$initParams['parent'] = $parent;
if (!empty($_SESSION['tags'])) {
    // Это у нас тэгированная страница, тут родитель назначен отдельно
    $initParams['parent'] = $_SESSION['tags']['parent'];
}
if (!empty($_GET['query'])) {
    $query = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING);
    $_SESSION['ts_query'] = $query;
    $initParams['parent'] = 922;
}
$initParams['page'] = $page;
$initParams['sort'] = $paginationSort;
$initParams['sort_by'] = $sortBy;
$initParams['query'] = $query;


$initParams['uri'] = '/' . $_GET['q'];

$initParams['per_page'] = 24;
$initParams['pageLimit'] = 10;


$initParams['filters'] = $filters;
$initParams['uniques'] = $rawUniques;

$initParams['tplPageWrapper'] = '@INLINE <ul class="pagination">[[+first]][[+prev]][[+pages]][[+next]][[+last]]</ul>';
$initParams['tplPageFirst'] = '@INLINE <span></span>';
$initParams['tplPagePrev'] = '@INLINE <li class="page-item"><a class="page-link" href="[[+href]]">&laquo;</a></li>';
$initParams['tplPageNext'] = '@INLINE <li class="page-item"><a class="page-link" href="[[+href]]">&raquo;</a></li>';
$initParams['tplPageLast'] = '@INLINE <span></span>';
$initParams['tplPageActive'] = '@INLINE <li class="page-item active"><a class="page-link" href="[[+href]]">[[+pageNo]]</a></li>';
$initParams['tplPage'] = '@INLINE <li class="page-item"><a class="page-link" href="[[+href]]">[[+pageNo]]</a></li>';
$initParams['tplPageSkip'] = '@INLINE <li class="disabled"><span>...</span></li>';

$initParams['uniques_array'] = $uniques;

$initParamsHash = md5(json_encode($initParams) . session_id());
$options = [\xPDO::OPT_CACHE_KEY => 'filter'];
$filterData = $modx->cacheManager->get($initParamsHash, $options);

if (!empty($filterData)) {
    $_SESSION['typesense'][$initParamsHash] ++;
    if ($_SESSION['typesense'][$initParamsHash] > 2) {
        $modx->cacheManager->delete($initParamsHash, $options);
        unset($_SESSION['typesense'][$initParamsHash]);
    }
    return $filterData;
}
$_SESSION['typesense'][$initParamsHash] = 1;

// инициирую конфиг для расчета
$typesense->filter->init($initParams);

//  Получаю массив фильтров с обновленными данными, согласно присланного конфига
$response = $typesense->filter->getFilters();
$filters = $response['filters'];

// Получаю все товары, подходящие под запрос.  Нужны для расчета пагинации, и total данных
switch (true) {
    case !empty($_GET['alias_tag']):
        //  Это тэгированная страница, тут предустановленный список ids
        $ids = $modx->getPlaceholder('skarb.list_products');
        $found = count(explode(',', $ids));
        break;
    default:
        $response = $typesense->filter->getProductsByFilter();
        $found = $response['found'];
}

// Получаю данные для расчета пагинации
$paginationData = $typesense->filter->getPaginationData($found);


//TODO  если обновлен хэш фильтров, нужно перейти на page 1
// Redirect to start if somebody specified incorrect page
if ($page > 1 && $page > $paginationData['pageCount']) {
    $q = $_GET['q'];
    unset($_GET['page']);
    unset($_GET['q']);
    $modx->sendRedirect($q . '?' . http_build_query($_GET));
}

// Формирую пагинацию
$pagination = $typesense->filter->buildPagination($paginationData);

// Получаю окончательный массив товаров, с учетом лимита на страницу и текущей страницы
$response = $typesense->filter->getProductsByFilter($initParams);
$ids = $response['ids'];
$found = $response['found'];

if (!empty($_GET['query']) && count($ids) === 1) {
    $url = $modx->makeUrl($ids[0]);
    $modx->sendRedirect($url);
}


if (!empty($ids)) {
    if (is_array($ids)) {
        $ids = implode(',', $ids);
    }
} else {
    // Абсурдный id покажет пустой результат. Иначе покажет все ресурсы из parent
    $ids = '99999999';
}

$sort = $typesense->filter->getSortItems();
$selected = $typesense->filter->getSelectedItems($found);

$pdoTools = $modx->getService('pdoTools');
$html = $pdoTools->runSnippet('msProducts', [
    'class' => 'msProduct',
    'tpl' => '@FILE chunks/v2/minishop2/card.tpl',
    'includeParentTVs' => 'inset_adv',
    'parents' => 922,
    'resources' => $ids,
    'level' => 5,
    'depth' => 5,
    'limit' => 24,
    'sortby' => 'ids',
]);

$cacheParams = [
    'filters' => $filters,
    'uniques' => $uniques,
    'html' => $html,
    'pagination' => $pagination,
    'sort' => $sort,
    'selected' => $selected,
    'total' => $found,
];
$modx->cacheManager->set($initParamsHash, $cacheParams, 300, $options);

return [
    'filters' => $filters,
    'uniques' => $uniques,
    'ids' => $ids,
    'pagination' => $pagination,
    'sort' => $sort,
    'selected' => $selected,
];
