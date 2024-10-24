<?php

class Request
{
    private $modx;
    private $apiKey;

    public function __construct(modX $modx)
    {
        $this->apiKey = '8t4LwfSVBSKssXoF7gxVq6UBnFwT68Xtb8bCKFmYjXw3sapJ';
        $this->modx = $modx;
    }

    public function post($path, $data)
    {
        $headers = [
            'Content-Type:application/json',
            'X-TYPESENSE-API-KEY:' . $this->apiKey,
        ];
        $ch = curl_init('http://localhost:8108/' . $path);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, 1);
    }

    public function get($path)
    {
        $headers = [
            'X-TYPESENSE-API-KEY:' . $this->apiKey,
        ];
        $ch = curl_init('http://localhost:8108/' . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, 1);
    }

    public function delete($path)
    {
        $headers = [
            'X-TYPESENSE-API-KEY:' . $this->apiKey,
        ];
        $ch = curl_init('http://localhost:8108/' . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        return json_decode($response, 1);
    }

    public function patch($path, $data)
    {

        $headers = [
            'Content-Type:application/json',
            'X-TYPESENSE-API-KEY:' . $this->apiKey,
        ];
        $ch = curl_init('http://localhost:8108/' . $path);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        return json_decode($response, 1);
    }
}
