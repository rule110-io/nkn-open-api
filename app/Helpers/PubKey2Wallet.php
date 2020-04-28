<?php

namespace App\Helpers;


define('ADDRESS_GEN_PREFIX', '02b825');
define('CHECKSUM_LEN', 4);

function publicKeyToSignatureRedeem($pubKey) {
    return '20' . $pubKey . 'ac';
}

function hexStringToProgramHash($hexStr) {
    return hash('ripemd160', hex2bin(hash('sha256', hex2bin($hexStr))));
}

function genAddressVerifyHexFromProgramHash($programHash) {
    $verifyHex = hash('sha256', hex2bin(hash('sha256', hex2bin(ADDRESS_GEN_PREFIX . $programHash))));
    return substr($verifyHex, 0, CHECKSUM_LEN * 2);
}



class PubKey2Wallet {

    public static function programHashToAddress($programHash) {
        $base58 = new \StephenHill\Base58('123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
        $addressVerifyHex = genAddressVerifyHexFromProgramHash($programHash);
        $addressBaseData = ADDRESS_GEN_PREFIX . $programHash;
        return $base58->encode(hex2bin($addressBaseData . $addressVerifyHex));
    }

    public static function encode($pubKey) {
      $signatureRedeem = publicKeyToSignatureRedeem($pubKey);
      $programHash = hexStringToProgramHash($signatureRedeem);
      return self::programHashToAddress($programHash);
    }

}

