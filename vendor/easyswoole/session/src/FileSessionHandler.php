<?php


namespace EasySwoole\Session;


class FileSessionHandler implements \SessionHandlerInterface
{
    /*
     * 无锁实现
     */
    private $savePath;
    private $name;
    private $sessionId;

    public function close()
    {
        return true;
    }

    public function destroy($session_id)
    {
        $this->sessionId = $session_id;
        $file = $this->file();
        if(file_exists($file)){
            unlink($file);
            return true;
        }else{
            return null;
        }
    }

    public function gc($maxlifetime)
    {
        $dir_handle = openDir($this->savePath);
        while(false !== $file = readDir($dir_handle)) {
            if ($file == '.' || $file == '..') continue;
            $file = "{$this->savePath}/{$file}";
            if(time() - filemtime($file) > $maxlifetime){
                unlink($file);
            }
        }
        closeDir($dir_handle);
    }

    public function open($save_path, $name)
    {
       $this->savePath = $save_path;
       $this->name = $name;
       if(!is_dir($save_path)){
           return mkdir($save_path);
       }
       return true;
    }

    public function read($session_id)
    {
        $this->sessionId = $session_id;
        $file = $this->file();
        if(file_exists($file)){
            return file_get_contents($file);
        }else{
            return null;
        }
    }

    public function write($session_id, $session_data)
    {
        $this->sessionId = $session_id;
        $file = $this->file();
        return (bool)file_put_contents($file,$session_data);
    }

    private function file()
    {
        return "{$this->savePath}/{$this->name}_{$this->sessionId}";
    }
}