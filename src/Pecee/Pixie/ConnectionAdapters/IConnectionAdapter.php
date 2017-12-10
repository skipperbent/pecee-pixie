<?php
namespace Pecee\Pixie\ConnectionAdapters;

interface IConnectionAdapter {

    public function connect($config);

    public function getQueryAdapterClass();

}