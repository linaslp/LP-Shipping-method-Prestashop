<?php


class LPShippingBaseRequest
{
    /**
     * cURL handle
     */
    private $curlHandle;

    /**
     * array of default headers
     */
    private $defaultHeaders = [
        'charset: utf-8',
        'Content-Type: application/json',
        'Accept: */*',
        'Cache-Control' => 'no-cache',
    ];

    /**
     * Create cURL handle object on class object creation
     */
    public function __construct()
    {
        $this->curlHandle = curl_init();
    }

    /**
     * Set headers of this request
     *
     * @param array $headers
     */
    protected function setHeaders(array $headers)
    {
        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, array_merge($this->defaultHeaders, $headers));
    }

    /**
     * Set cURL options
     *
     * @param array $options
     */
    protected function setOptions(array $options)
    {
        curl_setopt_array($this->curlHandle, $options);
    }

    /**
     * Get result of the request and close cURL instance
     *
     * @return any
     */
    protected function executeCallAndGetResult()
    {
        $res = curl_exec($this->curlHandle);
        if (curl_errno($this->curlHandle)) {
            return 'Error: ' . curl_error($this->curlHandle);
        }

        $statusCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
        if ((int) $statusCode != 200) {
            return [
                'success' => false,
                'status_code' => $statusCode,
                'message' => $res
            ];
        }

        curl_close($this->curlHandle);

        return $res;
    }

    /**
     * Set handle as new
     */
    protected function refreshCurl()
    {
        $this->curlHandle = curl_init();
    }
}
