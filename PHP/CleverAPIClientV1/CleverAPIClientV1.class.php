<?php

/*
 * Copyright (c) 2014 CleverCoin <info@clevercoin.com>
 * See the file LICENSE.txt for copying permission.
 */

class CleverAPIException extends Exception {}

/**
 * CleverCoin API reference client. Requirements:
 * - PHP 5.4 or above
 * - cURL PHP extension
 */
class CleverAPIClientV1 {
    // Supported HTTP methods
    const METHOD_DELETE = 'DELETE';
    const METHOD_GET    = 'GET';
    const METHOD_HEAD   = 'HEAD';
    const METHOD_POST   = 'POST';
    const METHOD_PUT    = 'PUT';

    // Public or private API call type
    const TYPE_PRIVATE = 1;
    const TYPE_PUBLIC  = 2;

    /**
     * cURL handle.
     * @var resource
     */
    private $ch;
    /**
     * API key.
     * @var string
     */
    private $key;
    /**
     * API secret.
     * @var string
     */
    private $secret;
    /**
     * API URI.
     * @var string
     */
    private $uri;

    /**
     * Initializes the CleverAPIClient.
     *
     * @param string $key Your API key
     * @param string $secret Your API secret
     * @param string $uri The URI of the API to connect to
     */
    public function __construct($key, $secret, $uri = 'https://api.clevercoin.com') {
        $this->key = (string) $key;
        $this->secret = (string) $secret;
        $this->uri = rtrim($uri, '/');

        // Initialize cURL
        if (!extension_loaded('curl')) {
            throw new CleverAPIException('The cURL PHP extension is required to use this client.');
        }
        $this->ch = curl_init();
        if ($this->ch === false) {
            throw new CleverAPIException('Failed to initialize cURL.');
        }
        curl_setopt_array($this->ch, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => sprintf('%s v1.0', __CLASS__),
        ]);

