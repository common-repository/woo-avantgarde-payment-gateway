<?php

/* * ********************************************************
 * Avantgarde Payments PHP class for encryption functions   *
 * Developed by: Beta Soft Technology                       *
 * Date: 2016-03-30                                         *
 * Version: 1.0.1                                           *
 * Compatibility: PHP 5.2 and higher                        *  
 * Copyright: Distribution and usage of this                *
 * library is reserved.                                     *
 * **********************************************************/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

abstract class AvantgardeLib {

    //private constants	
    const ENC = MCRYPT_RIJNDAEL_128;
    const MODE = MCRYPT_MODE_CBC;
    const IV = "0123456789abcdef";

    //variables
    public static $env;

    public static function _AESEncryption($text, $key) {
        $size = mcrypt_get_block_size(self::ENC, self::MODE);
        $pad = $size - (strlen($text) % $size);
        $padtext = $text . str_repeat(chr($pad), $pad);

        $crypt = mcrypt_encrypt(self::ENC, base64_decode($key), $padtext, self::MODE, self::IV);
        return base64_encode($crypt);
    }

    public static function _AESDecryption($crypt, $key) {
        $crypt = base64_decode($crypt);
        //	echo strlen("S°n.î5Äw&PÚ?ª ÔEýôEØIþ");
        $padtext = mcrypt_decrypt(self::ENC, base64_decode($key), $crypt, self::MODE, self::IV);

        $pad = ord($padtext{strlen($padtext) - 1});
        if ($pad > strlen($padtext)) {
            return false;
        }
        if (strspn($padtext, $padtext{strlen($padtext) - 1}, strlen($padtext) - $pad) != $pad) {
            $text = "Error";
        }
        $text = substr($padtext, 0, -1 * $pad);
        return $text;
    }

}

// End of AvantGardeLib class

