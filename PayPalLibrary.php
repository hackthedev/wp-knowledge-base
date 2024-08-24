<?php

require 'vendor/autoload.php';

use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Exception\PayPalConnectionException;

class PayPalPayment {
    private $apiContext;

    public function __construct($clientId, $clientSecret) {
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                $clientId,     // ClientID
                $clientSecret      // ClientSecret
            )
        );

        $this->apiContext->setConfig([
            'mode' => 'live', // or 'sandbox'
            'http.ConnectionTimeOut' => 30,
            'log.LogEnabled' => false,
            'log.FileName' => '',
            'log.LogLevel' => 'ERROR',
            'cache.enabled' => true,
        ]);
    }

    public function createPayment($amount, $currency, $returnUrl, $cancelUrl) {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $amountObj = new Amount();
        $amountObj->setTotal($amount);
        $amountObj->setCurrency($currency);

        $transaction = new Transaction();
        $transaction->setAmount($amountObj);
        $transaction->setDescription("Payment Description");

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($returnUrl)
            ->setCancelUrl($cancelUrl);

        $payment = new Payment();
        $payment->setIntent('sale')
            ->setPayer($payer)
            ->setTransactions([$transaction])
            ->setRedirectUrls($redirectUrls);

        try {
            $payment->create($this->apiContext);
        } catch (PayPalConnectionException $ex) {
            throw new Exception("An error occurred while creating payment: " . $ex->getMessage());
        }

        return $payment;
    }

    public function executePayment($paymentId, $payerId) {
        $payment = Payment::get($paymentId, $this->apiContext);
        $execution = new PaymentExecution();
        $execution->setPayerId($payerId);

        try {
            $result = $payment->execute($execution, $this->apiContext);
        } catch (PayPalConnectionException $ex) {
            throw new Exception("An error occurred while executing payment: " . $ex->getMessage());
        }

        return $result;
    }
}
