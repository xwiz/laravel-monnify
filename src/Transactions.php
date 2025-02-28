<?php
/**
 * Created By: Henry Ejemuta
 * PC: Enrico Systems
 * Project: laravel-monnify
 * Company: Stimolive Technologies Limited
 * Class Name: Transactions.php
 * Date Created: 3/2/21
 * Time Created: 4:22 PM
 */

namespace HenryEjemuta\LaravelMonnify;


use HenryEjemuta\LaravelMonnify\Classes\MonnifyIncomeSplitConfig;
use HenryEjemuta\LaravelMonnify\Classes\MonnifyPaymentMethods;
use HenryEjemuta\LaravelMonnify\Exceptions\MonnifyFailedRequestException;

abstract class Transactions
{
    private $monnify;

    /**
     * Flexible handle to the Monnify Configuration
     *
     * @var
     */
    private $config;

    /**
     * Transactions constructor.
     * @param $monnify
     */
    public function __construct(Monnify $monnify, $config)
    {
        $this->config = $config;
        $this->monnify = $monnify;
    }


    /**
     * This returns all transactions done by a merchant.
     *
     * @param array $queryParams
     * @return object
     *
     * @throws MonnifyFailedRequestException
     *
     * Kindly check here for query parameters keys
     * @link https://docs.teamapt.com/display/MON/Get+All+Transactions
     */
    public function getAllTransactions(array $queryParams)
    {
        $endpoint = "{$this->monnify->baseUrl}{$this->monnify->v1}transactions/search?" . http_build_query($queryParams, '', '&amp;');

        $response = $this->monnify->withOAuth2()->get($endpoint);

        $responseObject = json_decode($response->body());
        if (!$response->successful())
            throw new MonnifyFailedRequestException($responseObject->responseMessage ?? "Path '{$responseObject->path}' {$responseObject->error}", $responseObject->responseCode ?? $responseObject->status);

        return $responseObject->responseBody;
    }


    /**
     * Allows you initialize a transaction on Monnify and returns a checkout URL which you can load within a browser to display the payment form to your customer.
     *
     * @param float $amount The amount to be paid by the customer
     * @param string $customerName Full name of the customer
     * @param string $customerEmail Email address of the customer
     * @param string $paymentReference Merchant's Unique reference for the transaction.
     * @param string $paymentDescription Description for the transaction. Will be returned as part of the account name on name enquiry for transfer payments.
     * @param string $redirectUrl A URL which user will be redirected to, on completion of the payment.
     * @param MonnifyPaymentMethods $monnifyPaymentMethods
     * @param MonnifyIncomeSplitConfig $incomeSplitConfig
     * @param string|null $currencyCode
     * @return object
     *
     * @throws MonnifyFailedRequestException
     * @link https://docs.teamapt.com/display/MON/Initialize+Transaction
     */
    public function initializeTransaction(float $amount, string $customerName, string $customerEmail, string $paymentReference, string $paymentDescription, string $redirectUrl, MonnifyPaymentMethods $monnifyPaymentMethods, MonnifyIncomeSplitConfig $incomeSplitConfig = null, string $currencyCode = null)
    {
        $endpoint = "{$this->monnify->baseUrl}{$this->monnify->v1}merchant/transactions/init-transaction";

        $formData = [
            "amount" => $amount,
            "customerName" => trim($customerName),
            "customerEmail" => $customerEmail,
            "paymentReference" => $paymentReference,
            "paymentDescription" => trim($paymentDescription),
            "currencyCode" => $currencyCode ?? $this->config['default_currency_code'],
            "contractCode" => $this->config['contract_code'],
            "redirectUrl" => trim($redirectUrl),
            "paymentMethods" => $monnifyPaymentMethods->toArray(),
        ];
        if ($incomeSplitConfig !== null)
            $formData["incomeSplitConfig"] = $incomeSplitConfig->toArray();

        $response = $this->monnify->withBasicAuth()->post($endpoint, $formData);

        $responseObject = json_decode($response->body());
        if (!$response->successful())
            throw new MonnifyFailedRequestException($responseObject->responseMessage ?? "Path '{$responseObject->path}' {$responseObject->error}", $responseObject->responseCode ?? $responseObject->status);

        return $responseObject->responseBody;
    }


