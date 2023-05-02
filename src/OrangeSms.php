<?php

namespace Ngomory;


class OrangeSms
{

    private string $_endpoint = 'https://api.orange.com';
    private string $_sender_address = 'tel:+2250000';
    private string $_sender_name = '';
    private string $_regex_phone = '/^225[0-9]{10}$/i';

    private string $_token_basic;
    private string $_token_bearer;

    private string $recipient;
    private string $msg;

    public function __construct(string $token_basic)
    {

        if (empty($token_basic)) {
            throw new \Exception('OrangeSms : Authorization Basic is missing or invalide.');
        }

        $this->_token_basic = $token_basic;

        $this->_setAuth();
    }

    private function _setAuth()
    {

        $headers = [
            'Authorization' => $this->_token_basic,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        $params = ['grant_type' => 'client_credentials'];

        $response = $this->_curl('POST', '/oauth/v3/token', $params, $headers, 'http_build_query');

        if (!isset($response['access_token'])) {
            throw new \Exception('OrangeSms : Authentification fail.');
        }

        $this->_token_bearer = 'Bearer ' . $response['access_token'];
    }

    public function setEndpoint(string $enpoint)
    {
        if (!filter_var($enpoint, FILTER_VALIDATE_URL)) {
            throw new \Exception('OrangeSms : Invalid endpoint URL.');
        }
        $this->_endpoint = $enpoint;
    }

    public function setSenderAddress(string $sender_address)
    {
        $this->_sender_address = $sender_address;
    }

    public function setSenderName(string $sender_name)
    {
        $this->_sender_name = $sender_name;
    }

    public function setRegexPhone(string $regex)
    {
        $this->_regex_phone = $regex;
    }

    public function send(string $recipient, string $msg): array
    {

        preg_match($this->_regex_phone, $recipient, $matches);
        if (empty($matches)) {
            throw new \Exception('OrangeSms : Recipient is missing or invalide.');
        }
        $this->recipient = 'tel:+' . $recipient;
        $this->msg = $msg;

        $url = '/smsmessaging/v1/outbound/' . $this->_sender_address . '/requests';
        $headers = [
            'Authorization' => $this->_token_bearer,
            'Content-Type' => 'application/json',
        ];
        $params = [
            'outboundSMSMessageRequest' => [
                'address' => $this->recipient,
                'senderAddress' => $this->_sender_address,
                'outboundSMSTextMessage' => [
                    'message' => $this->msg
                ]
            ]
        ];

        if (!empty($this->_sender_name)) {
            $params['outboundSMSMessageRequest']['senderName'] = $this->_sender_name;
        }

        $response = $this->_curl('POST', $url, $params, $headers);

        return $response;
    }

    private function _curl(string $method = 'POST', string $url, array $params = [], array $headers = []): array
    {

        $url = $this->_endpoint . $url;

        $type = $headers['Content-Type'] ?? '';
        switch ($type) {
            case 'application/json':
                $params = json_encode($params);
                break;
            case 'application/x-www-form-urlencoded':
                $params = http_build_query($params);
                break;
        }

        $headerFields = [];
        $headers = array_merge($headers);
        foreach ($headers as $key => $value) {
            $headerFields[] = $key . ': ' . $value;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => $headerFields,
        ));

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            throw new \Exception('OrangeSms :  ' . $error);
        }

        curl_close($curl);

        return json_decode($response, true);
    }
}
