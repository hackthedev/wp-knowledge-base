<?php

class PayPalLibrary {
    private $client_id;
    private $secret;
    private $currency;
    private $webhook_id;

    public function __construct($client_id, $secret, $currency = 'USD', $webhook_id = '') {
        $this->client_id = $client_id;
        $this->secret = $secret;
        $this->currency = $currency;
        $this->webhook_id = $webhook_id;
    }

    public function generatePurchase($amount, $description, $return_url, $cancel_url) {
        $auth = base64_encode($this->client_id . ":" . $this->secret);

        // Get access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v1/oauth2/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_USERPWD, $this->client_id . ":" . $this->secret);

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "Accept-Language: en_US";
        $headers[] = "Authorization: Basic " . $auth;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        curl_close($ch);

        if (empty($result)) {
            return false;
        }

        $json = json_decode($result);
        $access_token = $json->access_token;

        // Create payment
        $payment_data = array(
            "intent" => "sale",
            "redirect_urls" => array(
                "return_url" => $return_url,
                "cancel_url" => $cancel_url,
            ),
            "payer" => array(
                "payment_method" => "paypal"
            ),
            "transactions" => array(
                array(
                    "amount" => array(
                        "total" => $amount,
                        "currency" => $this->currency
                    ),
                    "description" => $description
                )
            )
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v1/payments/payment");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "Authorization: Bearer " . $access_token
        ));

        $result = curl_exec($ch);
        curl_close($ch);

        if (empty($result)) {
            return false;
        }

        $json = json_decode($result);
        foreach ($json->links as $link) {
            if ($link->rel == 'approval_url') {
                return $link->href;
            }
        }

        return false;
    }

    public function verifyWebhook($event) {
        if (!$this->webhook_id) {
            return false; // No webhook ID provided
        }

        $auth = base64_encode($this->client_id . ":" . $this->secret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v1/notifications/verify-webhook-signature");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        $headers = array();
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request_data = array(
            "transmission_id" => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'],
            "transmission_time" => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'],
            "cert_url" => $_SERVER['HTTP_PAYPAL_CERT_URL'],
            "auth_algo" => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'],
            "transmission_sig" => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'],
            "webhook_id" => $this->webhook_id,
            "webhook_event" => $event
        );

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));

        $result = curl_exec($ch);
        curl_close($ch);

        if (empty($result)) {
            return false;
        }

        $json = json_decode($result);

        return $json->verification_status === 'SUCCESS';
    }

    public function handleWebhook(callable $callback) {
        // Retrieve the webhook event from the request
        $request_body = file_get_contents('php://input');
        $event = json_decode($request_body, true);

        // Verify the webhook event
        if ($this->verifyWebhook($event)) {
            // Execute the callback function with the verified event data
            call_user_func($callback, $event);
        }

        // Return a 200 response to PayPal
        status_header(200);
        exit();
    }
}
