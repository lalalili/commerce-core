<?php

$loader = require __DIR__.'/../vendor/autoload.php';

$loader->addPsr4('Lalalili\\CommerceCore\\', __DIR__.'/../src/', true);
$loader->addPsr4('Lalalili\\CommerceCore\\Tests\\', __DIR__.'/', true);

return $loader;
