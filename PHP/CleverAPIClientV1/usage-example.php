<?php

/*
 * Copyright (c) 2014 CleverCoin <info@clevercoin.com>
 * See the file LICENSE.txt for copying permission.
 */

/**
 * Example usage of the CleverAPIClientV1 library.
 *
 * The private API is still in beta, please notify us at support@clevercoin.com if you are using this API.
 * This way we can notify you before we make any changes.
 */

require_once('CleverAPIClientV1.class.php');

// Your API credentials
$key = 'KEY';
$secret = 'SECRET';

$cleverAPI = new CleverAPIClientV1($key, $secret);

echo '<pre>' . PHP_EOL;

// Query ticker PUBLIC
$ticker = $cleverAPI->getTicker();
print_r($ticker);
/**
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

// Query order book PUBLIC
$group = true; // Group orders for the same price
$orderbook = $cleverAPI->getOrderBook($group);
//print_r($orderbook);

/**
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

// Query your wallet balances
$cleverWallets = $cleverAPI->getWallets();
print_r($cleverWallets);
/**
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
 *
 */

// Query open orders
$openLimitedOrders = $cleverAPI->getLimitedOrders();
print_r($openLimitedOrders);
/**
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

// Get our latest trades
$trades = $cleverAPI->getTrades(5);
print_r($trades);
/**
 * Example return:
 *
 * Array
 * (
 *     [0] => Array
 *         (
 *             [transactionId] => 427
 *             [time] => 1411475620
 *             [type] => buy
 *             [price] => 316.760000000
 *             [volume] => 0.06970000
 *         )
 *
 *     [1] => Array
 *         (
 *             [transactionId] => 428
 *             [time] => 1411475571
 *             [type] => sell
 *             [price] => 316.760000000
 *             [volume] => 0.12225000
 *         )
 * )
 */

// Get the trades that took place on CleverCoin. PUBLIC
$transactions = $cleverAPI->getTransactions();
print_r($transactions);
/**
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

// Get bitcoin deposit address
$bitcoinAddress = $cleverAPI->getBitcoinDepositAddress();
print_r($bitcoinAddress);
/**
 * Example return:
 *
 *Array
 * (
 *     [address] => 1H8hxnmjezTUquDPoHk9gKvmaDbcBtAzY9
 * )
 */

// MUTATING CALLS (commented out by default for safety)

// Create an limited order
/*
$type = 'bid'; // or 'ask'
$amount = '2.25'; // amount in bitcoins
$price = '499.77'; // price in euro
print_r($cleverAPI->createLimitedOrder($type, $amount, $price));
*/
/**
 * Example return:
 *
 * Array
 *  (
 *      [orderID] => 1185799
 *  )
 */

// Cancel an order
/*
$orderID = 1184199;
$cleverAPI->cancelLimitedOrder($orderId);
*/
/**
 * Example return:
 *
 * Array
 *  (
 *      [result] => 'success'
 *  )
 */

// Withdraw bitcoins
/*
$sendtoAddress = '1ByjU8c1xpgiNL8vk9qdS5HnmYGQk3PeFs';
$amount = '0.684';
$withdrawal = $cleverAPI->createBitcoinWithdrawal($amount, $sendtoAddress);
print_r($withdrawal);
*/
/**
 * Example return:
 * Array
 *  (
 *      [withdrawalID] => 123
 *  )
 *
 * Note: checking status of a withdrawalId trough API is not yet supported
 */

unset($cleverAPI); // Unload API
