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

    /**
     * __construct
     *
     * @param string $token_basic Authorization header provided by orange
     */
    public function __construct(string $token_basic)
    {

        if (empty($token_basic)) {
            throw new \Exception('OrangeSms : Authorization Basic is missing or invalide.');
        }

        $this->_token_basic = $token_basic;

        $this->_oauth();
    }

    /**
     * _oauth for authentication
     *  
     * @return void
     */
    private function _oauth(): void
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

    /**
     * setEndpoint
     *
     * @param string $enpoint New enpoint url
     * @return OrangeSms
     */
    public function setEndpoint(string $enpoint): OrangeSms
    {
        if (!filter_var($enpoint, FILTER_VALIDATE_URL)) {
            throw new \Exception('OrangeSms : Invalid endpoint URL.');
        }
        $this->_endpoint = $enpoint;

        return $this;
    }

    /**
     * setSenderAddress
     *
     * @param string $sender_address Set new sender address
     * @return OrangeSms
     */
    public function setSenderAddress(string $sender_address): OrangeSms
    {
        $this->_sender_address = $sender_address;

        return $this;
    }

    /**
     * setSenderName
     *
     * @param string $sender_name new sender name
     * @return OrangeSms
     */
    public function setSenderName(string $sender_name): OrangeSms
    {
        $this->_sender_name = $sender_name;
        return $this;
    }

    /**
     * setRegexPhone 
     * 
     * @param string $regex Recipient number validation regex
     * @return OrangeSms
     */
    public function setRegexPhone(string $regex): OrangeSms
    {
        $this->_regex_phone = $regex;
        return $this;
    }

    /**
     * send Send new message
     *
     * @param string $recipient Recipient number
     * @param string $msg You message
     * @param string $sender_name Sender name
     * @return array
     */
    public function send(string $recipient, string $msg, string $sender_name = ''): array
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

        if (!empty($sender_name)) {
            $this->_sender_name = $sender_name;
        }

        if (!empty($this->_sender_name)) {
            $params['outboundSMSMessageRequest']['senderName'] = $this->_sender_name;
        }

        $response = $this->_curl('POST', $url, $params, $headers);

        return $response;
    }

    /**
     * _curl For call orange API
     *
     * @param string $method
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return array
     */
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
