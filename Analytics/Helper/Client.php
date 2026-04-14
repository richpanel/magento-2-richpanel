<?php

namespace Richpanel\Analytics\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Exception;

/**
 * Helper for sending requests to Richpanel end points
 *
 * @author Shubhanshu Chouhan <shubhanshu@richpanel.com>
 */
class Client extends AbstractHelper
{

    /**
    * Create HTTP POST request to URL
    *
    * @param string $url
    * @param array|false $bodyArray
    * @return array{response: ?string, code: int}
    */
    public function post(string $url, $bodyArray = false): array
    {
        if (empty($url)) {
            return ['response' => null, 'code' => 0];
        }

        $encodedBody = '';
        if ($bodyArray) {
            $encodedBody = json_encode($bodyArray);
            if ($encodedBody === false) {
                return ['response' => null, 'code' => 0];
            }
        }

        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['host'])) {
            return ['response' => null, 'code' => 0];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: */*',
            'User-Agent: HttpClient/1.0.0',
            'Connection: Close',
            'Host: ' . $parsedUrl['host']
        ];

        return $this->curlCall($url, $headers, $encodedBody);
    }


    /**
    * CURL call
    *
    * @param string $url
    * @param array $headers
	* @param string $body
    * @param string $method
    * @return array{response: ?string, code: int}
    */
	public function curlCall(string $url, array $headers = [], string $body = '', string $method = "POST"): array
	{
        if (empty($url)) {
            return ['response' => null, 'code' => 0];
        }

        $curl = curl_init();
        if ($curl === false) {
            return ['response' => null, 'code' => 0];
        }

        try {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_COOKIESESSION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 2000);
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, 3000);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

            $response = curl_exec($curl);
            if ($response === false) {
                $response = null;
            }
            
            $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            return [
                'response' => $response,
                'code' => (int)$code
            ];
        } catch (Exception $e) {
            if (isset($curl) && ($curl instanceof \CurlHandle || is_resource($curl))) {
                curl_close($curl);
            }
            return [
                'response' => null,
                'code' => 0
            ];
        }
    }
}
