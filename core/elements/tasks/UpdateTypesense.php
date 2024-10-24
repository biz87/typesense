<?php

/** @var modX $modx */

/** @var sFileTask $task */
/** @var sTaskRun $run */
/** @var array $scriptProperties */
/** @var TypeSense $typesense */
$typesense = $modx->getService('typesense', 'TypeSense', MODX_CORE_PATH . 'components/typesense/');
/** @var pdoTools $pdoTools */
$pdoTools = $modx->getService('pdoTools');
$pdoTools->addTime('start');

$result = $typesense->document->updateOrCreate();

$pdoTools->addTime('finish createData');
return 'Обновлено записей: ' . $result['update'] . PHP_EOL .
    'Добавлено записей: ' . $result['create'] . PHP_EOL .
    'Ошибок обновления: ' . $result['errors'] . PHP_EOL .
    'Время: ' . $pdoTools->getTime();
