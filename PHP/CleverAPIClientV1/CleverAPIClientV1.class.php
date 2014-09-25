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
     * Query order book
     *
     * @param boolean $group Group orders for the same price
     *
     * @return array
     * Example return:
     *
     * Array
     * (
     *     [timestamp] => 1411477485
     *     [bids] => Array
     *         (
     *             [0] => Array
     *                 (
     *                     [0] => 310.06
     *                     [1] => 0.201606
     *                 )
     *
     *             [1] => Array
     *                 (
     *                     [0] => 310
     *                     [1] => 0.17129
     *                 )
     *
     *     [asks] => Array
     *         (
     *             [0] => Array
     *                 (
     *                     [0] => 316.63
     *                     [1] => 0.3933
     *                 )
     *
     *             [1] => Array
     *                 (
     *                     [0] => 316.75
     *                     [1] => 0.3375
     *                 )
     *
     *         )
     *
     * )
     */
    public function getOrderBook($group) {
        return $this->executeCall(self::TYPE_PUBLIC, self::METHOD_GET, 'orderbook', ['group' => $group ? '1' : '0']);
    }

    /**
     * Query ticker
     *
     * @return array
     * Example return:
     *
     * Array
     * (
     *     [timestamp] => 1411477301
     *     [low] => 312.00
     *     [high] => 316.76
     *     [ask] => 316.46
     *     [bid] => 310.06
     *     [last] => 316.46
     *     [volume] => 13.50610000
     * )
     */
    public function getTicker() {
        return $this->executeCall(self::TYPE_PUBLIC, self::METHOD_GET, 'ticker');
    }

    /**
     * Get the trades that took place on CleverCoin.
     *
     * @param integer $since Return transactions with an ID (tid) newer than this
     *
     * @return array
     * Example return:
     *
     * Array
     * (
     *     [0] => Array
     *         (
     *             [date] => 1411475620
     *             [tid] => 263
     *             [price] => 316.76
     *             [amount] => 0.06970000
     *         )
     *
     *     [1] => Array
     *         (
     *             [date] => 1411475571
     *             [tid] => 261
     *             [price] => 316.76
     *             [amount] => 0.12225000
     *         )
     * )
     */
    public function getTransactions($since = 0) {
        return $this->executeCall(self::TYPE_PUBLIC, self::METHOD_GET, 'transactions', ['since' => $since]);
    }

    /* --- Private API calls --- */

    /**
     * Cancel an order
     *
     * @param integer $orderID
     *
     * @return array
     * Example return:
     *
     * Array
     *  (
     *      [result] => 'success'
     *  )
     */
    public function cancelLimitedOrder($orderID) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_DELETE, 'orders/limited', ['orderID' => $orderID]);
    }

    /**
     * Withdraw bitcoins.
     *
     * @param string $amount
     * @param string $toAddress
     *
     * @return array
     * Example return:
     * Array
     *  (
     *      [withdrawalID] => 123
     *  )
     *
     * Note: checking status of a withdrawalId trough API is not yet supported
     */
    public function createBitcoinWithdrawal($amount, $toAddress) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_POST, 'bitcoin/withdrawal', [], [
            'amount'    => $amount,
            'toAddress' => $toAddress,
        ]);
    }

    /**
     * Create a limited order.
     *
     * @param string $type ('bid' or 'ask')
     * @param string $amount (in bitcoins)
     * @param string $price (in euro)
     *
     * @return array
     * Example return:
     *
     * Array
     *  (
     *      [orderID] => 1185799
     *  )
     */
    public function createLimitedOrder($type, $amount, $price) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_POST, 'orders/limited', [], [
            'type'   => $type,
            'amount' => $amount,
            'price'  => $price,
        ]);
    }

    /**
     * Get bitcoin deposit address.
     *
     * @return array
     * Example return:
     *
     * Array
     * (
     *     [address] => 1H8hxnmjezTUquDPoHk9gKvmaDbcBtAzY9
     * )
     */
    public function getBitcoinDepositAddress() {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_GET, 'bitcoin/depositAddress');
    }

    /**
     * Query open orders of this account.
     *
     * @param integer $orderID
     *
     * @return array
     * Example return:
     *
     * Array
     * (
     *     [0] => Array
     *         (
     *             [orderID] => 1184185
     *             [type] => bid
     *             [amount] => 1.00000000
     *             [remainingAmount] => 1.00000000
     *             [price] => 16.95
     *             [isOpen] => 1
     *         )
     *
     *     [1] => Array
     *         (
     *             [orderID] => 1184199
     *             [type] => ask
     *             [amount] => 1.00000000
     *             [remainingAmount] => 1.00000000
     *             [price] => 1310.06
     *             [isOpen] => 1
     *         )
     *
     * )
     */
    public function getLimitedOrders($orderID = null) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_GET, 'orders/limited', array_filter(['orderID' => $orderID]));
    }

    /**
     * Get our latest trades.
     *
     * @param integer $count
     *
     * @return array
     * Example return:
     *
     * Array
     * (
     *     [0] => Array
     *         (
     *             [date] => 1411475620
     *             [tid] => 263
     *             [price] => 316.76
     *             [amount] => 0.06970000
     *         )
     *
     *     [1] => Array
     *         (
     *             [date] => 1411475571
     *             [tid] => 261
     *             [price] => 316.76
     *             [amount] => 0.12225000
     *         )
     * )
     */
    public function getTrades($count = 100) {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_GET, 'trades', ['count' => $count]);
    }

    /**
     * Query your wallet balances.
     *
     * @return array
     * Example return:
     *
     * Array
     * (
     *     [0] => Array
     *         (
     *             [currency] => BTC
     *             [balance] => 0.00000000
     *         )
     *
     *     [1] => Array
     *         (
     *             [currency] => EUR
     *             [balance] => 0.00
     *         )
     *
     * )
     */
    public function getWallets() {
        return $this->executeCall(self::TYPE_PRIVATE, self::METHOD_GET, 'wallets');
    }
}