    /**
     * Allows you post a transaction on Monnify using customer card token.
     *
     * @param float $amount The amount to be paid by the customer
     * @param string $customerName Full name of the customer
     * @param string $customerEmail Email address of the customer
     * @param string $paymentReference Merchant's Unique reference for the transaction.
     * @param string $paymentDescription Description for the transaction. Will be returned as part of the account name on name enquiry for transfer payments.
     * @param string $cardToken The card token to use for the payment.
     * @param MonnifyIncomeSplitConfig $incomeSplitConfig
     * @param string|null $currencyCode
     * @return object
     *
     * @throws MonnifyFailedRequestException
     * @link https://teamapt.atlassian.net/wiki/spaces/MON/pages/666501132/Charge+Card+Token
     */
    public function chargeCard(float $amount, string $customerName, string $customerEmail, string $paymentReference, string $paymentDescription, string $cardToken, MonnifyIncomeSplitConfig $incomeSplitConfig = null, string $currencyCode = null)
    {
        $endpoint = "{$this->monnify->baseUrl}{$this->monnify->v1}merchant/cards/charge-card-token";

        $formData = [
            "cardToken" => $cardToken,
            "amount" => $amount,
            "customerName" => trim($customerName),
            "customerEmail" => $customerEmail,
            "paymentReference" => $paymentReference,
            "paymentDescription" => trim($paymentDescription),
            "currencyCode" => $currencyCode ?? $this->config['default_currency_code'],
            "contractCode" => $this->config['contract_code'],
            "apiKey" => $this->config['api_key'],
        ];
        if ($incomeSplitConfig !== null)
            $formData["incomeSplitConfig"] = $incomeSplitConfig->toArray();

        $response = $this->monnify->withOAuth2()->post($endpoint, $formData);

        $responseObject = json_decode($response->body());
        if (!$response->successful())
            throw new MonnifyFailedRequestException($responseObject->responseMessage ?? "Path '{$responseObject->path}' {$responseObject->error}", $responseObject->responseCode ?? $responseObject->status);

        return $responseObject->responseBody;
    }

    /**
     * When Monnify sends transaction notifications, we add a transaction hash for security reasons. We expect you to try to recreate the transaction hash and only honor the notification if it matches.
     *
     * To calculate the hash value, concatenate the following parameters in the request body and generate a hash using the SHA512 algorithm:
     *
     * @param string $paymentReference Unique reference generated by the merchant for each transaction. However, will be the same as transactionReference for reserved accounts.
     * @param mixed $amountPaid The amount that was paid by the customer
     * @param string $paidOn Date and Time when payment happened in the format dd/mm/yyyy hh:mm:ss
     * @param string $transactionReference Unique transaction reference generated by Monnify for each transaction
     * @return string Hash of successful transaction
     *
     * @link https://docs.teamapt.com/display/MON/Calculating+the+Transaction+Hash
     */
    public function calculateHash(string $paymentReference, $amountPaid, string $paidOn, string $transactionReference)
    {
        $clientSK = $this->config['secret_key'];
        return hash('sha512', "$clientSK|$paymentReference|$amountPaid|$paidOn|$transactionReference");
    }


    /**
     * We highly recommend that when you receive a notification from us, even after checking to ensure the hash values match,
     * you should initiate a get transaction status request to us with the transactionReference to confirm the actual status of that transaction before updating the records on your database.
     *
     * @param string $transactions Unique transaction reference generated by Monnify for each transaction
     * @return object
     *
     * @throws MonnifyFailedRequestException
     * @link https://docs.teamapt.com/display/MON/Get+Transaction+Status
     */
    public function getTransactionStatus(string $transactions)
    {
        $endpoint = "{$this->monnify->baseUrl}{$this->monnify->v2}transactions/$transactions/";

        $response = $this->monnify->withOAuth2()->get($endpoint);

        $responseObject = json_decode($response->body());
        if (!$response->successful())
            throw new MonnifyFailedRequestException($responseObject->responseMessage ?? "Path '{$responseObject->path}' {$responseObject->error}", $responseObject->responseCode ?? $responseObject->status);

        return $responseObject->responseBody;
    }


    /**
     * Allows you get virtual account details for a transaction using the transactionReference of an initialized transaction.
     * This is useful if you want to control the payment interface.
     * There are a lot of UX considerations to keep in mind if you choose to do this so we recommend you read this @link https://docs.teamapt.com/display/MON/Optimizing+Your+User+Experience.
     *
     * @param string $transactionReference
     * @param string $bankCode
     * @return array
     *
     * @throws MonnifyFailedRequestException
     * @link https://docs.teamapt.com/display/MON/Pay+with+Bank+Transfer
     */
    public function payWithBankTransfer(string $transactionReference, string $bankCode)
    {
        $endpoint = "{$this->monnify->baseUrl}{$this->monnify->v1}merchant/bank-transfer/init-payment";

        $response = $this->monnify->withBasicAuth()->post($endpoint, [
            "transactionReference" => $transactionReference,
            "bankCode" => trim($bankCode),
        ]);

        $responseObject = json_decode($response->body());
        if (!$response->successful())
            throw new MonnifyFailedRequestException($responseObject->responseMessage ?? "Path '{$responseObject->path}' {$responseObject->error}", $responseObject->responseCode ?? $responseObject->status);

        return $responseObject->responseBody;
    }
}
