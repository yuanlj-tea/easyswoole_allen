<?php


namespace App\HttpController;


use App\HttpController\Pool\MysqlInvoke;
use App\Utility\Pool\MysqlPool;
use EasySwoole\EasySwoole\Config;

class Stream extends AbstractController
{
    /**
     * @var string 令牌保存目录
     */
    private $_tokenPath;

    /**
     * 上传文件保存目录
     * @var string
     */
    private $_filePath;

    /**
     * 允许上传的文件类型
     * @var
     */
    private $allowExt;

    function index()
    {

    }

    public function __construct()
    {
        parent::__construct();
        $this->allowExt = get_upload_file_type_conf();
        $this->_tokenPath = Config::getInstance()->getConf('UPLOAD_FILE_PATH') . '/app/game/tokens/';
        $this->_filePath = Config::getInstance()->getConf('UPLOAD_FILE_PATH') . '/app/game/files/';
    }

    public function tk()
    {
        $request = $this->request();
        $response = $this->response();
        $file['name'] = pathinfo($request->getRequestParam('name'), PATHINFO_BASENAME);                  //上传文件名称
        $file['size'] = $request->getRequestParam('size');                  //上传文件总大小
        $file['token'] = md5(json_encode($file['name'] . $file['size']));

        //限制上传文件类型
        $ext = substr($file['name'], strrpos($file['name'], '.') + 1);
        if (!in_array($ext, $this->allowExt)) {
            return $response->write(err('上传文件类型限制'));
        }

        //判断是否存在该令牌信息
        if (!file_exists($this->_tokenPath . $file['token'] . '.token')) {
            $file['up_size'] = 0;                       //已上传文件大小
            $pathInfo = pathinfo($file['name']);
            $servName = sprintf("%s_%s.%s", time(), gen_uid(), $ext); //保存到服务的文件名
            $path = $this->_filePath . date('Ymd') . '/';
            //生成文件保存子目录
            if (!is_dir($path)) {
                $oldumask = umask(0);
                mkdir($path, 0777, true);
                @chmod($path, 0777);
                umask($oldumask);
            }
            //上传文件保存目录
            $file['filePath'] = $path . $servName;
            $file['modified'] = $request->getRequestParam('modified');      //上传文件的修改日期

            //保存令牌信息
            $this->setTokenInfo($file['token'], $file);
        }
        $result['token'] = $file['token'];
        $result['success'] = true;

        return $response->write(json_encode($result));
    }

    /**
     * 上传接口
     */
    public function up()
    {
        $request = $this->request();
        $response = $this->response();

        $client = $request->getRequestParam('client');
        $name = pathinfo($request->getRequestParam('name'), PATHINFO_BASENAME);    //上传的文件名

        if ('html5' == $client) {
            $this->html5Upload($name);
        } else {
            return $response->write(err('无效的参数:client'));
        }

    }

    /**
     * HTML5上传
     * @param string $name 客户端上传的文件名
     */
    private function html5Upload($name)
    {
        $request = $this->request();
        $response = $this->response();

        $token = $request->getRequestParam('token');
        $fileInfo = $this->getTokenInfo($token);

        if ($fileInfo['size'] > $fileInfo['up_size']) {
            //取得上传内容
            // $data = file_get_contents('php://input', 'r');
            $data = $request->getBody()->__toString();
            if (!empty($data)) {
                //上传内容写入目标文件
                $fp = fopen($fileInfo['filePath'], 'a');
                flock($fp, LOCK_EX);
                fwrite($fp, $data);
                flock($fp, LOCK_UN);
                fclose($fp);
                //累积增加已上传文件大小
                $fileInfo['up_size'] += strlen($data);
                if ($fileInfo['size'] > $fileInfo['up_size']) {
                    $this->setTokenInfo($token, $fileInfo);
                } else {
                    //保存上传日志
                    $logData = [
                        'name_key' => pathinfo($fileInfo['filePath'], PATHINFO_BASENAME),
                        'name_value' => $name,
                        'op_date' => date('Y-m-d H:i:s'),
                        'serv_path' => $fileInfo['filePath'],
                    ];
                    $db = MysqlPool::defer();
                    $db->insert('upload_log' , $logData);

                    //上传完成后删除令牌信息
                    @unlink($this->_tokenPath . $token . '.token');
                }

            }
        }
        $result['start'] = $fileInfo['up_size'];
        $result['success'] = true;

        return $response->write(json_encode($result));
    }

    /**
     * 生成文件内容
     */
    protected function setTokenInfo($token, $data)
    {
        if (!file_exists($this->_tokenPath)) {
            $oldumask = umask(0);
            mkdir($this->_tokenPath, 0777, true);
            @chmod($this->_tokenPath, 0777);
            umask($oldumask);
        }
        file_put_contents($this->_tokenPath . $token . '.token', json_encode($data));
    }

    /**
     * 获取文件内容
     */
    protected function getTokenInfo($token)
    {
        $file = $this->_tokenPath . $token . '.token';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }
        return false;
    }

}