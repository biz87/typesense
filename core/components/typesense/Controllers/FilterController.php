<?php

require_once dirname(__FILE__, 2) . '/Request.php';

class FilterController
{
    private $typesense;
    private $modx;
    protected $request;

    private $inputConfig;

    private $pdoTools;
    private $translate;

    public function __construct(TypeSense $typesense)
    {
        $this->typesense = $typesense;
        $this->modx = $typesense->modx;
        $this->request = new Request($this->modx);
        $this->pdoTools = $this->modx->getService('pdoTools');
    }

    public function init($inputParams)
    {
        $this->inputConfig = $inputParams;
    }

    public function getFilters()
    {
        $categoryFields = $this->getFieldsByParent($this->inputConfig['parent']);
        $facetedFields = $this->typesense->collection->getFacetFields();

        $facetBy[] = 'sale';
        $this->translate['sale'] = 'На акции';

        $facetBy[] = 'availability';
        $this->translate['availability'] = 'В наличии';

        $facetBy[] = 'onorder';
        $this->translate['onorder'] = 'Под заказ (1 -2 дня)';

        $facetBy[] = 'subscribe';
        $this->translate['subscribe'] = 'По автоподписке';

        $facetBy[] = 'bomb';
        $this->translate['bomb'] = 'Подарок к заказу';

        $facetBy[] = 'price';
        $this->translate['price'] = 'Цена';

        $facetBy[] = 'brand_facet(sort_by: _alpha:asc)';
        $this->translate['brand_facet'] = 'Бренд';

        foreach ($categoryFields as $item) {
            if (in_array($item['key'] . '_facet', $facetedFields)) {
                $facetBy[] = $item['key'] . '_facet(sort_by: _alpha:asc)';
                $this->translate[$item['key'] . '_facet'] = $item['caption'];
            }
        }

        $searchParams = [];
        $searchParams['q'] = '*';
        if (!empty($this->inputConfig['query'])) {
            $searchParams['q'] = $this->inputConfig['query'];
        }
        if (!empty($this->inputConfig['parent'])) {
            $filterBy[] = 'category:=' . $this->inputConfig['parent'];
        }
        if (!empty($_SESSION['tags']) && !empty($_SESSION['tags']['id'])) {
            $filterBy[] = 'tag_pages:=' . $_SESSION['tags']['id'];
        }
        if (!empty($_SESSION['brand'])) {
            $filterBy[] = 'brand_page:=' . (int)$_SESSION['brand']['id'];
        }
        if (!empty($_SESSION['ts_favorite'])) {
            $filterBy[] = 'favorite:=true';
            $searchParams['group_by'] = 'favorite';
        }

        if (!empty($filterBy)) {
            $searchParams['filter_by'] = implode('&&', $filterBy);
        }

        if (!empty($facetBy)) {
            $searchParams['facet_by'] = $facetBy;
        }
        return $this->getUpdateFilters($searchParams);
    }

    public function getProductsByFilter($params = [])
    {
        $searchParams = [];
        $searchParams['q'] = '*';
        if (!empty($this->inputConfig['query'])) {
            $searchParams['q'] = $this->inputConfig['query'];
        }
        if (!empty($_SESSION['ts_query'])) {
            $searchParams['q'] = $_SESSION['ts_query'];
        }
        if (!empty($this->inputConfig['sort_by'])) {
            $searchParams['sort_by'] = $this->inputConfig['sort_by'];
        }

        if (!empty($params)) {
            $searchParams = array_merge($searchParams, $params);
        }

        $filterBy = $this->buildFilterBy();

        // Для простоты еще раз добавил в перезаписаную переменную фильтрования родителя
        if (!empty($this->inputConfig['parent'])) {
            $filterBy[] = 'category:=' . $this->inputConfig['parent'];
        }

        $searchParams['filter_by'] = implode('&&', $filterBy);
        // Получаю фильтр с примененными параметрами
        return $this->searchWithParams($searchParams);
    }

    /**
     * @return mixed
     */
    public function getPaginationData($total = 0)
    {
        $perPage = $this->inputConfig['per_page'];
        $offset = 0;
        $pageCount = $total > $offset
            ? ceil(($total - $offset) / $perPage)
            : 0;


        return compact('total', 'pageCount');
    }

