<?php

$loader = require __DIR__.'/../../../vendor/autoload.php';

$loader->addPsr4('Lalalili\\CommerceCore\\', __DIR__.'/../src/');
$loader->addPsr4('Lalalili\\CommerceCore\\Tests\\', __DIR__.'/');

require __DIR__.'/Pest.php';

return $loader;
