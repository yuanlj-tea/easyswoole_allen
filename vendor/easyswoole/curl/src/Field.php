<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/24
 * Time: 下午3:26
 */

namespace EasySwoole\Curl;


class Field
{
    private $name;
    private $val;

    function __construct($key = null,$val = null)
    {
        $this->name = $key;
        $this->val = $val;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getVal()
    {
        return $this->val;
    }

    /**
     * @param mixed $val
     */
    public function setVal($val)
    {
        $this->val = $val;
    }

    public function toArray():array
    {
        return array(
            $this->name => $this->val
        );
    }
}