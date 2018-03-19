<?php

namespace Yaprouter\Yaprouter;

interface HttpRequestFactoryInterface {

    public function factory($method, $uri);

}