        // Enable TLS verification and set default timeout
        $this->setCertificateVerification(true);
        $this->setTimeout(10);
    }

    /**
     * Closes the open cURL handle.
     */
    public function __destruct() {
        curl_close($this->ch);
    }

    /**
     * Executes an API call and returns the result.
     *
     * @param integer $type One of the TYPE_* constants
     * @param string $HTTPMethod One of the METHOD_* constants
     * @param string $name The API call name
     * @param string[string] $queryParameters The parameters to add to the query string
     * @param string[string] $bodyParameters The parameters to add to the request body (does not work for METHOD_GET)
     *
     * @return array The API call response
     */
    public function executeCall($type, $HTTPMethod, $name, array $queryParameters = [], array $bodyParameters = []) {
        // Check arguments
        if (!in_array($type, [self::TYPE_PUBLIC, self::TYPE_PRIVATE], true)) {
            throw new CleverAPIException('Call type must be public or private.');
        } else if (($HTTPMethod === self::METHOD_GET) && $bodyParameters) {
            throw new CleverAPIException('You cannot use body parameters with a GET method.');
        }

        // Set URI
        $path = sprintf('/v1/%s', $name);
        if ($queryParameters) {
            $path .= '?' . http_build_query($queryParameters);
        }
        curl_setopt($this->ch, CURLOPT_URL, $this->uri . $path);

        // Private calls require a valid signature
        if ($type === self::TYPE_PRIVATE) {
            // Determine nonce and API headers
            $nonce = explode(' ', microtime());
            $requestHeaders = [
                'X-CleverAPI-Key'   => $this->key,
                'X-CleverAPI-Nonce' => $nonce[1] . str_pad(substr($nonce[0], 2, 6), 6, '0'),
            ];

            // Calculate signature and add the signature header
            $signatureParameters = $requestHeaders + ['X-CleverAPI-Request' => $HTTPMethod . ' ' . $path] + $bodyParameters;
            ksort($signatureParameters);
            $requestHeaders['X-CleverAPI-Signature'] = hash_hmac('sha256', http_build_query($signatureParameters), $this->secret);

            // Set request headers
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array_map(function($k, $v) {
                return $k . ': ' . $v;
            }, array_keys($requestHeaders), array_values($requestHeaders)));
        } else {
            // Public calls do not send additional headers
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, []);
        }

        // Set request method
        if (!in_array($HTTPMethod, [self::METHOD_DELETE, self::METHOD_GET, self::METHOD_HEAD, self::METHOD_POST, self::METHOD_PUT], true)) {
            throw new CleverAPIException('Unsupported HTTP method.');
        }
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $HTTPMethod);

        // Set request body
        if ($bodyParameters) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($bodyParameters));
        } else {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, null);
        }

        // Execute the request
        $result = curl_exec($this->ch);
        if ($result === false) {
            throw new CleverAPIException(sprintf('cURL reported an error #%s: %s.', curl_errno($this->ch), curl_error($this->ch)));
        }
        $HTTPCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        // Return the call reponse
        $json = json_decode($result, true);
        if ($HTTPCode !== 200) {
            $error = (is_array($json) && array_key_exists('error', $json)) ? $json['error'] : $result;
            throw new CleverAPIException(sprintf('API responds with HTTP status %d: %s', $HTTPCode, $error), $HTTPCode);
        } else if (!is_array($json)) {
            throw new CleverAPIException(sprintf('Failed to parse the result as JSON data: %s.', $result));
        }
        return $json;
    }

    /**
     * Enables or disables TLS certificate verification. Never disable verification for production environments!
     *
     * @param boolean $verifiyCertificate True if the TLS certificate should be verified, false if invalid certificates are accepted
     */
    public function setCertificateVerification($verifyCertificate) {
        $verifyCertificate = ($verifyCertificate ? true : false);
        curl_setopt_array($this->ch, [
            CURLOPT_SSL_VERIFYHOST => ($verifyCertificate ? 2 : 0),
            CURLOPT_SSL_VERIFYPEER => $verifyCertificate,
        ]);
    }

    /**
     * Sets the HTTP connection and response timeouts.
     *
     * @param integer $timeout The timeout in seconds
     */
    public function setTimeout($timeout) {
        $timeout = (integer) $timeout;
        curl_setopt_array($this->ch, [
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
    }

    /* --- Public API calls --- */

    /**
     * @return array
     */
    public function getExchangeRateHistory() {
        return $this->executeCall(self::TYPE_PUBLIC, self::METHOD_GET, 'exchangeRateHistory');
    }

    /**
     * Query order book. Example return value:
     * [
     *     'timestamp' => 1411477485,
     *     'bids' => [
     *         [310.06, 0.201606],
     *         [310, 0.17129],
     *     ],
     *     'asks' => [
     *         [316.63, 0.3933],
     *         [316.75, 0.3375],
     *     ],
     * ]
     *
     * @param boolean $group Group orders for the same price
     *
     * @return array
     */
    public function getOrderBook($group) {
        return $this->executeCall(self::TYPE_PUBLIC, self::METHOD_GET, 'orderbook', ['group' => $group ? '1' : '0']);
    }

    /**
     * Query ticker. Example return value:
     * [
     *     'timestamp' => 1411477301,
     *     'low' => '312.00',
     *     'high' => '316.76',
     *     'ask' => '316.46',
     *     'bid' => '310.06',
     *     'previous' => '312.17',
     *     'last' => '316.46',
     *     'volume' => '13.50610000',
     * ]
     *
     * @return array
     */
    public function getTicker() {
        return $this->executeCall(self::TYPE_PUBLIC, self::METHOD_GET, 'ticker');
    }

    /**
     * Get the trades that took place on CleverCoin. Example return value:
     * [
     *     [
     *         'date' => 1411475620,
     *         'tid' => 263,
     *         'price' => 316.76,
     *         'amount' => 0.06970000,
     *     ],
     *     [
     *         'date' => 1411475571,
     *         'tid' => 261,
     *         'price' => 316.76,
     *         'amount' => 0.12225000,
     *     ],
     * ]
     *
     * @param integer $since Return transactions with an ID (tid) newer than this
     *
     * @return array
     */
    public function getTransactions($since = 0) {
        return $this->executeCall(self::TYPE_PUBLIC, self::METHOD_GET, 'transactions', ['since' => $since]);
    }

    /* --- Private API calls --- */

    /**
     * Cancel an order. Example return value:
     * ['result' => 'success']
     *
     * @param integer $orderID The ID of the order to cancel
     *
     * @return array
     */
    public function cancelLimitedOrder($orderID) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_DELETE, 'orders/limited', ['orderID' => $orderID]);
    }

    /**
     * Withdraw bitcoins. Example return value:
     * ['withdrawalID' => 123]
     *
     * Note: checking status of a withdrawalId through API is not yet supported
     *
     * @param string $amount The amount in BTC to withdraw; e.g. '1.23000000'
     * @param string $toAddress The BTC recipient address
     *
     * @return array
     */
    public function createBitcoinWithdrawal($amount, $toAddress) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_POST, 'bitcoin/withdrawal', [], [
            'amount'    => $amount,
            'toAddress' => $toAddress,
        ]);
    }

    /**
     * Create a limited order. Example return value:
     * ['orderID' => 1185799]
     *
     * @param string $type 'bid' or 'ask'
     * @param string $amount Limited order amount in BTC; e.g. '2.56000000'
     * @param string $price Limited order price per BTC; e.g. '300.00'
     *
     * @return array
     */
    public function createLimitedOrder($type, $amount, $price) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_POST, 'orders/limited', [], [
            'type'   => $type,
            'amount' => $amount,
            'price'  => $price,
        ]);
    }

    /**
     * Get bitcoin deposit address. Example return value:
     * ['address' => '1H8hxnmjezTUquDPoHk9gKvmaDbcBtAzY9']
     *
     * @return array
     */
    public function getBitcoinDepositAddress() {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_GET, 'bitcoin/depositAddress');
    }

    /**
     * Query open orders of this account. Example return value:
     * [
     *     [
     *         'orderID' => 1184185,
     *         'type' => 'bid',
     *         'amount' => '1.00000000',
     *         'remainingAmount' => '1.00000000',
     *         'price' => '16.95',
     *         'isOpen' => true,
     *     ],
     *     [
     *         'orderID' => 1184199,
     *         'type' => 'ask',
     *         'amount' => '1.00000000',
     *         'remainingAmount' => '1.00000000',
     *         'price' => '1310.06',
     *         'isOpen' => true,
     *     ],
     * ]
     *
     * @param integer $orderID
     *
     * @return array
     */
    public function getLimitedOrders($orderID = null) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_GET, 'orders/limited', array_filter(['orderID' => $orderID]));
    }

    /**
     * Get our latest trades. Example return value:
     * [
     *     [
     *         'transactionId' => 427,
     *         'time' => 1411475620,
     *         'type' => 'buy',
     *         'price' => '316.760000000',
     *         'volume' => '0.06970000',
     *         'order' => 72957492,
     *     ],
     *     [
     *         'transactionId' => 428,
     *         'time' => 1411475571,
     *         'type' => 'sell',
     *         'price' => '316.760000000',
     *         'volume' => '0.12225000',
     *         'order' => 72957493,
     *     ],
     * ]
     *
     * @param integer $count Maximum number of trades to return; range 1 to 500
     *
     * @return array
     */
    public function getTrades($count = 100) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_GET, 'trades', ['count' => $count]);
    }

    /**
     * Query your wallet balances. Example return value:
     * [
     *     [
     *         'currency' => 'BTC',
     *         'balance' => '0.00000000',
     *     ],
     *     [
     *         'currency' => 'EUR',
     *         'balance' => '0.00',
     *     ],
     * ]
     *
     * @return array
     */
    public function getWallets() {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_GET, 'wallets');
    }
}