    public function buildPagination($paginationData)
    {

        // $paginationData = total, pageCount
        $page = $this->inputConfig['page'];
        $pageCount = $paginationData['pageCount'];
        $url = $this->inputConfig['uri'];

        $tplPageWrapper = $this->inputConfig['tplPageWrapper'];
        $tplPageFirst = $this->inputConfig['tplPageFirst'];
        $tplPagePrev = $this->inputConfig['tplPagePrev'];
        $tplPageNext = $this->inputConfig['tplPageNext'];
        $tplPageLast = $this->inputConfig['tplPageLast'];


        $pagination = [
            'first' => $page > 1
                ? $this->makePageLink($url, 1, $tplPageFirst)
                : '',
            'prev' => $page > 1
                ? $this->makePageLink($url, $page - 1, $tplPagePrev)
                : '',
            'pages' => $this->buildModernPagination($page, $pageCount, $url),
            'next' => $page < $pageCount
                ? $this->makePageLink($url, $page + 1, $tplPageNext)
                : '',
            'last' => $page < $pageCount
                ? $this->makePageLink($url, $pageCount, $tplPageLast)
                : '',
        ];

        if ((int)$pageCount === 1) {
            return '';
        }

        return $this->pdoTools->getChunk($tplPageWrapper, $pagination);
    }

