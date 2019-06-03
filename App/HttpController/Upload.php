<?php


namespace App\HttpController;


class Upload extends AbstractController
{
    function index()
    {
        // TODO: Implement index() method.
    }

    public function wsUpload()
    {
        $request = $this->request();
        $file = $request->getUploadedFile('img');

        $mimeType = $file->getClientMediaType();
        $image_data = $file->getStream()->read($file->getSize());
        $base64_image = 'data:' . $mimeType . ';base64,' . chunk_split(base64_encode($image_data));
        $data = ['err' => "", 'msg' => ['url' => $base64_image, 'localname' => $file->getClientFilename()]];
        return $this->response()->write(json_encode($data));
    }

    public function wsUploadCallback()
    {
        $request = $this->request();
        $data = $request->getRequestParam('data');
        return $this->response()->write($data);
    }
}