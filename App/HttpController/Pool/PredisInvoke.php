<?php


namespace App\HttpController\Pool;


use App\HttpController\AbstractController;
use App\Utility\Pool\Predis\PredisObject;
use App\Utility\Pool\Predis\PredisPool;

class PredisInvoke extends AbstractController
{
    function index()
    {
        try{
            $result = PredisPool::invoke(function(PredisObject $predis){
                $predis->set('name','bar');
                $val = $predis->get('name');
                pp($val);
                return $val;
            });
            $this->writeJson(200, $result);
        }catch(\Exception $e){

        }
    }

}