    public function getSortItems()
    {
        $sortItems = [
            [
                'sort' => 'price:asc',
                'title' => 'Сначала дешевые',
                'current' => false,
            ],
            [
                'sort' => 'price:desc',
                'title' => 'Сначала дорогие',
                'current' => false,
            ],
            [
                'sort' => 'date:desc',
                'title' => 'Сначала новинки',
                'current' => false,
            ],
            [
                'sort' => 'sale:desc',
                'title' => 'По скидке',
                'current' => false,
            ],
            [
                'sort' => 'popular:desc',
                'title' => 'Популярные',
                'current' => true,
            ],
        ];

        foreach ($sortItems as &$item) {
            if (!empty($_POST['sort'])) {
                if ($_POST['sort'] === $item['sort']) {
                    $item['current'] = true;
                } else {
                    $item['url'] = $this->makeSortUrl($item['sort']);
                    $item['current'] = false;
                }
            } else {
                $item['url'] = $this->makeSortUrl($item['sort']);
            }
        }
        unset($item);

        return $this->pdoTools->getChunk('@INLINE   
                    <div class="sort__title">Сортировка:</div>
                    {foreach $sortItems as $item}
                        {if $item.current?}
                            <span class="sort__current">{$item.title}</span>
                        {else}
                            <a href="{$uri}?{$item.url}" class="sort__item">{$item.title}</a>
                        {/if}
                    {/foreach}
                ', ['sortItems' => $sortItems, 'uri' => $this->inputConfig['uri']]);
    }

    public function getSelectedItems($found = 0)
    {
        $filters = array_merge($this->inputConfig['filters'], $this->inputConfig['uniques']);
        $items = [];
        if (!empty($filters)) {
            foreach ($filters as $key => $values) {
                switch ($key) {
                    case 'price':
                        break;
                    default:
                        if (!empty($values)) {
                            if (is_array($values)) {
                                foreach ($values as $value) {
                                    $items[] = [
                                        'key' => $key,
                                        'value' => $value,
                                    ];
                                }
                            } elseif (!empty($this->inputConfig['uniques_array'])) {
                                foreach ($this->inputConfig['uniques_array'] as $uniquesItem) {
                                    if ($uniquesItem['key'] === $key) {
                                        $items[] = [
                                            'key' => $key,
                                            'value' => $uniquesItem['title']
                                        ];
                                    }
                                }
                            }
                        }
                }
            }
        }

        return $this->pdoTools->getChunk('@INLINE  
                    <div class="selected__title">Найдено товаров: {$found}</div>
                    {if $items|length > 0} 
                    <div class="selected__title">| Вы выбрали:</div>
                    {foreach $items as $item}
                       <span class="selected__item" data-value="{$item.value}" data-key="{$item.key}">{$item.value}</span>
                    {/foreach}
                    {/if}
                      
                ', ['items' => $items, 'found' => $found]);
    }

    private function getFieldsByParent($parent_id)
    {
        $q = $this->modx->newQuery(msCategoryOption::class);
        $q->leftJoin(msOption::class, 'Option', 'Option.id = msCategoryOption.option_id');
        $q->where([
            'msCategoryOption.category_id' => $parent_id,
            'msCategoryOption.active' => 1,
            'Option.active' => 1,
            'Option.show_in_filter' => 1
        ]);
        $q->select('Option.`key`, Option.caption');
        $q->sortby('msCategoryOption.`rank`');
        $q->prepare();
        $q->stmt->execute();
        return $q->stmt->fetchAll(\PDO::FETCH_ASSOC);
    }


    /**
     * Метод возвращает фильтры для базовых параметров запроса (parent, query))
     * @param $searchParams
     * @return array
     */
    private function getBasicFilters($searchParams)
    {
        if (is_array($searchParams['facet_by'])) {
            $searchParams['facet_by'] = implode(',', $searchParams['facet_by']);
        }

        $response = $this->typesense->search->search($searchParams);
        if (!empty($response['message'])) {
            $this->modx->log(1, $response['message']);
            $this->modx->log(1, print_r($searchParams, 1));
        }
        $filters = [];
        if (!empty($response['facet_counts'])) {
            foreach ($response['facet_counts'] as $filterItem) {
                if (empty($filterItem['counts'])) {
                    continue;
                }
                if ($filterItem['field_name'] === 'category') {
                    continue;
                }
                $filters[] = $filterItem;
            }
        }
        return $filters;
    }

    private function getUpdateFilters($searchParams)
    {
        // Формирую основу для параметра filter_by
        $filterBy = $this->buildFilterBy();

        // Для простоты еще раз добавил в перезаписаную переменную фильтрования родителя
        if (!empty((int)$this->inputConfig['parent'])) {
            $filterBy[] = 'category:=' . $this->inputConfig['parent'];
        }

        $searchParams['filter_by'] = implode('&&', $filterBy);
        // Получаю все фильтры по текущей комбинации уточняющих параметров
        $basicFilters = $this->getFilterWithParams($searchParams);

        //  Улучшение для отмеченных секций.
        $basicFilters = $this->refineFilter($basicFilters);

//        // Объединяю общий фильтр, с отсортированным фильтром, чтобы обновить только подсказки
        return $this->prepareFilters($basicFilters);
    }

    private function refineFilter($basicFilters)
    {
        $filterByKeys = $this->getFilterByKeys();

        foreach ($filterByKeys as $filterKey => $values) {
            $filterBy = $this->buildFilterBy();
            foreach ($filterBy as $key => $filterByItem) {
                $firstPos = stripos($filterByItem, $filterKey);
                if ($firstPos !== false) {
                    unset($filterBy[$key]);
                }
            }
            if (!empty((int)$this->inputConfig['parent'])) {
                $filterBy[] = 'category:=' . $this->inputConfig['parent'];
            }

            $searchParams['q'] = '*';
            $searchParams['filter_by'] = implode('&&', $filterBy);
            $searchParams['facet_by'] = [$filterKey . '(sort_by: _alpha:asc)'];


            // Получаю фильтр с примененными параметрами
            $filtersWithParams = $this->getFilterWithParams($searchParams);

            foreach ($basicFilters as $k => $basicFilter) {
                if ($basicFilter['field_name'] === $filterKey) {
                    $basicFilters[$k] = $filtersWithParams[0];
                }
            }
        }

        return $basicFilters;
    }

    private function buildFilterBy()
    {
        $facetedFields = $this->typesense->collection->getFacetFields();

        $filterBy = [];
        foreach ($this->inputConfig['filters'] as $key => $value) {
            if (empty($value)) {
                continue;
            }
            if (in_array($key, $facetedFields)) {
                $data = [];
                $str = '[';
                if (is_array($value)) {
                    foreach ($value as $v) {
                        if (empty($v)) {
                            continue;
                        }
                        $v = trim($v);
                        //TODO все цифровые поля выбрать как то
                        if ($key === 'price') {
                            $tmp = [];
                            //  Такой разделитель где-то в старых подборках остался
                            if (strpos($v, '/')) {
                                $tmp = explode('/', $v);
                            }
                            if (strpos($v, '|')) {
                                $tmp = explode('|', $v);
                            }
                            if (!empty($tmp)) {
                                $min = $tmp[0];
                                $max = $tmp[1];
                                $data[] = "{$min}..{$max}";
                            } else {
                                $this->modx->log(1, 'Ошибка разбора поля price ' . $v);
                            }
                            //Диапазоны цифр пишутся так price:[10..100]
                        } else {
                            if (is_numeric($v)) {
                                switch ($key) {
                                    case 'favorite':
                                    case 'new':
                                        $data[] = 'true';
                                        break;
                                    default:
                                        $data[] = $v;
                                }
                            } else {
                                // Для фраз нужно обернуть фразу в ` `
                                $data[] = '`' . $v . '`';
                            }
                        }
                    }
                }

                $str .= implode(',', $data);
                $str .= ']';
                $filterBy[] = $key . ':=' . $str;
            }
        }

        if (!empty($this->inputConfig['uniques'])) {
            foreach ($this->inputConfig['uniques'] as $key => $value) {
                if (in_array($key, $facetedFields)) {
                    switch ($key) {
                        case 'sale':
                            $filterBy[] = $key . ':>0';
                            break;
                        default:
                            $filterBy[] = $key . ':=' . (!empty($value) ? 'true' : 'false');
                    }
                }
            }
        }

        if (!empty($_SESSION['tags']) && !empty((int)$_SESSION['tags']['id'])) {
            $filterBy[] = 'tag_pages:=' . $_SESSION['tags']['id'];
        }

        if (!empty($_SESSION['brand'])) {
            $filterBy[] = 'brand_page:=' . (int)$_SESSION['brand']['id'];
        }
        return $filterBy;
    }

    private function getFilterByKeys()
    {
        $facetedFields = $this->typesense->collection->getFacetFields();

        $filterBy = [];
        foreach ($this->inputConfig['filters'] as $key => $value) {
            if (in_array($key, $facetedFields)) {
                $filterBy[$key] = $value;
            }
        }

        if (!empty($this->inputConfig['uniques'])) {
            foreach ($this->inputConfig['uniques'] as $key => $value) {
                if (in_array($key, $facetedFields)) {
                    $filterBy[$key] = $value;
                }
            }
        }
        return $filterBy;
    }


    private function getFilterWithParams($searchParams)
    {
        $response = $this->typesense->search->search($searchParams);
        $filters = [];
        if (!empty($response['facet_counts'])) {
            foreach ($response['facet_counts'] as $filterItem) {
                $filters[] = $filterItem;
            }
        }
        return $filters;
    }

    private function prepareFilters($basicFilters)
    {
        $output = [];
        $filters = [];
        $uniques = [];
        foreach ($basicFilters as $basicItem) {
            switch ($basicItem['field_name']) {
                case 'sale':
                case 'availability':
                case 'onorder':
                case 'subscribe':
                case 'bomb':
                    if (empty($basicItem['counts'])) {
                        break;
                    }

                    if (!empty($this->inputConfig['uniques_array'])) {
                        foreach ($this->inputConfig['uniques_array'] as $item) {
                            if ($item['key'] === $basicItem['field_name']) {
                                $tmp = [];
                                $tmp['is_unique'] = true;
                                $tmp['field_name'] = $basicItem['field_name'];
                                $tmp['checkedGroup'] = $item['checked'];
                                $tmp['disabled'] = false;
                                $tmp['title'] = $item['title'];
                                $tmp['icon'] = $item['icon'];
                                $tmp['stats'] = $basicItem['stats']['total_values'];
                                $tmp['counts'] = $basicItem['counts'];
                                $uniques[] = $tmp;
                            }
                        }
                    }
                    break;
                default:
                    $basicItem['checkedGroup'] = $this->checkedGroup($basicItem['field_name']);
                    if (empty($basicItem['counts'])) {
                        break;
                    }

                    $basicItem = $this->updateCounts($basicItem);
                    $basicItem['title'] = $this->translate[$basicItem['field_name']] ?? $basicItem['field_name'];
                    $filters[] = $basicItem;
            }
        }

        $output['filters'] = $filters;
        $output['uniques'] = $uniques;
        return $output;
    }

    private function updateCounts($basicItem)
    {

        $name = $basicItem['field_name'];
        $basicGroup = $basicItem['counts'];
        foreach ($basicGroup as $key => $checkbox) {
            $checkbox['checked'] = $this->checkedCheckbox($name, $checkbox);
            if ($basicItem['checkedGroup']) {
                if (!$checkbox['checked']) {
                    $checkbox['count'] = '+' . $checkbox['count'];
                } else {
                    $checkbox['count'] = '';
                }
            }

            $basicGroup[$key] = $checkbox;
        }
        $basicItem['counts'] = $basicGroup;


        return $basicItem;
    }

    private function checkedGroup($name)
    {
        $filters = $this->inputConfig['filters'];
        return !empty($filters[$name]);
    }

    private function checkedCheckbox($name, $checkbox)
    {
        $filters = $this->inputConfig['filters'];
        if (empty($filters[$name])) {
            return false;
        }

        if (in_array($checkbox['value'], $filters[$name])) {
            return true;
        }

        return false;
    }

    private function searchWithParams($searchParams)
    {
        if (!isset($searchParams['per_page'])) {
            $searchParams['per_page'] = 24;
        }
        $response = $this->typesense->search->search($searchParams);

        if (!empty($response['message'])) {
            $this->modx->log(1, $response['message']);
            $this->modx->log(1, print_r($searchParams, 1));
        }
        $ids = [];
        if ($response['found'] > 0) {
            foreach ($response['hits'] as $document) {
                $ids[] = $document['document']['id'];
            }
        }

        return ['ids' => $ids, 'found' => $response['found']];
    }

    public function buildModernPagination($page = 1, $pages = 5, $url = '')
    {
        $pageLimit = $this->inputConfig['pageLimit'];
        $url = $this->inputConfig['uri'];
        $page = $this->inputConfig['page'];
        $tplPageActive = $this->inputConfig['tplPageActive'];
        $tplPage = $this->inputConfig['tplPage'];
        $tplPageSkip = $this->inputConfig['tplPageSkip'];

        if ($pageLimit >= $pages || $pageLimit < 7) {
            return $this->buildClassicPagination($page, $pages, $url);
        } else {
            $tmp = (int)floor($pageLimit / 3);
            $left = $right = $tmp;
            $center = $pageLimit - ($tmp * 2);
        }

        $pagination = [];
        // Left
        for ($i = 1; $i <= $left; $i++) {
            if ($page == $i && !empty($tplPageActive)) {
                $tpl = $tplPageActive;
            } elseif (!empty($tplPage)) {
                $tpl = $tplPage;
            }
            $pagination[$i] = !empty($tpl)
                ? $this->makePageLink($url, $i, $tpl)
                : '';
        }

        // Right
        for ($i = $pages - $right + 1; $i <= $pages; $i++) {
            if ($page == $i && !empty($tplPageActive)) {
                $tpl = $tplPageActive;
            } elseif (!empty($tplPage)) {
                $tpl = $tplPage;
            }
            $pagination[$i] = !empty($tpl)
                ? $this->makePageLink($url, $i, $tpl)
                : '';
        }

        // Center
        if ($page <= $left) {
            $i = $left + 1;
            while ($i <= $center + $left) {
                if ($i == $center + $left && !empty($tplPageSkip)) {
                    $tpl = $tplPageSkip;
                } else {
                    $tpl = $tplPage;
                }

                $pagination[$i] = !empty($tpl)
                    ? $this->makePageLink($url, $i, $tpl)
                    : '';
                $i++;
            }
        } elseif ($page > $pages - $right) {
            $i = $pages - $right - $center + 1;
            while ($i <= $pages - $right) {
                if ($i == $pages - $right - $center + 1 && !empty($tplPageSkip)) {
                    $tpl = $tplPageSkip;
                } else {
                    $tpl = $tplPage;
                }

                $pagination[$i] = !empty($tpl)
                    ? $this->makePageLink($url, $i, $tpl)
                    : '';
                $i++;
            }
        } else {
            if ($page - $center < $left) {
                $i = $left + 1;
                while ($i <= $center + $left) {
                    if ($page == $i && !empty($tplPageActive)) {
                        $tpl = $tplPageActive;
                    } elseif (!empty($tplPage)) {
                        $tpl = $tplPage;
                    }
                    $pagination[$i] = !empty($tpl)
                        ? $this->makePageLink($url, $i, $tpl)
                        : '';
                    $i++;
                }
                if (!empty($tplPageSkip)) {
                    $key = ($page + 1 == $left + $center)
                        ? $pages - $right + 1
                        : $left + $center;
                    $pagination[$key] = $this->pdoTools->getChunk($tplPageSkip);
                }
            } elseif ($page + $center - 1 > $pages - $right) {
                $i = $pages - $right - $center + 1;
                while ($i <= $pages - $right) {
                    if ($page == $i && !empty($tplPageActive)) {
                        $tpl = $tplPageActive;
                    } elseif (!empty($tplPage)) {
                        $tpl = $tplPage;
                    }
                    $pagination[$i] = !empty($tpl)
                        ? $this->makePageLink($url, $i, $tpl)
                        : '';
                    $i++;
                }
                if (!empty($tplPageSkip)) {
                    $key = ($page - 1 == $pages - $right - $center + 1)
                        ? $left
                        : $pages - $right - $center + 1;
                    $pagination[$key] = $this->pdoTools->getChunk($tplPageSkip);
                }
            } else {
                $tmp = (int)floor(($center - 1) / 2);
                $i = $page - $tmp;
                while ($i < $page - $tmp + $center) {
                    if ($page == $i && !empty($tplPageActive)) {
                        $tpl = $tplPageActive;
                    } elseif (!empty($tplPage)) {
                        $tpl = $tplPage;
                    }
                    $pagination[$i] = !empty($tpl)
                        ? $this->makePageLink($url, $i, $tpl)
                        : '';
                    $i++;
                }
                if (!empty($tplPageSkip)) {
                    $pagination[$left] = $pagination[$pages - $right + 1] = $this->pdoTools->getChunk($tplPageSkip);
                }
            }
        }

        ksort($pagination);

        return implode($pagination);
    }

    public function buildClassicPagination($page = 1, $pages = 5, $url = '')
    {
        $pageLimit = $this->inputConfig['pageLimit'];

        if ($pageLimit > $pages) {
            $pageLimit = 0;
        } else {
            // -1 because we need to show current page
            $tmp = (int)floor(($pageLimit - 1) / 2);
            $left = $tmp;                        // Pages from left
            $right = $pageLimit - $left - 1;    // Pages from right

            if ($page - 1 == 0) {
                $right += $left;
                $left = 0;
            } elseif ($page - 1 < $left) {
                $tmp = $left - ($page - 1);
                $left -= $tmp;
                $right += $tmp;
            } elseif ($pages - $page == 0) {
                $left += $right;
                $right = 0;
            } elseif ($pages - $page < $right) {
                $tmp = $right - ($pages - $page);
                $right -= $tmp;
                $left += $tmp;
            }

            $i = $page - $left;
            $pageLimit = $page + $right;
        }

        if (empty($i)) {
            $i = 1;
        }
        $pagination = '';
        while ($i <= $pages) {
            if (!empty($pageLimit) && $i > $pageLimit) {
                break;
            }

            $tplPageActive = $this->inputConfig['tplPageActive'];
            $tplPage = $this->inputConfig['tplPage'];

            if ($page == $i) {
                $tpl = $tplPageActive;
            } else {
                $tpl = $tplPage;
            }

            $pagination .= $this->makePageLink($url, $i, $tpl);

            $i++;
        }

        return $pagination;
    }

    public function makePageLink($url = '', $page = 1, $tpl = '')
    {
        $tmp = explode('?', $url);
        $url = $tmp[0];
        $href = $url;
        //TODO сюда нужно будет предусмотерть прочие параметры запроса.  query, sort

        $request = [];
        if (!empty($this->inputConfig['filters'])) {
            $request = $this->inputConfig['filters'];
        }
        if (!empty($this->inputConfig['uniques'])) {
            $request = array_merge($request, $this->inputConfig['uniques']);
        }
        if (!empty($this->inputConfig['sort'])) {
            $request['sort'] = $this->inputConfig['sort'];
        }

        if (!empty($request)) {
            foreach ($request as $key => $values) {
                if (is_array($values)) {
                    array_walk_recursive($values, function (&$item) {
                        $item = rawurldecode($item);
                    });
                    $request[$key] = implode('||', $values);
                } else {
                    $values = rawurldecode($values);
                    $request[$key] = $values;
                }
            }

            $request['page'] = $page;

            $href .= strpos($href, '?') !== false
                ? '&'
                : '?';
            $href .= http_build_query($request);
        } else {
            $href .= '?page=' . $page;
        }

        $data = array(
            'page' => $page,
            'pageNo' => $page,
            'href' => $href,
        );

        return !empty($tpl)
            ? $this->pdoTools->getChunk($tpl, $data)
            : $href;
    }

    private function makeSortUrl($direction)
    {
        $query = [];
        if (!empty($this->inputConfig['filters'])) {
            $query = $this->inputConfig['filters'];
        }
        if (!empty($this->inputConfig['uniques'])) {
            $query = array_merge($this->inputConfig['filters'], $this->inputConfig['uniques']);
        }

        $queryParams = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                $value = implode('||', $value);
            }
            $queryParams[$key] = $value;
        }

        $queryParams['sort'] = $direction;
        return http_build_query($queryParams);
    }
}
