<?php

namespace ontio\core\transaction;

use ontio\crypto\Address;
use ontio\common\ByteArray;
use ontio\sdk\Constant;
use ontio\core\payload\InvokeCode;
use ontio\common\Fixed64;
use ontio\crypto\PrivateKey;
use ontio\crypto\SignatureScheme;
use ontio\core\scripts\ScriptBuilder;
use ontio\core\scripts\Opcode;
use ontio\core\payload\DeployCode;
use ontio\smartcontract\abi\Parameter;
use ontio\smartcontract\abi\AbiFunction;
use ontio\smartcontract\abi\NativeVmParamsBuilder;

class TransactionBuilder
{

  public function signTransaction(Transaction $tx, PrivateKey $prikey, ? SignatureScheme $scheme = null)
  {
    $sig = TxSignature::create($tx, $prikey, $scheme);
    $tx->sigs = [$sig];
  }

  public function addSig(Transaction $tx, PrivateKey $prikey, ? SignatureScheme $scheme = null)
  {
    $sig = TxSignature::create($tx, $prikey, $scheme);
    $tx->sigs[] = $sig;
  }

  /**
   * Creates transaction to invoke native contract
   *
   * @param string $fnName Function name of contract to call
   * @param string $params Parameters serialized in hex string
   * @param Address $contractAddr Address of contract
   * @param string $gasPrice Gas price
   * @param string $gasLimit Gas limit
   * @param Address $payer Address to pay for transaction gas
   * @return Transaction|Transfer
   */
  public function makeNativeContractTx(
    string $fnName,
    string $params,
    Address $contractAddr,
    string $gasPrice = '',
    string $gasLimit = '',
    ? Address $payer = null
  ) : Transaction {
    $builder = new ScriptBuilder();
    $builder->pushArray(ByteArray::fromHex($params));
    $builder->pushHexString(ByteArray::fromBinary($fnName)->toHex());
    $builder->pushHexString($contractAddr->serialize());
    $builder->pushNum(0);
    $builder->pushInt(Opcode::SYSCALL);
    $builder->pushHexString(ByteArray::fromBinary(Constant::$NATIVE_INVOKE_NAME)->toHex());
    $payload = new InvokeCode();
    $payload->code = $builder->toHex();

    $tx;
    if ($fnName === 'transfer' || $fnName === 'transferFrom') {
      $tx = new Transfer();
    } else {
      $tx = new Transaction();
    }

    $tx->type = TxType::Invoke;
    $tx->payload = $payload;
    if ($gasLimit !== '') {
      $tx->gasLimit = new Fixed64((int)$gasLimit);
    }
    if ($gasPrice !== '') {
      $tx->gasPrice = new Fixed64((int)$gasPrice);
    }
    if ($payer) {
      $tx->payer = $payer;
    }
    return $tx;
  }

  public function makeDeployCodeTransaction(
    string $code,
    string $name,
    string $codeVersion,
    string $author,
    string $email,
    string $desc,
    bool $needStorage,
    string $gasPrice,
    string $gasLimit,
    ? Address $payer
  ) : Transaction {
    $dc = new DeployCode();
    $dc->author = $author;
    $dc->code = $code;
    $dc->name = $name;
    $dc->version = $codeVersion;
    $dc->email = $email;
    $dc->needStorage = $needStorage;
    $dc->description = $desc;

    $tx = new Transaction();
    $tx->version = 0x00;
    $tx->payload = $dc;
    $tx->type = TxType::Deploy;

    $tx->gasLimit = new Fixed64($gasLimit);
    $tx->gasPrice = new Fixed64($gasPrice);
    if ($payer) {
      $tx->payer = $payer;
    }

    return $tx;
  }

  /**
   * Creates transaction to invoke smart contract
   *
   * @param string $fnName
   * @param Parameter[]|string $params
   * @param Address $contractAddr
   * @param string $gasPrice
   * @param string $gasLimit
   * @param Address $payer
   * @return void
   */
  public function makeInvokeTransaction(
    string $fnName,
    $params,
    Address $contractAddr,
    string $gasPrice = '0',
    string $gasLimit = '20000',
    ? Address $payer = null
  ) : Transaction {

    $tx = new Transaction();
    $tx->type = TxType::Invoke;

    $code = new ByteArray();
    if (is_string($params)) {
      $code->pushArray(ByteArray::fromHex($params));
    } else {
      $fn = new AbiFunction($fnName, $params);
      $b = new NativeVmParamsBuilder();
      $code->pushArray($b->pushAbiFunction($fn));
    }

    $code->pushInt(Opcode::APPCALL);
    $code->pushArray(ByteArray::fromHex($contractAddr->toHex()));

    $payload = new InvokeCode();
    $payload->code = $code->toHex();

    $tx->payload = $payload;

    if ($gasLimit) {
      $tx->gasLimit = new Fixed64($gasLimit);
    }

    if ($gasPrice) {
      $tx->gasPrice = new Fixed64($gasPrice);
    }

    if ($payer) {
      $tx->payer = $payer;
    }

    return $tx;
  }
}
