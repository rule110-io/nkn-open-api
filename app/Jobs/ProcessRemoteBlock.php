<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;

use App\Block;
use App\Transaction;
use App\Header;
use App\Program;
use App\Payload;
use App\Sigchain;
use App\SigchainElem;
use App\AddressBookItem;
use App\AddressStatistic;

use App\Events\BlockEvent;
use App\Events\CoinbaseTxEvent;
use App\Events\SigChainTxEvent;
use App\Events\TransferAssetTxEvent;
use App\Events\RegisterNameTxEvent;
use App\Events\TransferNameTxEvent;
use App\Events\DeleteNameTxEvent;
use App\Events\SubscribeTxEvent;
use App\Events\UnsubscribeTxEvent;
use App\Events\NanoPayTxEvent;
use App\Events\GenerateIdTxEvent;
use App\Events\IssueAssetTxEvent;

use App\Helpers\PubKey2Wallet;

use App\Jobs\SyncAddressBalance;

use Carbon\Carbon;

use Log;
use Cache;


class ProcessRemoteBlock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $blockheight;
    protected $ws_enabled;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($blockheight, $ws_enabled)
    {
        $this->blockheight = $blockheight;
        $this->ws_enabled = $ws_enabled;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $requestContent = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'json' => [
                "id" => 1,
                "method" => "getblock",
                "params" => [
                    "height" => (int) $this->blockheight,
                ],
                "jsonrpc" => "2.0"
            ]
        ];

        try {
            // Try to get the whole block with all data
            $client = new GuzzleHttpClient();

            $apiRequest = $client->Post(config('nkn.remote-node.address') . ':' . config('nkn.remote-node.port'), $requestContent);

            $response = json_decode($apiRequest->getBody(), true, 512, JSON_BIGINT_AS_STRING);

            // Global data
            $created_at = $response["result"]["header"]["timestamp"];
            $wallet = PubKey2Wallet::encode($response["result"]["header"]["signerPk"]);

            // Create basic database objects
            $block_obj = new Block(array_merge($response["result"], ['created_at' => $created_at]));
            $header_obj = new Header(array_merge($response["result"]["header"], ['created_at' => $created_at, 'wallet' => $wallet]));

            $block_obj->save();
            $block_obj->header()->save($header_obj);

            $addresses_involved = [];

            foreach ($response["result"]["transactions"] as $transaction) {


                $transaction_obj = new Transaction(array_merge($transaction, ['created_at' => $created_at, 'block_height' => $response["result"]["header"]["height"]]));
                $block_obj->transactions()->save($transaction_obj);

                // directly parse all programs into the database
                foreach ($transaction["programs"] as $program) {
                    $program_obj = new Program(array_merge($program, ['created_at' => $created_at]));
                    $transaction_obj->programs()->save($program_obj);

                    //mark fee sender as address to update
                    array_push($addresses_involved, PubKey2Wallet::encode(substr ( $program_obj->code , 2, 64)));

                }

                // Now we will deserialise the payload...
                // Depending on the payload-type we will store and extract different attributes...
                // For more check https://github.com/nknorg/nkn/blob/master/pb/transaction.proto

                switch ($transaction["txType"]) {
                    case "COINBASE_TYPE":
                        $protoCoinbase = new \Protos\Coinbase;
                        try {
                            $protoCoinbase->mergeFromString(hex2bin($transaction["payloadData"]));

                            $senderWallet = PubKey2Wallet::programHashToAddress(bin2hex($protoCoinbase->getSender()));
                            $recipientWallet = PubKey2Wallet::programHashToAddress(bin2hex($protoCoinbase->getRecipient()));
                            $amount = $protoCoinbase->getAmount();

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "senderWallet" => $senderWallet,
                                "sender" => bin2hex($protoCoinbase->getSender()),
                                "recipient" => bin2hex($protoCoinbase->getRecipient()),
                                "recipientWallet" => $recipientWallet,
                                "amount" => $amount,
                                "signerPk" => $response["result"]["header"]["signerPk"]
                            ];
                            if ($header_obj->wallet !== $recipientWallet) {
                                $header_obj->benificiaryWallet = $recipientWallet;
                            }
                            $header_obj->reward = $amount;
                            $header_obj->save();
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            //increment Address tx count
                            $recipientAddressStatistic = AddressStatistic::firstOrNew(['address' => $recipientWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $recipientAddressStatistic->transaction_count = ($recipientAddressStatistic->transaction_count + 1);
                            $recipientAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $recipientAddressStatistic->save();

                            array_push($addresses_involved, $recipientWallet);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new CoinbaseTxEvent($transaction_obj));
                            }

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [COINBASE_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }

                        break;

                    case "TRANSFER_ASSET_TYPE":
                        $protoTransferAsset = new \Protos\TransferAsset;
                        try {
                            $protoTransferAsset->mergeFromString(hex2bin($transaction["payloadData"]));

                            $senderWallet = PubKey2Wallet::programHashToAddress(bin2hex($protoTransferAsset->getSender()));
                            $recipientWallet = PubKey2Wallet::programHashToAddress(bin2hex($protoTransferAsset->getRecipient()));

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "sender" => bin2hex($protoTransferAsset->getSender()),
                                "senderWallet" => $senderWallet,
                                "recipient" => bin2hex($protoTransferAsset->getRecipient()),
                                "recipientWallet" => $recipientWallet,
                                "amount" => $protoTransferAsset->getAmount()
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            //increment Address tx count
                            $senderAddressStatistic = AddressStatistic::firstOrNew(['address' => $senderWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $senderAddressStatistic->transaction_count = ($senderAddressStatistic->transaction_count + 1);
                            $senderAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $senderAddressStatistic->save();

                            $recipientAddressStatistic = AddressStatistic::firstOrNew(['address' => $recipientWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $recipientAddressStatistic->transaction_count = ($recipientAddressStatistic->transaction_count + 1);
                            $recipientAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $recipientAddressStatistic->save();

                            array_push($addresses_involved, $senderWallet);
                            array_push($addresses_involved, $recipientWallet);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new TransferAssetTxEvent($transaction_obj));
                            }

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [TRANSFER_ASSET_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }
                        break;

                    case "SIG_CHAIN_TXN_TYPE":
                        $protoSigChainTxn = new \Protos\SigChainTxn;
                        try {
                            $protoSigChainTxn->mergeFromString(hex2bin($transaction["payloadData"]));
                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "submitter" => bin2hex($protoSigChainTxn->getSubmitter()),
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            //A Commit-payload is the only one who carries a sigchain, so we will parse and store it
                            $protoSigchain = new \Protos\SigChain;
                            try {
                                $protoSigchain->mergeFromString($protoSigChainTxn->getSigChain());

                                $pubkey = bin2hex($protoSigchain->getSrcPubkey());

                                $sigchainData = [
                                    "nonce" => $protoSigchain->getNonce(),
                                    "dataSize" => $protoSigchain->getDataSize(),
                                    "blockHash" => bin2hex($protoSigchain->getBlockHash()),
                                    "srcId" => bin2hex($protoSigchain->getSrcId()),
                                    "srcPubkey" => $pubkey,
                                    "destId" => bin2hex($protoSigchain->getDestId()),
                                    "destPubkey" => bin2hex($protoSigchain->getDestPubkey())
                                ];
                                $sigchain_obj = new Sigchain(array_merge($sigchainData, ['created_at' => $created_at]));
                                $payload_obj->sigchain()->save($sigchain_obj);

                                $sigchain_elements = [];

                                foreach ($protoSigchain->getElems() as $key => $value) {

                                    $sigchain_element = new SigchainElem([
                                        "id2" => bin2hex($value->getId()),
                                        "pubkey" => $pubkey,
                                        "wallet" => PubKey2Wallet::encode($pubkey),
                                        "nextPubkey" => bin2hex($value->getNextPubkey()),
                                        "mining" => $value->getMining(),
                                        "sigAlgo" => \Protos\SigAlgo::name($value->getSigAlgo()),
                                        "signature" => bin2hex($value->getSignature()),
                                        "vrf" => bin2hex($value->getVrf()),
                                        "proof" => bin2hex($value->getProof()),
                                        "created_at" => $created_at
                                    ]);
                                    $sigchain_obj->sigchain_elems()->save($sigchain_element);
                                    array_push($sigchain_elements,$sigchain_element);

                                    $pubkey = bin2hex($value->getNextPubkey());
                                }

                                // WebSocket Events
                                if ($this->ws_enabled){
                                    $transaction_obj->payload = $payload_obj;
                                    $transaction_obj->payload->sigchain = $sigchain_obj;
                                    $transaction_obj->payload->sigchain->sigchain_elems = $sigchain_elements;
                                    event(new SigChainTxEvent($transaction_obj));
                                }
                            } catch (\Exception $e) {
                                Log::channel('syncWithBlockchain')->error("Error processing sigchainElems: " . $transaction["payloadData"] . ": " . $e);
                            }
                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [SIG_CHAIN_TXN_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }

                        break;

                    case "REGISTER_NAME_TYPE":
                        $protoRegisterName = new \Protos\RegisterName;
                        try {
                            $protoRegisterName->mergeFromString(hex2bin($transaction["payloadData"]));

                            $registrant = bin2hex($protoRegisterName->getRegistrant());
                            $registrantWallet = PubKey2Wallet::encode(bin2hex($protoRegisterName->getRegistrant()));
                            $name = bin2hex($protoRegisterName->getName());

                            $asciiName = '';
                            for ($i = 0; $i < strlen($name); $i += 2) $asciiName .= chr(hexdec(substr($name, $i, 2)));

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "registrant" => $registrant,
                                "registrantWallet" =>  $registrantWallet,
                                "registration_fee" => $protoRegisterName->getRegistrationFee(),
                                "name" => $asciiName,
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            Cache::forget('addressBookItem-addresses-' . $registrantWallet);
                            Cache::forget('addressBookItem-name-' . $asciiName);

                            AddressBookItem::create([
                                "public_key" => $registrant,
                                "name" => $asciiName,
                                "address" => $registrantWallet,
                                "expires_at" => Carbon::createFromTimestamp($created_at)->addYear()->toDateTimeString()
                            ]);

                            //increment Address tx count
                            $registrantAddressStatistic = AddressStatistic::firstOrNew(['address' => $registrantWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $registrantAddressStatistic->transaction_count = ($registrantAddressStatistic->transaction_count + 1);
                            $registrantAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $registrantAddressStatistic->save();

                            array_push($addresses_involved, $registrantWallet);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new RegisterNameTxEvent($transaction_obj));
                            }

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [REGISTER_NAME_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }

                        break;

                    case "TRANSFER_NAME_TYPE":
                        $protoTransferName = new \Protos\TransferName;
                        try {
                            $protoTransferName->mergeFromString(hex2bin($transaction["payloadData"]));

                            $registrant = bin2hex($protoTransferName->getRegistrant());
                            $registrantWallet = PubKey2Wallet::encode($registrant);
                            $recipient = bin2hex($protoTransferName->getRecipient());
                            $recipientWallet = PubKey2Wallet::encode($recipient);
                            $name = bin2hex($protoTransferName->getName());

                            $asciiName = '';
                            for ($i = 0; $i < strlen($name); $i += 2) $asciiName .= chr(hexdec(substr($name, $i, 2)));

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "registrant" => $registrant,
                                "registrantWallet" =>  $registrantWallet,
                                "recipient" => $recipient,
                                "recipientWallet" => $recipientWallet,
                                "name" => $asciiName,
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            AddressBookItem::updateOrCreate([
                                "public_key" => $registrant,
                                "name" => $asciiName
                            ], [
                                "public_key" => $recipient,
                                "name" => $asciiName,
                                "address" => $recipientWallet,
                                "expires_at" => Carbon::createFromTimestamp($created_at)->addYear()->toDateTimeString()
                            ]);

                            Cache::forget('addressBookItem-addresses-' . $registrantWallet);
                            Cache::forget('addressBookItem-addresses-' . $recipientWallet);
                            Cache::forget('addressBookItem-name-' . $asciiName);

                            //increment Address tx count
                            $registrantAddressStatistic = AddressStatistic::firstOrNew(['address' => $registrantWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $registrantAddressStatistic->transaction_count = ($registrantAddressStatistic->transaction_count + 1);
                            $registrantAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $registrantAddressStatistic->save();

                            $recipientAddressStatistic = AddressStatistic::firstOrNew(['address' => $recipientWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $recipientAddressStatistic->transaction_count = ($recipientAddressStatistic->transaction_count + 1);
                            $recipientAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $recipientAddressStatistic->save();

                            array_push($addresses_involved, $registrantWallet);
                            array_push($addresses_involved, $recipientWallet);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new TransferNameTxEvent($transaction_obj));
                            }

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [TRANSFER_NAME_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }
                        break;

                    case "DELETE_NAME_TYPE":
                        $protoDeleteName = new \Protos\DeleteName;
                        try {
                            $protoDeleteName->mergeFromString(hex2bin($transaction["payloadData"]));

                            $registrant = bin2hex($protoDeleteName->getRegistrant());
                            $registrantWallet = PubKey2Wallet::encode($registrant);
                            $name = bin2hex($protoDeleteName->getName());

                            $asciiName = '';
                            for ($i = 0; $i < strlen($name); $i += 2) $asciiName .= chr(hexdec(substr($name, $i, 2)));

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "registrant" => $registrant,
                                "registrantWallet" => $registrantWallet,
                                "name" => $asciiName,
                            ];

                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);
                            AddressBookItem::where([
                                ["public_key", "=", $registrant],
                                ["name", "=", $asciiName]
                            ])->delete();

                            Cache::forget('addressBookItem-addresses-' . $registrantWallet);
                            Cache::forget('addressBookItem-name-' . $asciiName);

                            //increment Address tx count
                            $registrantAddressStatistic = AddressStatistic::firstOrNew(['address' => $registrantWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $registrantAddressStatistic->transaction_count = ($registrantAddressStatistic->transaction_count + 1);
                            $registrantAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $registrantAddressStatistic->save();

                            array_push($addresses_involved, $registrantWallet);

                            // WebSocket events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new DeleteNameTxEvent($transaction_obj));
                            }

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [DELETE_NAME_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }

                        break;

                    case "SUBSCRIBE_TYPE":
                        $protoSubscribe = new \Protos\Subscribe;

                        try {
                            $protoSubscribe->mergeFromString(hex2bin($transaction["payloadData"]));

                            $identifier = bin2hex($protoSubscribe->getIdentifier());
                            $topic = bin2hex($protoSubscribe->getTopic());

                            $subscriber = bin2hex($protoSubscribe->getSubscriber());
                            $subscriberWallet = PubKey2Wallet::encode($subscriber);

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "subscriber" => $subscriber,
                                "subscriberWallet" => $subscriberWallet,
                                "identifier" => $identifier,
                                "topic" => $topic,
                                "bucket" => $protoSubscribe->getBucket(),
                                "duration" => $protoSubscribe->getDuration(),
                                "meta" => bin2hex($protoSubscribe->getMeta()),
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new SubscribeTxEvent($transaction_obj));
                            }


                            //increment Address tx count
                            $subscriberAddressStatistic = AddressStatistic::firstOrNew(['address' => $subscriberWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $subscriberAddressStatistic->transaction_count = ($subscriberAddressStatistic->transaction_count + 1);
                            $subscriberAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $subscriberAddressStatistic->save();

                            array_push($addresses_involved, $subscriberWallet);

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [SUBSCRIBE_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }

                        break;

                    case "UNSUBSCRIBE_TYPE":
                        $protoUnsubscribe = new \Protos\Unsubscribe;

                        try {
                            $protoUnsubscribe->mergeFromString(hex2bin($transaction["payloadData"]));

                            $identifier = bin2hex($protoUnsubscribe->getIdentifier());
                            $topic = bin2hex($protoUnsubscribe->getTopic());

                            $subscriber = bin2hex($protoUnsubscribe->getSubscriber());
                            $subscriberWallet = PubKey2Wallet::encode($subscriber);

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "subscriber" => $subscriber,
                                "subscriberWallet" => $subscriberWallet,
                                "identifier" => $identifier,
                                "topic" => $topic
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new UnsubscribeTxEvent($transaction_obj));
                            }


                            //increment Address tx count
                            $subscriberAddressStatistic = AddressStatistic::firstOrNew(['address' => $subscriberWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $subscriberAddressStatistic->transaction_count = ($subscriberAddressStatistic->transaction_count + 1);
                            $subscriberAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $subscriberAddressStatistic->save();

                            array_push($addresses_involved, $subscriberWallet);

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [UNSUBSCRIBE_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }
                        break;

                    case "GENERATE_ID_TYPE":
                        $protoGenerateID = new \Protos\GenerateID;
                        try {
                            $protoGenerateID->mergeFromString(hex2bin($transaction["payloadData"]));
                            $publicKey = bin2hex($protoGenerateID->getPublicKey());
                            $generateWallet = PubKey2Wallet::encode($publicKey);

                            $senderWallet = PubKey2Wallet::programHashToAddress(bin2hex($protoGenerateID->getSender()));

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "public_key" => $publicKey,
                                "registration_fee" => $protoGenerateID->getRegistrationFee(),
                                "sender" => bin2hex($protoGenerateID->getSender()),
                                "senderWallet" => $senderWallet,
                                "version" => $protoGenerateID->getVersion(),
                                "generateWallet" => $generateWallet
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                event(new GenerateIdTxEvent($transaction_obj));
                            }

                            array_push($addresses_involved, $generateWallet);

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [GENERATE_ID_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }
                        break;

                    case "NANO_PAY_TYPE":
                        $protoNanoPay = new \Protos\NanoPay;
                        try {
                            $protoNanoPay->mergeFromString(hex2bin($transaction["payloadData"]));

                            $recipientWallet = PubKey2Wallet::programHashToAddress(bin2hex($protoNanoPay->getRecipient()));
                            $senderWallet = PubKey2Wallet::programHashToAddress(bin2hex($protoNanoPay->getSender()));

                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "sender" => bin2hex($protoNanoPay->getSender()),
                                "senderWallet" => $senderWallet,
                                "recipient" => bin2hex($protoNanoPay->getRecipient()),
                                "recipientWallet" => $recipientWallet ,
                                "identifier" => $protoNanoPay->getId(),
                                "amount" => $protoNanoPay->getAmount(),
                                "txn_expiration" => $protoNanoPay->getTxnExpiration(),
                                "nano_pay_expiration" => $protoNanoPay->getNanoPayExpiration()
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            //increment Address tx count
                            $senderAddressStatistic = AddressStatistic::firstOrNew(['address' => $senderWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $senderAddressStatistic->transaction_count = ($senderAddressStatistic->transaction_count + 1);
                            $senderAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $senderAddressStatistic->save();

                            $recipientAddressStatistic = AddressStatistic::firstOrNew(['address' => $recipientWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $recipientAddressStatistic->transaction_count = ($recipientAddressStatistic->transaction_count + 1);
                            $recipientAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $recipientAddressStatistic->save();

                            array_push($addresses_involved, $senderWallet);
                            array_push($addresses_involved, $recipientWallet);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new NanoPayTxEvent($transaction_obj));
                            }

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing [NANO_PAY_TYPE] payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }
                        break;

                    case "ISSUE_ASSET_TYPE":
                        $protoIssueAsset = new \Protos\IssueAsset;
                        try {
                            $protoIssueAsset->mergeFromString(hex2bin($transaction["payloadData"]));
                            $payloadData = [
                                "payloadType" => $transaction["txType"],
                                "sender" => bin2hex($protoIssueAsset->getSender()),
                                "senderWallet" => PubKey2Wallet::programHashToAddress(bin2hex($protoIssueAsset->getSender())),
                                "symbol" => $protoIssueAsset->getSymbol(),
                                "total_supply" => $protoIssueAsset->getTotalSupply(),
                                "precision" => $protoIssueAsset->getPrecision(),
                            ];
                            $payload_obj = new Payload(array_merge($payloadData, ['created_at' => $created_at]));
                            $transaction_obj->payload()->save($payload_obj);

                            //increment Address tx count
                            $senderAddressStatistic = AddressStatistic::firstOrNew(['address' => $senderWallet],['transaction_count' => 0, 'first_transaction' => Carbon::createFromTimestamp($created_at)->toDateTimeString()]);
                            $senderAddressStatistic->transaction_count = ($senderAddressStatistic->transaction_count + 1);
                            $senderAddressStatistic->last_transaction = Carbon::createFromTimestamp($created_at)->toDateTimeString();
                            $senderAddressStatistic->save();

                            array_push($addresses_involved, $senderWallet);

                            // WebSocket Events
                            if ($this->ws_enabled){
                                $transaction_obj->payload = $payload_obj;
                                event(new IssueAssetTxEvent($transaction_obj));
                            }

                        } catch (\Exception $e) {
                            Log::channel('syncWithBlockchain')->error("Error processing payloadData: " . $transaction["payloadData"] . ": " . $e);
                        }
                        break;
                }
            }

            if ($this->ws_enabled){
                $addressStatistics = AddressStatistic::whereIn('address', $addresses_involved)->get();
                foreach ($addressStatistics as $addressStatistic) {
                    SyncAddressBalance::dispatch($addressStatistic)->onQueue('balanceSync');
                }
            }

            if ($this->ws_enabled){

                $block_event = Block::where('id', $block_obj->id)
                ->with(['header:block_id,height,signerPk,wallet,benificiaryWallet,created_at'])
                ->first();
                $block_event->transactions_count = count($response["result"]["transactions"]);

                event(new BlockEvent($block_event));
            }


        } catch (RequestException $re) {
            Log::channel('syncWithBlockchain')->error("ProcessRemoteBlock: Can't connect to testnet-node!");
            throw $re;
        }
    }
    public function tags()
    {
        return ['ProcessRemoteBlock', $this->blockheight];
    }
}
