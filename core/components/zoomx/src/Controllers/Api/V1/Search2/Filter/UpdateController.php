<?php

namespace Zoomx\Controllers\Api\V1\Search2\Filter;

use Zoomx\Controllers\Controller;

class UpdateController extends Controller
{
    /** @var \TypeSense $typesense */
    protected $typesense;
    /**
     * @var \pdoTools $pdoTools
     */
    protected $pdoTools;

    public function __construct(\modX $modx)
    {
        parent::__construct($modx);
        $this->typesense = $this->modx->getService('typesense', 'TypeSense', MODX_CORE_PATH . 'components/typesense/');
        $this->pdoTools = $this->modx->getService('pdoTools');
    }

    public function index()
    {
        $this->pdoTools->addTime('start api query');
        $rawFilters = json_decode($_POST['filters'], 1);
        $rawUniques = json_decode($_POST['uniques'], 1);
        $urlComponents = parse_url($_SERVER['HTTP_REFERER']);
        if (isset($_SESSION['tags'])) {
            unset($_SESSION['tags']);
        }
        if (isset($_SESSION['brand'])) {
            unset($_SESSION['brand']);
        }
        if (isset($_SESSION['ts_query'])) {
            unset($_SESSION['ts_query']);
        }
        if (isset($_SESSION['ts_favorite'])) {
            unset($_SESSION['ts_favorite']);
        }

        $initParams = [];
        $parent = (int)$_POST['parent'];

        // Проверка на признак тэгированной страницы
        $tmp = explode('/', $urlComponents['path']);
        if (in_array('tags', $tmp)) {
            $tag = $tmp[count($tmp) - 1];
            $this->modx->addPackage(
                'skarbcabinet',
                $this->modx->getOption('core_path') . 'components/skarbcabinet/model/'
            );

            $q = $this->modx->newQuery('skarbTagsPages');
            $q->select(
                'id, category, banner,banner_mobile,banner_link'
            );
            $q->where([
                'alias' => $tag,
                'active' => 1
            ]);
            $tagItem = ($q->prepare() && $q->stmt->execute()) ? $q->stmt->fetch(\PDO::FETCH_ASSOC) : [];
            if (!empty($tagItem)) {
                $_SESSION['tags']['parent'] = $tagItem['category'];
                $_SESSION['tags']['id'] = $tagItem['id'];
                if (!empty($tagItem['banner']) && !empty($tagItem['banner_mobile'])) {
                    $_SESSION['tags']['banner'] = $tagItem['banner'];
                    $_SESSION['tags']['banner_mobile'] = $tagItem['banner_mobile'];
                    $_SESSION['tags']['banner_link'] = $tagItem['banner_link'];
                }
            }
        }

        if (in_array('brands', $tmp)) {
            $resource_id = $this->modx->findResource(ltrim($urlComponents['path'], '/'), 'web');
            if (!empty($resource_id)) {
                $_SESSION['brand'] = [
                    'id' => $resource_id,
                ];
            }
        }

        if (in_array('search', $tmp) && !empty($_POST['query'])) {
            $query = filter_input(INPUT_POST, 'query', FILTER_SANITIZE_STRING);
            $_SESSION['ts_query'] = $query;
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
            $_SESSION['ts_favorite'] = true;
        }

        $page = !empty($_POST['page']) ? (int)$_POST['page'] : 1;
        $whiteList = [
            'price:asc',
            'price:desc',
            'date:desc',
            'sale:desc',
            'popular:desc'
        ];

        $paginationSort = '';

        if (!empty($_POST['sort']) && in_array($_POST['sort'], $whiteList)) {
            switch ($_POST['sort']) {
                case 'popular:desc':
                    $sort = 'popular_score:desc';
                    break;
                case 'sale:desc':
                    $sort = 'sale:desc';
                    if (
                        $this->modx->user->hasSessionContext('web')
                        && !empty($this->modx->user->Profile->get('partner'))
                    ) {
                        $sort = 'partner_sale:desc';
                    }
                    break;
                default:
                    $sort = $_POST['sort'];
            }

            $sortBy = 'availability:desc,' . $sort;
            $paginationSort = $_POST['sort'];
        } else {
            $sortBy = 'available_position:desc,popular_score:desc';
        }
        $query = '*';

        $initParams['parent'] = $parent;
        if (!empty($_SESSION['tags'])) {
            // Это у нас тэгированная страница, тут родитель назначен отдельно
            $initParams['parent'] = $_SESSION['tags']['parent'];
        }

        if (!empty($_SESSION['brand'])) {
            $initParams['parent'] = 922;
        }
        if (!empty($_SESSION['ts_query'])) {
            $query = $_SESSION['ts_query'];
            $initParams['parent'] = 922;
        }

        $initParams['page'] = $page;
        $initParams['sort'] = $paginationSort;
        $initParams['sort_by'] = $sortBy;
        $initParams['query'] = $query;

        $initParams['uri'] = $urlComponents['path'];

        $initParams['per_page'] = 24;
        $initParams['pageLimit'] = 10;

        $filters = [];
        foreach ($rawFilters as $key => $value) {
            switch ($key) {
                case 'q':
                case 'popular':
                    break;
                default:
                    $filters[$key] = $value;
            }
        }
        $initParams['filters'] = $filters;
        $initParams['uniques'] = $rawUniques;

        // Добавляю уникальные акционные чекбоксы

        // Этот показываю только на странице new
        $unique = [];
        $unique['key'] = 'sale';
        $unique['checked'] = !empty($rawUniques['sale']);
        $unique['disabled'] = in_array('sale', $tmp);
        $unique['title'] = 'На акции';
        $unique['field_name'] = 'sale';
        $unique['icon'] = '/assets/template/img/labels_2/sale_2.png';
        $uniques[] = $unique;

        if (!empty($rawUniques['new'])) {
            $unique = [];
            $unique['key'] = 'new';
            $unique['checked'] = true;
            $unique['disabled'] = true;
            $unique['title'] = 'Новинки';
            $unique['field_name'] = 'new';
            $unique['icon'] = '/assets/template/img/labels_2/new.png';
            $uniques[] = $unique;
        }

        if (!empty($rawUniques['favorite'])) {
            $unique = [];
            $unique['key'] = 'favorite';
            $unique['checked'] = true;
            $unique['disabled'] = true;
            $unique['title'] = 'Лучший выбор';
            $unique['field_name'] = 'favorite';
            $unique['icon'] = '/assets/template/img/labels_2/lychiy_vibor.png';
            $uniques[] = $unique;
        }

        $unique = [];
        $unique['key'] = 'availability';
        $unique['checked'] = !empty($rawUniques['availability']);
        $unique['disabled'] = false;
        $unique['title'] = 'В наличии';
        $unique['field_name'] = 'availability';
        $unique['icon'] = '/assets/template/img/labels_2/v_nalichii.png';
        $uniques[] = $unique;

        $unique = [];
        $unique['key'] = 'onorder';
        $unique['checked'] = !empty($rawUniques['onorder']);
        $unique['disabled'] = false;
        $unique['title'] = 'Под заказ (1-2 дня)';
        $unique['field_name'] = 'onorder';
        $unique['icon'] = '/assets/template/img/labels_2/pod_zakaz.png';
        $uniques[] = $unique;

        $unique = [];
        $unique['key'] = 'subscribe';
        $unique['checked'] = !empty($rawUniques['subscribe']);
        $unique['disabled'] = false;
        $unique['title'] = 'По автоподписке';
        $unique['field_name'] = 'subscribe';
        $unique['icon'] = '/assets/template/img/labels_2/avtopodpiska.png';
        $uniques[] = $unique;

        $unique = [];
        $unique['key'] = 'bomb';
        $unique['checked'] = !empty($rawUniques['bomb']);
        $unique['disabled'] = false;
        $unique['title'] = 'Подарок к заказу';
        $unique['field_name'] = 'bomb';
        $unique['icon'] = '/assets/template/img/labels_2/bomb.png';
        $uniques[] = $unique;


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
        $filterData = $this->modx->cacheManager->get($initParamsHash, $options);

//        if (!empty($filterData)) {
//            $_SESSION['typesense'][$initParamsHash]++;
//            if ($_SESSION['typesense'][$initParamsHash] > 2) {
//                $this->modx->cacheManager->delete($initParamsHash, $options);
//                unset($_SESSION['typesense'][$initParamsHash]);
//            }
//            return jsonx(array_merge(['success' => true], $filterData));
//        }
//        $_SESSION['typesense'][$initParamsHash] = 1;

        // инициирую конфиг для расчета
        $this->typesense->filter->init($initParams);

        //  Получаю массив фильтров с обновленными данными, согласно присланного конфига
        $response = $this->typesense->filter->getFilters();
        $filters = $response['filters'];
        // $uniques = $response['uniques'];
        // Получаю все товары, подходящие под запрос.  Нужны для расчета пагинации, и total данных
        $response = $this->typesense->filter->getProductsByFilter();
        $found = $response['found'];

        // Получаю данные для расчета пагинации
        $paginationData = $this->typesense->filter->getPaginationData($found);


        //TODO  если обновлен хэш фильтров, нужно перейти на page 1
        // Redirect to start if somebody specified incorrect page
        if ($page > 1 && $page > $paginationData['pageCount']) {
            //  return first page
            //  Нужно умудриться сохранить все существующие GET параметры, с заменой page на 1, чтобы сохранить параметры подбора
        }


        // Формирую пагинацию
        $pagination = $this->typesense->filter->buildPagination($paginationData);

        // Получаю окончательный массив товаров, с учетом лимита на страницу и текущей страницы
        $response = $this->typesense->filter->getProductsByFilter($initParams);
        $ids = $response['ids'];

        if (!empty($ids)) {
            $ids = implode(',', $ids);
        } else {
            // Абсурдный id покажет пустой результат. Иначе покажет все ресурсы из parent
            $ids = '99999999';
        }

        $html = $this->pdoTools->runSnippet('msProducts', [
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

        $sort = $this->typesense->filter->getSortItems();
        $selected = $this->typesense->filter->getSelectedItems($found);


        $cacheParams = [
            'filters' => $filters,
            'uniques' => $uniques,
            'html' => $html,
            'pagination' => $pagination,
            'sort' => $sort,
            'selected' => $selected,
            'total' => $found,
        ];

        $this->modx->cacheManager->set($initParamsHash, $cacheParams, 300, $options);

        $this->pdoTools->addTime('finish api query');
        //$this->modx->log(1, $this->pdoTools->getTime());

        return jsonx([
            'success' => true,
            'filters' => $filters,
            'uniques' => $uniques,
            'html' => $html,
            'pagination' => $pagination,
            'sort' => $sort,
            'selected' => $selected,
            'total' => $found,
        ]);
    }
}
